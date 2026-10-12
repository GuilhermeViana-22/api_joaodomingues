<?php

namespace App\Services;

use App\Jobs\TraduzirTextosSite;
use App\Models\TextoSite;
use App\Support\FilaAssincrona;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Textos do site (js/idiomas/*.js), guardados por idioma na tabela textos_site.
 *
 * O painel só edita o português de Portugal. As versões en/fr/es são da API:
 * ao gravar, cada chave cujo texto PT mudou é traduzida de novo; as outras
 * ficam como estão (incluindo traduções feitas à mão no seeder).
 *
 * Como se sabe o que mudou: cada linha en/fr/es tem `fontes`, um mapa
 * chave → md5 do texto PT de onde veio a tradução. Fonte diferente do PT
 * atual (ou chave sem tradução) = tradução por fazer.
 *
 * Os dicionários são aninhados ({ hero: { titulo: '…' } }); aqui trabalha-se
 * com eles achatados ('hero.titulo' => '…') e voltam a aninhar-se ao gravar.
 */
class TextosSiteService
{
    public const IDIOMAS = ['pt', 'en', 'fr', 'es'];

    public function __construct(private readonly TradutorService $tradutor) {}

    /** @return array<string, array<string, mixed>|object> Os 4 idiomas, no formato que o site lê. */
    public function obter(): array
    {
        $linhas = TextoSite::query()->get()->keyBy('locale');
        $textos = [];
        foreach (self::IDIOMAS as $locale) {
            $textos[$locale] = $linhas->get($locale)?->conteudo ?: (object) [];
        }

        return $textos;
    }

    /**
     * Grava o português e atualiza as traduções que ficaram desatualizadas.
     *
     * @param  array<string, mixed>  $pt  dicionário aninhado
     * @return array{textos: array, pendentes: list<string>} pendentes = idiomas cuja tradução falhou
     */
    public function guardar(array $pt): array
    {
        // O Laravel transforma "" em null (ConvertEmptyStringsToNull): volta a "",
        // senão o site recebia null num texto que a pessoa só deixou em branco
        $pt = $this->aninhar($this->achatar($pt));

        $linhaPt = TextoSite::query()->find('pt');
        $ptAntigo = $this->achatar($linhaPt?->conteudo ?? []);

        // 1. O português grava já, numa transação curta
        DB::beginTransaction();
        try {
            TextoSite::query()->updateOrCreate(['locale' => 'pt'], ['conteudo' => $pt]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Falha ao gravar os textos em português; nada mudou.', ['erro' => $e->getMessage()]);
            throw $e;
        }

        // 2. As traduções vêm a seguir (chamadas HTTP, fora da transação do PT)
        $pendentes = $this->sincronizarTraducoes($ptAntigo);

        return ['textos' => $this->obter(), 'pendentes' => $pendentes];
    }

    /**
     * Traduz as chaves desatualizadas de en/fr/es e grava as 3 linhas numa só
     * transação. Usado ao gravar e pelo job TraduzirTextosSite (repetição).
     *
     * @param  array<string, string>|null  $ptAntigo  português anterior, achatado (só para linhas sem `fontes`)
     * @param  bool  $agendarRepeticao  false quando quem chama já é o job (ele repete com release())
     * @return list<string> idiomas em que a tradução falhou e ficou por fazer
     */
    public function sincronizarTraducoes(?array $ptAntigo = null, bool $agendarRepeticao = true): array
    {
        $pt = $this->achatar(TextoSite::query()->find('pt')?->conteudo ?? []);
        $linhas = TextoSite::query()->whereIn('locale', TradutorService::IDIOMAS_SITE)->get()->keyBy('locale');

        // 1.ª passagem: o que falta traduzir em cada idioma
        $estado = [];
        foreach (TradutorService::IDIOMAS_SITE as $idioma) {
            $linha = $linhas->get($idioma);
            $conteudo = $this->achatar($linha?->conteudo ?? []);
            $fontes = $linha?->fontes ?? $this->fontesIniciais($ptAntigo ?? $pt, $conteudo);

            // O grupo `imoveis` (textos dos imóveis de exemplo do site) não é
            // desta tela nem tem português aqui: passa intacto
            $deImoveis = array_filter($conteudo, fn ($c) => str_starts_with($c, 'imoveis.'), ARRAY_FILTER_USE_KEY);

            // Chaves que já não existem em português saem das traduções
            $conteudo = array_intersect_key($conteudo, $pt);
            $fontes = array_intersect_key($fontes, $pt);

            $porFazer = array_keys(array_filter(
                $pt,
                fn ($texto, $chave) => ($fontes[$chave] ?? null) !== $this->impressao($texto) || ! array_key_exists($chave, $conteudo),
                ARRAY_FILTER_USE_BOTH,
            ));

            $estado[$idioma] = compact('conteudo', 'fontes', 'deImoveis', 'porFazer');
        }

        // 2. Uma só chamada ao tradutor para os 3 idiomas (em paralelo, com tempo
        // máximo). Sem motor (TRADUCAO_DRIVER=nenhum) nada se marca como
        // traduzido: as chaves ficam por fazer para quando houver motor.
        $chaves = array_values(array_unique(array_merge(...array_column($estado, 'porFazer'))));
        $idiomas = array_keys(array_filter($estado, fn ($e) => $e['porFazer'] !== []));
        $traducoes = $chaves !== [] && $this->tradutor->ativo()
            ? $this->tradutor->traduzirParaIdiomas(array_map(fn ($c) => $pt[$c], $chaves), $idiomas)
            : [];

        // 3. Aplica o que traduziu; o que falhou fica como estava (a tradução
        // antiga, ou nenhuma e o site usa js/idiomas/*.js). As fontes dessas
        // chaves não mudam, por isso a próxima tentativa refaz só elas.
        $novas = [];
        $pendentes = [];
        foreach ($estado as $idioma => ['conteudo' => $conteudo, 'fontes' => $fontes, 'deImoveis' => $deImoveis, 'porFazer' => $porFazer]) {
            $resultado = $traducoes[$idioma] ?? null;

            if ($resultado instanceof Throwable) {
                $pendentes[] = $idioma;
                Log::warning('Tradução dos textos do site falhou; fica para nova tentativa.', [
                    'idioma' => $idioma,
                    'chaves' => count($porFazer),
                    'erro' => $resultado->getMessage(),
                ]);
            } elseif (is_array($resultado)) {
                $porChave = array_combine($chaves, $resultado);
                foreach ($porFazer as $chave) {
                    $conteudo[$chave] = $porChave[$chave];
                    $fontes[$chave] = $this->impressao($pt[$chave]);
                }
            }

            $novas[$idioma] = ['conteudo' => $this->aninhar([...$conteudo, ...$deImoveis]), 'fontes' => $fontes];
        }

        DB::beginTransaction();
        try {
            foreach ($novas as $idioma => $dados) {
                TextoSite::query()->updateOrCreate(['locale' => $idioma], $dados);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Falha ao gravar as traduções dos textos do site.', ['erro' => $e->getMessage()]);
            throw $e;
        }

        if ($pendentes !== [] && $agendarRepeticao && FilaAssincrona::ativa()) {
            TraduzirTextosSite::dispatch()->delay(now()->addMinute());
        }

        return $pendentes;
    }

    /**
     * Linhas gravadas antes de existir `fontes`: as traduções que lá estão
     * contam como feitas a partir do português anterior. Assim a 1.ª gravação
     * só traduz o que mudou, e não deita fora as traduções do seeder.
     *
     * @param  array<string, string>  $ptAnterior
     * @param  array<string, string>  $conteudo
     * @return array<string, string>
     */
    private function fontesIniciais(array $ptAnterior, array $conteudo): array
    {
        $fontes = [];
        foreach (array_intersect_key($ptAnterior, $conteudo) as $chave => $texto) {
            $fontes[$chave] = $this->impressao($texto);
        }

        return $fontes;
    }

    private function impressao(string $texto): string
    {
        return md5($texto);
    }

    /**
     * { hero: { titulo: 'x' } } → ['hero.titulo' => 'x']. Valores null viram '';
     * grupos vazios ({}) não são textos e ficam de fora.
     *
     * @return array<string, string>
     */
    public function achatar(array $dicionario): array
    {
        $plano = array_filter(Arr::dot($dicionario), fn ($v) => ! is_array($v));

        return array_map(fn ($v) => (string) ($v ?? ''), $plano);
    }

    /** ['hero.titulo' => 'x'] → { hero: { titulo: 'x' } } */
    public function aninhar(array $plano): array
    {
        return Arr::undot($plano);
    }
}
