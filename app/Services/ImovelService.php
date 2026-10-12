<?php

namespace App\Services;

use App\Jobs\TraduzirImovel;
use App\Models\Imovel;
use App\Support\FilaAssincrona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Regras de gravação dos imóveis (criar, editar, publicar, fotos).
 *
 * Fluxo de criar/editar:
 *  1. Traduz os textos PT-PT para EN/FR/ES (fora da transação: é uma chamada
 *     HTTP lenta e não deve prender linhas da base enquanto espera).
 *  2. Abre a transação, grava o imóvel e as fotos; qualquer erro faz rollback.
 *  3. Só depois do commit apaga do disco as fotos que saíram do imóvel. Se o
 *     rollback acontecesse com os ficheiros já apagados, o imóvel ficava com
 *     fotos partidas.
 *  4. Se algum idioma não traduziu (motor em baixo), põe o job TraduzirImovel
 *     na fila: o worker repete sozinho, sem comandos manuais.
 */
class ImovelService
{
    public function __construct(private readonly TradutorService $tradutor) {}

    public function criar(array $dados): Imovel
    {
        $traducoes = $this->tradutor->traduzirImovel($this->textos($dados));

        DB::beginTransaction();
        try {
            $referencia = $this->referencia($dados['referencia'] ?? '');
            $id = $this->idLivre(Str::slug($referencia));

            $imovel = Imovel::query()->create([
                ...$this->atributos($dados, $referencia),
                'traducoes' => $traducoes,
                'id' => $id,
                'slug' => $id,
            ]);

            $this->sincronizarImagens($imovel, $dados['imagens'] ?? []);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Falha ao criar imóvel; nada foi gravado.', ['erro' => $e->getMessage()]);
            throw $e;
        }

        $this->repetirTraducaoSeFaltar($imovel);

        // refresh(): o create() não traz os valores por defeito da base
        // (finalidade "venda", moeda "EUR", país...). Sem isto a resposta saía
        // com null nesses campos e o painel reenviava-os na edição seguinte.
        return $imovel->refresh()->load('imagens');
    }

