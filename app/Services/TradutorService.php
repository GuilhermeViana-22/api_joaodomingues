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
        if (! $this->ativo()) {
            return [];
        }

        // Achata tudo numa lista só: [titulo, resumo, descricao, destaque1, destaque2, ...]
        $destaques = array_values($textos['destaques'] ?? []);
        $lista = [$textos['titulo'], $textos['resumo'], $textos['descricao'], ...$destaques];

        $resultado = [];
        foreach ($this->traduzirParaIdiomas($lista) as $idioma => $traduzidos) {
            if ($traduzidos instanceof Throwable) {
                Log::warning('Tradução automática falhou; o site usa o português neste idioma.', [
                    'idioma' => $idioma,
                    'driver' => $this->driver(),
                    'erro' => $traduzidos->getMessage(),
                ]);

                continue;
            }

            $resultado[$idioma] = [
                'titulo' => $traduzidos[0],
                'resumo' => $traduzidos[1],
                'descricao' => $traduzidos[2],
                'destaques' => array_slice($traduzidos, 3),
            ];
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
        $resultado = $this->traduzirParaIdiomas($textos, [$idioma])[$idioma];
        if ($resultado instanceof Throwable) {
            throw $resultado;
        }

        return $resultado;
    }

    /**
     * Traduz a mesma lista para vários idiomas de uma vez, mantendo a ordem.
     *
     * Os idiomas correm em paralelo (no Google) e há um tempo máximo total
     * (TRADUCAO_ORCAMENTO, 10 s): quem grava no painel não fica à espera de um
     * motor lento ou a recusar pedidos (429). O idioma que falhar ou não
     * acabar a tempo vem como exceção, e quem chamou decide (os serviços
     * guardam o resto e põem um job a repetir).
     *
     * @param  list<string>  $textos
     * @param  list<string>|null  $idiomas  por defeito en, fr e es
     * @return array<string, list<string>|Throwable>
     */
    public function traduzirParaIdiomas(array $textos, ?array $idiomas = null): array
    {
        $idiomas ??= self::IDIOMAS_SITE;
        $textos = array_values($textos);

        // Textos vazios não vão ao motor (gasta quota e algumas APIs recusam)
        $indices = array_keys(array_filter($textos, fn ($t) => trim((string) $t) !== ''));
        if ($indices === []) {
            return array_fill_keys($idiomas, $textos);
        }

        // Variáveis ({{nome}}) e HTML (<br>) não podem ser traduzidos: saem
        // trocados por marcadores ⟦0⟧, ⟦1⟧... e voltam no fim
        $protegidos = array_map(fn ($i) => $this->proteger((string) $textos[$i]), $indices);
        $aTraduzir = array_column($protegidos, 'texto');
        $limite = microtime(true) + $this->orcamento();

        $brutos = match ($this->driver()) {
            'deepl' => $this->deeplVarios($aTraduzir, $idiomas, $limite),
            'google' => $this->googleVarios($aTraduzir, $idiomas, $limite),
            default => array_fill_keys($idiomas, new RuntimeException("Motor de tradução desconhecido: {$this->driver()}.")),
        };

        // Volta a pôr cada tradução no lugar original, com as variáveis
        $resultado = [];
        foreach ($brutos as $idioma => $traduzidos) {
            if ($traduzidos instanceof Throwable) {
                $resultado[$idioma] = $traduzidos;

                continue;
            }
            $saida = $textos;
            foreach ($indices as $n => $i) {
                $saida[$i] = $this->restaurar($traduzidos[$n], $protegidos[$n]['pecas'], (string) $textos[$i], $idioma);
            }
            $resultado[$idioma] = $saida;
        }

        return $resultado;
    }

    /**
     * DeepL: um pedido por idioma com até 50 textos (é rápida; em série chega).
     *
     * @param  list<string>  $textos
     * @param  list<string>  $idiomas
     * @return array<string, list<string>|Throwable>
     */
    private function deeplVarios(array $textos, array $idiomas, float $limite): array
    {
        $resultado = [];
        foreach ($idiomas as $idioma) {
            try {
                $traduzidos = [];
                foreach (array_chunk($textos, 50) as $lote) {
                    $this->exigirTempo($limite);
                    array_push($traduzidos, ...$this->deepl($lote, self::IDIOMAS[$idioma]['deepl']));
                }
                $resultado[$idioma] = $traduzidos;
            } catch (Throwable $e) {
                $resultado[$idioma] = $e;
            }
        }

        return $resultado;
    }

    /**
     * Google Tradutor (endpoint público "gtx"): um pedido por texto e idioma,
     * em paralelo, até 12 de cada vez (mais do que isso o Google devolve 429).
     * O texto vai no corpo (POST) para descrições longas não estourarem o URL.
     *
     * @param  list<string>  $textos
     * @param  list<string>  $idiomas
     * @return array<string, list<string>|Throwable>
     */
    private function googleVarios(array $textos, array $idiomas, float $limite): array
    {
        $tarefas = [];
        foreach ($idiomas as $idioma) {
            foreach ($textos as $n => $texto) {
                $tarefas[] = [$idioma, $n, $texto];
            }
        }

        $traduzidos = array_fill_keys($idiomas, []);
        $falhas = [];

        foreach (array_chunk($tarefas, 12) as $lote) {
            // Idiomas que já falharam não gastam mais pedidos
            $lote = array_values(array_filter($lote, fn ($t) => ! isset($falhas[$t[0]])));
            if ($lote === []) {
                continue;
            }
            try {
                $this->exigirTempo($limite);
            } catch (RuntimeException $e) {
                foreach ($lote as [$idioma]) {
                    $falhas[$idioma] ??= $e;
                }

                continue;
            }

            $respostas = Http::pool(fn (Pool $pool) => array_map(
                fn ($t) => $pool->asForm()
                    ->timeout($this->timeout())
                    ->post('https://translate.googleapis.com/translate_a/single?client=gtx&sl=pt&dt=t&tl='.self::IDIOMAS[$t[0]]['google'], ['q' => $t[2]]),
                $lote,
            ));

            foreach ($lote as $k => [$idioma, $n]) {
                try {
                    $traduzidos[$idioma][$n] = $this->lerGoogle($respostas[$k]);
                } catch (Throwable $e) {
                    $falhas[$idioma] ??= $e;
                }
            }
        }

        $resultado = [];
        foreach ($idiomas as $idioma) {
            if (isset($falhas[$idioma])) {
                $resultado[$idioma] = $falhas[$idioma];

                continue;
            }
            ksort($traduzidos[$idioma]);
            $resultado[$idioma] = array_values($traduzidos[$idioma]);
        }

        return $resultado;
    }

    /** Texto traduzido de uma resposta do Google (ou exceção se falhou). */
    private function lerGoogle(mixed $resposta): string
    {
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
    }

    private function exigirTempo(float $limite): void
    {
        if (microtime(true) > $limite) {
            throw new RuntimeException('Tempo máximo de tradução esgotado; fica para nova tentativa.');
        }
    }

    private function orcamento(): int
    {
        return (int) config('services.traducao.orcamento', 10);
    }

    /**
     * Troca variáveis e tags por marcadores numerados que os motores não traduzem.
     *
     * @return array{texto: string, pecas: list<string>}
     */
    private function proteger(string $texto): array
    {
        $pecas = [];
        $protegido = preg_replace_callback('/\{\{\s*[\w.]+\s*\}\}|<[^>]+>/u', function ($m) use (&$pecas) {
            $pecas[] = $m[0];

            return '⟦'.(count($pecas) - 1).'⟧';
        }, $texto);

        return ['texto' => (string) $protegido, 'pecas' => $pecas];
    }

    /**
     * Repõe as variáveis e tags. Se o motor perdeu ou duplicou algum marcador,
     * a tradução não é de confiança (o site partiria a variável): fica o
     * texto em português nesse caso.
     *
     * @param  list<string>  $pecas
     */
    private function restaurar(string $traduzido, array $pecas, string $original, string $idioma): string
    {
        if ($pecas === []) {
            return $traduzido;
        }

        $usados = [];
        $resultado = preg_replace_callback('/⟦\s*(\d+)\s*⟧/u', function ($m) use ($pecas, &$usados) {
            $usados[] = (int) $m[1];

            return $pecas[(int) $m[1]] ?? $m[0];
        }, $traduzido);

        sort($usados);
        if ($usados !== array_keys($pecas)) {
            Log::warning('Tradução perdeu variáveis; fica o texto em português.', ['idioma' => $idioma, 'original' => $original]);

            return $original;
        }

        return (string) $resultado;
    }

    /** Há um motor de tradução configurado (deepl ou google)? */
    public function ativo(): bool
    {
        return in_array($this->driver(), ['deepl', 'google'], true);
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
