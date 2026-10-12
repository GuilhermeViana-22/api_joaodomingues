<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Tradução automática dos textos dos imóveis.
 *
 * O painel só recebe texto em português de Portugal; ao gravar, a API gera as
 * versões em inglês, francês e espanhol que o site mostra (`traducoes`).
 *
 * Motores (config/services.php → traducao.driver, env TRADUCAO_DRIVER):
 *  - deepl:  API oficial da DeepL (DEEPL_API_KEY). Melhor qualidade; o plano
 *            gratuito dá 500 mil caracteres por mês, chaves terminadas em ":fx".
 *  - google: endpoint público do Google Tradutor, sem chave. Serve para pouco
 *            volume, mas não tem garantia de serviço: em produção prefira a DeepL.
 *  - nenhum: não traduz (testes, ou para desligar). O site mostra o português.
 *
 * Nunca lança exceção para fora: um idioma que falhe fica de fora do resultado
 * e o site usa o português nesse idioma. O erro fica no log.
 */
class TradutorService
{
    /** Idiomas de destino, com o código de cada motor. */
    private const IDIOMAS = [
        'en' => ['deepl' => 'EN-GB', 'google' => 'en'],
        'fr' => ['deepl' => 'FR', 'google' => 'fr'],
        'es' => ['deepl' => 'ES', 'google' => 'es'],
    ];

    /** Idiomas do site além do português. */
    public const IDIOMAS_SITE = ['en', 'fr', 'es'];

    /** Campos de texto do imóvel que o site traduz. */
    public const CAMPOS = ['titulo', 'resumo', 'descricao', 'destaques'];

    /**
     * Traduz os textos de um imóvel para todos os idiomas do site.
     *
     * Devolve ['en' => [...], 'fr' => [...], 'es' => [...]]; idiomas que falharam não aparecem.
     *
     * @param  array{titulo: string, resumo: string, descricao: string, destaques?: list<string>}  $textos
     * @return array<string, array{titulo: string, resumo: string, descricao: string, destaques: list<string>}>
     */
    public function traduzirImovel(array $textos): array
    {
        // Motor desligado ou desconhecido: sem traduções (o site usa o português)
        if (! in_array($this->driver(), ['deepl', 'google'], true)) {
            return [];
        }

        // Achata tudo numa lista só: [titulo, resumo, descricao, destaque1, destaque2, ...]
        $destaques = array_values($textos['destaques'] ?? []);
        $lista = [$textos['titulo'], $textos['resumo'], $textos['descricao'], ...$destaques];

        $resultado = [];
        foreach (array_keys(self::IDIOMAS) as $idioma) {
            try {
                $traduzidos = $this->traduzir($lista, $idioma);

                $resultado[$idioma] = [
                    'titulo' => $traduzidos[0],
                    'resumo' => $traduzidos[1],
                    'descricao' => $traduzidos[2],
                    'destaques' => array_slice($traduzidos, 3),
                ];
            } catch (Throwable $e) {
                Log::warning('Tradução automática falhou; o site usa o português neste idioma.', [
                    'idioma' => $idioma,
                    'driver' => $this->driver(),
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $resultado;
    }

    /**
     * Traduz uma lista de textos de PT-PT para um idioma, mantendo a ordem.
     *
     * @param  list<string>  $textos
     * @return list<string>
     *
     * @throws RuntimeException quando o motor não responde ou devolve algo inesperado
     */
    public function traduzir(array $textos, string $idioma): array
    {
        // Textos vazios não vão ao motor (gasta quota e algumas APIs recusam)
        $indices = array_keys(array_filter($textos, fn ($t) => trim((string) $t) !== ''));
        if ($indices === []) {
            return $textos;
        }
        $aTraduzir = array_values(array_map(fn ($i) => (string) $textos[$i], $indices));

        $traduzidos = match ($this->driver()) {
            'deepl' => $this->deepl($aTraduzir, self::IDIOMAS[$idioma]['deepl']),
            'google' => $this->google($aTraduzir, self::IDIOMAS[$idioma]['google']),
            default => throw new RuntimeException("Motor de tradução desconhecido: {$this->driver()}."),
        };

        // Volta a pôr cada tradução no lugar original
        $saida = $textos;
        foreach ($indices as $n => $i) {
            $saida[$i] = $traduzidos[$n];
        }

        return array_values($saida);
    }

    public function driver(): string
    {
        return (string) config('services.traducao.driver', 'nenhum');
    }

    /**
     * DeepL: um só pedido com todos os textos (o parâmetro `text` aceita lista).
     *
     * @param  list<string>  $textos
     * @return list<string>
     */
    private function deepl(array $textos, string $alvo): array
    {
        $chave = (string) config('services.traducao.deepl_key');
        if ($chave === '') {
            throw new RuntimeException('DEEPL_API_KEY em falta.');
        }

        // Chaves do plano gratuito terminam em ":fx" e usam outro domínio
        $url = str_ends_with($chave, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $resposta = Http::timeout($this->timeout())
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$chave])
            ->post($url, [
                'text' => $textos,
                'source_lang' => 'PT',
                'target_lang' => $alvo,
                'preserve_formatting' => true,
            ]);

        $this->exigirSucesso($resposta, 'DeepL');

        $traducoes = array_column($resposta->json('translations') ?? [], 'text');
        if (count($traducoes) !== count($textos)) {
            throw new RuntimeException('DeepL devolveu um número de traduções diferente do pedido.');
        }

        return $traducoes;
    }

    /**
     * Google Tradutor (endpoint público "gtx"): um pedido por texto, em paralelo.
     * O texto vai no corpo (POST) para descrições longas não estourarem o URL.
     *
     * @param  list<string>  $textos
     * @return list<string>
     */
    private function google(array $textos, string $alvo): array
    {
        $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=pt&dt=t&tl='.$alvo;

        $respostas = Http::pool(fn (Pool $pool) => array_map(
            fn (string $texto) => $pool->asForm()->timeout($this->timeout())->post($url, ['q' => $texto]),
            $textos,
        ));

        return array_map(function ($resposta) {
            // Falhas de rede chegam ao pool como exceção, não como resposta
            if ($resposta instanceof Throwable) {
                throw new RuntimeException('Google Tradutor sem resposta: '.$resposta->getMessage());
            }
            $this->exigirSucesso($resposta, 'Google Tradutor');

            // Formato: [[["frase traduzida", "frase original", ...], ...], ...]
            $frases = $resposta->json('0');
            if (! is_array($frases)) {
                throw new RuntimeException('Google Tradutor devolveu um formato inesperado.');
            }

            return implode('', array_map(fn ($f) => (string) ($f[0] ?? ''), $frases));
        }, $respostas);
    }

    private function exigirSucesso(Response $resposta, string $motor): void
    {
        if (! $resposta->successful()) {
            throw new RuntimeException("{$motor} respondeu HTTP {$resposta->status()}.");
        }
    }

    private function timeout(): int
    {
        return (int) config('services.traducao.timeout', 8);
    }
}