    public function atualizar(Imovel $imovel, array $dados): Imovel
    {
        // Só volta a traduzir se o texto em português mudou ou se falta algum
        // idioma (ex.: o motor falhou da última vez). Poupa quota e tempo.
        $traducoes = $this->textosMudaram($imovel, $dados) || $this->faltaIdioma($imovel)
            ? $this->tradutor->traduzirImovel($this->textos($dados))
            : $imovel->traducoes;

        $removidas = [];

        DB::beginTransaction();
        try {
            $referencia = $this->referencia($dados['referencia'] ?? $imovel->referencia, $imovel);
            $imovel->fill([...$this->atributos($dados, $referencia), 'traducoes' => $traducoes]);
            $imovel->save();

            if (array_key_exists('imagens', $dados)) {
                $removidas = $this->sincronizarImagens($imovel, $dados['imagens'] ?? []);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Falha ao editar imóvel; as alterações foram desfeitas.', ['id' => $imovel->id, 'erro' => $e->getMessage()]);
            throw $e;
        }

        // Fora da transação: só apaga ficheiros quando a base já confirmou
        foreach ($removidas as $url) {
            $this->apagarFicheiroLocal($url);
        }

        $this->repetirTraducaoSeFaltar($imovel);

        return $imovel->load('imagens');
    }

    /**
     * Volta a gerar as traduções de um imóvel (comando imoveis:traduzir).
     * Devolve os idiomas que ficaram traduzidos.
     *
     * @return list<string>
     */
    public function retraduzir(Imovel $imovel): array
    {
        $traducoes = $this->tradutor->traduzirImovel([
            'titulo' => $imovel->titulo,
            'resumo' => $imovel->resumo,
            'descricao' => $imovel->descricao,
            'destaques' => $imovel->destaques ?? [],
        ]);

        // Se nada traduziu (motor em baixo), mantém o que havia
        if ($traducoes !== []) {
            $imovel->forceFill(['traducoes' => $traducoes])->saveQuietly();
        }

        return array_keys($traducoes);
    }

    public function publicar(Imovel $imovel, bool $publicado): Imovel
    {
        if ($publicado && $imovel->imagens()->count() === 0) {
            throw ValidationException::withMessages([
                'imagens' => 'Adicione pelo menos a foto de destaque antes de publicar.',
            ]);
        }

        $imovel->publicado = $publicado;
        $imovel->save();

        return $imovel->load('imagens');
    }

    /**
     * Substitui as fotos do imóvel pela lista recebida (a ordem é a do site).
     * Devolve os endereços que saíram, para apagar do disco depois do commit.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function sincronizarImagens(Imovel $imovel, array $urls): array
    {
        $anteriores = $imovel->imagens()->pluck('caminho');
        $imovel->imagens()->delete();

        foreach (array_values($urls) as $ordem => $url) {
            $imovel->imagens()->create([
                'caminho' => $url,
                'ordem' => $ordem,
            ]);
        }

        return $anteriores->map(fn ($c) => (string) $c)->diff($urls)->values()->all();
    }

    public function apagarFicheiroLocal(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $prefixo = '/storage/';
        if (! str_starts_with($path, $prefixo)) {
            return;
        }

        $relativo = substr($path, strlen($prefixo));
        if (! str_starts_with($relativo, 'imoveis/')) {
            return;
        }

        Storage::disk('public')->delete($relativo);
    }

    private function referencia(string $informada, ?Imovel $actual = null): string
    {
        $ref = strtoupper(trim($informada));
        if ($ref !== '') {
            return $ref;
        }

        if ($actual !== null) {
            return $actual->referencia;
        }

        $maximo = Imovel::withTrashed()
            ->pluck('referencia')
            ->map(fn (string $r) => (int) preg_replace('/\D/', '', $r))
            ->max() ?? 0;

        return 'JD-'.str_pad((string) ($maximo + 1), 3, '0', STR_PAD_LEFT);
    }

    private function idLivre(string $base): string
    {
        $base = $base !== '' ? $base : 'imovel';
        $id = $base;
        $n = 2;

        while (Imovel::withTrashed()->where(function ($q) use ($id) {
            $q->where('id', $id)->orWhere('slug', $id);
        })->exists()) {
            $id = $base.'-'.$n;
            $n++;
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function atributos(array $dados, string $referencia): array
    {
        $base = [
            'referencia' => $referencia,
            'titulo' => $dados['titulo'],
            'tipologia' => $dados['tipologia'],
            'tipo' => $dados['tipo'],
            'zona' => $dados['zona'],
            'preco' => $dados['preco'],
            'area' => $dados['area'],
            'quartos' => $dados['quartos'],
            'casas_banho' => $dados['casasBanho'],
            'estado' => $dados['estado'],
            'resumo' => $dados['resumo'],
            'descricao' => $dados['descricao'],
            'destaques' => array_values($dados['destaques'] ?? []),
            'publicado' => (bool) ($dados['publicado'] ?? false),
        ];

        $opcionais = [
            'finalidade' => 'finalidade',
            'moeda' => 'moeda',
            'areaTotal' => 'area_total',
            'vagas' => 'vagas',
            'endereco' => 'endereco',
            'cidade' => 'cidade',
            'distrito' => 'distrito',
            'pais' => 'pais',
            'codigoPostal' => 'codigo_postal',
            'metaTitulo' => 'meta_titulo',
            'metaDescricao' => 'meta_descricao',
            'destaque' => 'destaque',
            'ordem' => 'ordem',
        ];

        // Colunas NOT NULL com valor por defeito: um null aqui daria erro SQL,
        // por isso null quer dizer "deixar como está" (ou o defeito, se novo)
        $semNull = ['finalidade', 'moeda', 'pais', 'destaque', 'ordem'];

        foreach ($opcionais as $entrada => $coluna) {
            if (! array_key_exists($entrada, $dados)) {
                continue;
            }
            if ($dados[$entrada] === null && in_array($coluna, $semNull, true)) {
                continue;
            }
            $base[$coluna] = $dados[$entrada];
        }

        return $base;
    }

    /**
     * Textos em português que alimentam a tradução.
     *
     * @return array{titulo: string, resumo: string, descricao: string, destaques: list<string>}
     */
    private function textos(array $dados): array
    {
        return [
            'titulo' => (string) $dados['titulo'],
            'resumo' => (string) $dados['resumo'],
            'descricao' => (string) $dados['descricao'],
            'destaques' => array_values($dados['destaques'] ?? []),
        ];
    }

    private function textosMudaram(Imovel $imovel, array $dados): bool
    {
        $atuais = [
            'titulo' => $imovel->titulo,
            'resumo' => $imovel->resumo,
            'descricao' => $imovel->descricao,
            'destaques' => array_values($imovel->destaques ?? []),
        ];

        return $atuais !== $this->textos($dados);
    }

    /** Põe na fila uma nova tentativa de tradução se ficou algum idioma por fazer. */
    private function repetirTraducaoSeFaltar(Imovel $imovel): void
    {
        if ($this->tradutor->ativo() && FilaAssincrona::ativa() && $this->faltaIdioma($imovel)) {
            TraduzirImovel::dispatch($imovel->id)->delay(now()->addMinute());
        }
    }

    private function faltaIdioma(Imovel $imovel): bool
    {
        return count(array_intersect(TradutorService::IDIOMAS_SITE, array_keys($imovel->traducoes ?? []))) < count(TradutorService::IDIOMAS_SITE);
    }
}
