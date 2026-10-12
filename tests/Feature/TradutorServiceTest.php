<?php

namespace Tests\Feature;

use App\Services\TradutorService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * O tradutor com o motor do Google (sem chave), que é o que corre quando não
 * há DEEPL_API_KEY. O Google responde 429 quando recebe pedidos a mais: estes
 * testes garantem que isso nunca impede de gravar nem prende o painel.
 */
class TradutorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.traducao.driver' => 'google']);
    }

    /** Resposta no formato do Google: "(idioma) texto". */
    private function googleQueTraduz(array $idiomasEmBaixo = []): void
    {
        Http::fake([
            'translate.googleapis.com/*' => function (Request $pedido) use ($idiomasEmBaixo) {
                parse_str((string) parse_url($pedido->url(), PHP_URL_QUERY), $q);
                if (in_array($q['tl'], $idiomasEmBaixo, true)) {
                    return Http::response('Too Many Requests', 429);
                }

                return Http::response([[['('.$q['tl'].') '.$pedido['q'], $pedido['q']]]]);
            },
        ]);
    }

    private function tradutor(): TradutorService
    {
        return app(TradutorService::class);
    }

    public function test_traduz_para_os_3_idiomas_mantendo_a_ordem_e_os_vazios(): void
    {
        $this->googleQueTraduz();

        $r = $this->tradutor()->traduzirParaIdiomas(['Casa', '', 'Jardim']);

        $this->assertSame(['(en) Casa', '', '(en) Jardim'], $r['en']);
        $this->assertSame(['(fr) Casa', '', '(fr) Jardim'], $r['fr']);
        $this->assertSame(['(es) Casa', '', '(es) Jardim'], $r['es']);
        // 2 textos x 3 idiomas; o vazio não vai ao motor
        Http::assertSentCount(6);
    }

    public function test_protege_variaveis_e_html(): void
    {
        $this->googleQueTraduz();

        $r = $this->tradutor()->traduzir(['Olá {{nome}}!<br>Até já', '{{count}} quartos'], 'fr');

        $this->assertSame(['(fr) Olá {{nome}}!<br>Até já', '(fr) {{count}} quartos'], $r);
        Http::assertSent(fn (Request $p) => ! str_contains($p['q'], '{{') && ! str_contains($p['q'], '<br>'));
    }

    public function test_um_idioma_com_429_nao_estraga_os_outros(): void
    {
        $this->googleQueTraduz(['fr']);

        $r = $this->tradutor()->traduzirParaIdiomas(['Casa']);

        $this->assertSame(['(en) Casa'], $r['en']);
        $this->assertInstanceOf(RuntimeException::class, $r['fr']);
        $this->assertStringContainsString('429', $r['fr']->getMessage());
        $this->assertSame(['(es) Casa'], $r['es']);
    }

    public function test_tempo_esgotado_devolve_falha_sem_chamar_o_motor(): void
    {
        $this->googleQueTraduz();
        config(['services.traducao.orcamento' => -1]);

        $r = $this->tradutor()->traduzirParaIdiomas(['Casa']);

        foreach (['en', 'fr', 'es'] as $idioma) {
            $this->assertInstanceOf(RuntimeException::class, $r[$idioma]);
            $this->assertStringContainsString('Tempo máximo', $r[$idioma]->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_muitos_textos_vao_em_lotes_de_12_pedidos(): void
    {
        $this->googleQueTraduz();

        $r = $this->tradutor()->traduzirParaIdiomas(array_map(fn ($n) => "Texto {$n}", range(1, 20)));

        $this->assertCount(20, $r['es']);
        $this->assertSame('(es) Texto 20', $r['es'][19]);
        Http::assertSentCount(60);
    }

    public function test_traduzir_um_idioma_lanca_excecao_quando_falha(): void
    {
        $this->googleQueTraduz(['en']);

        $this->expectException(RuntimeException::class);
        $this->tradutor()->traduzir(['Casa'], 'en');
    }
}
