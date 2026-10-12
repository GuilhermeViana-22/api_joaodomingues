<?php

namespace Tests\Feature;

use App\Jobs\TraduzirTextosSite;
use App\Models\TextoSite;
use App\Models\User;
use App\Services\TextosSiteService;
use Database\Seeders\TextosSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Textos do site: o painel grava só português (PUT /textos { pt }) e a API
 * traduz para en/fr/es apenas o que mudou.
 *
 * O tradutor é falso: devolve "[EN-GB] texto" (o código de idioma da DeepL
 * antes do texto), o que deixa ver o que foi traduzido e para que idioma.
 */
class TextosSiteTest extends TestCase
{
    use RefreshDatabase;

    /** Como o tradutor falso trata os marcadores ⟦n⟧: 'manter', 'espacos' ou 'perder'. */
    private string $marcadores = 'manter';

    private bool $motorEmBaixo = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TextosSiteSeeder::class);
        config(['services.traducao.driver' => 'deepl', 'services.traducao.deepl_key' => 'chave:fx']);

        Http::fake([
            'api-free.deepl.com/*' => function (Request $pedido) {
                if ($this->motorEmBaixo) {
                    return Http::response('erro', 503);
                }

                return Http::response(['translations' => array_map(fn ($t) => ['text' => match ($this->marcadores) {
                    'espacos' => '['.$pedido['target_lang'].'] '.preg_replace('/⟦(\d+)⟧/u', '⟦ $1 ⟧', $t),
                    'perder' => '['.$pedido['target_lang'].'] '.preg_replace('/⟦\d+⟧/u', '', $t),
                    default => '['.$pedido['target_lang'].'] '.$t,
                }], $pedido['text'])]);
            },
        ]);

        Passport::actingAs(User::factory()->create(['perfil' => 'editor']));
    }

    public function test_site_le_os_4_idiomas_sem_sessao(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/textos')
            ->assertOk()
            ->assertJsonStructure(['pt', 'en', 'fr', 'es'])
            ->assertJsonPath('pt.hero.eyebrow', 'Consultor imobiliário');
    }

    public function test_gravar_so_em_portugues_traduz_apenas_o_que_mudou(): void
    {
        $antes = $this->getJson('/api/v1/textos')->json();

        $pt = $antes['pt'];
        $pt['hero']['titulo'] = 'Venda a sua casa com tranquilidade.';
        $pt['hero']['texto'] = 'Acompanho tudo, do início ao fim.';

        $resposta = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json();

        // As chaves alteradas foram traduzidas para os 3 idiomas
        $this->assertSame('[EN-GB] Venda a sua casa com tranquilidade.', $resposta['textos']['en']['hero']['titulo']);
        $this->assertSame('[FR] Acompanho tudo, do início ao fim.', $resposta['textos']['fr']['hero']['texto']);
        $this->assertSame('[ES] Venda a sua casa com tranquilidade.', $resposta['textos']['es']['hero']['titulo']);
        $this->assertSame([], $resposta['pendentes']);

        // As outras mantêm a tradução feita à mão (seeder)
        $this->assertSame($antes['en']['hero']['eyebrow'], $resposta['textos']['en']['hero']['eyebrow']);
        $this->assertSame($antes['fr']['nav']['inicio'], $resposta['textos']['fr']['nav']['inicio']);

        // Um pedido por idioma, só com os 2 textos que mudaram
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $p) => count($p['text']) === 2);

        // O GET público já devolve o novo
        $this->getJson('/api/v1/textos')->assertJsonPath('en.hero.titulo', '[EN-GB] Venda a sua casa com tranquilidade.');
    }

    public function test_gravar_de_novo_sem_mudancas_nao_chama_o_tradutor(): void
    {
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['titulo'] = 'Título novo para o site.';
        $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk();
        Http::assertSentCount(3);

        $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk();
        Http::assertSentCount(3);
    }

    public function test_traducoes_enviadas_pelo_cliente_sao_ignoradas(): void
    {
        $textos = $this->getJson('/api/v1/textos')->json();
        $textos['en']['hero']['eyebrow'] = 'Escrito à mão pelo cliente';

        $this->putJson('/api/v1/textos', $textos)
            ->assertOk()
            ->assertJsonMissing(['eyebrow' => 'Escrito à mão pelo cliente']);
    }

    public function test_variaveis_e_html_sao_preservadas_mesmo_com_espacos_nos_marcadores(): void
    {
        $this->marcadores = 'espacos';
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['port']['verMais'] = 'Ver todos os imóveis ({{n}})';
        $pt['form']['obrigado'] = 'Obrigado, {{nome}}! Até breve.<br>João';
        $pt['card']['quarto_other'] = '{{count}} quartos amplos';

        $en = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos.en');

        $this->assertSame('[EN-GB] Ver todos os imóveis ({{n}})', $en['port']['verMais']);
        $this->assertSame('[EN-GB] Obrigado, {{nome}}! Até breve.<br>João', $en['form']['obrigado']);
        $this->assertSame('[EN-GB] {{count}} quartos amplos', $en['card']['quarto_other']);
    }

    public function test_se_o_tradutor_perde_uma_variavel_fica_o_portugues(): void
    {
        $this->marcadores = 'perder';
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['port']['verMais'] = 'Ver todos os imóveis ({{n}})';
        $pt['hero']['eyebrow'] = 'Consultor em Lisboa';

        $en = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos.en');

        // Com a variável partida o site mostraria "({{n}})" errado: fica o PT
        $this->assertSame('Ver todos os imóveis ({{n}})', $en['port']['verMais']);
        // Textos sem variáveis traduzem normalmente
        $this->assertSame('[EN-GB] Consultor em Lisboa', $en['hero']['eyebrow']);
    }

    public function test_chave_nova_e_traduzida_e_chave_removida_sai_das_traducoes(): void
    {
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['novo'] = 'Texto acabado de criar';
        unset($pt['aria']);

        $textos = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos');

        $this->assertSame('[ES] Texto acabado de criar', $textos['es']['hero']['novo']);
        foreach (['en', 'fr', 'es'] as $idioma) {
            $this->assertArrayNotHasKey('aria', $textos[$idioma], "aria ficou em {$idioma}");
        }
    }

    public function test_grupo_imoveis_das_traducoes_nao_e_apagado(): void
    {
        $antes = $this->getJson('/api/v1/textos')->json();
        $this->assertArrayHasKey('imoveis', $antes['en']);
        $pt = $antes['pt'];
        $pt['hero']['titulo'] = 'Outro título.';

        $textos = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos');

        foreach (['en', 'fr', 'es'] as $idioma) {
            $this->assertSame($antes[$idioma]['imoveis'], $textos[$idioma]['imoveis'], $idioma);
        }
    }

    public function test_texto_deixado_em_branco_fica_vazio_e_nao_null(): void
    {
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['eyebrow'] = '';

        $textos = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos');

        $this->assertSame('', $textos['pt']['hero']['eyebrow']);
        $this->assertSame('', $textos['en']['hero']['eyebrow']);
    }

    public function test_falha_do_tradutor_guarda_o_portugues_mantem_a_traducao_antiga_e_repete_sozinho(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->motorEmBaixo = true;
        $antes = $this->getJson('/api/v1/textos')->json();
        $pt = $antes['pt'];
        $pt['hero']['titulo'] = 'Título escrito com o tradutor em baixo.';

        $resposta = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json();

        $this->assertSame(['en', 'fr', 'es'], $resposta['pendentes']);
        $this->assertSame('Título escrito com o tradutor em baixo.', $resposta['textos']['pt']['hero']['titulo']);
        $this->assertSame($antes['en']['hero']['titulo'], $resposta['textos']['en']['hero']['titulo']);
        Queue::assertPushed(TraduzirTextosSite::class);

        // O tradutor volta e o job completa, sem ninguém fazer nada
        $this->motorEmBaixo = false;
        (new TraduzirTextosSite)->handle(app(TextosSiteService::class));

        $this->getJson('/api/v1/textos')
            ->assertJsonPath('en.hero.titulo', '[EN-GB] Título escrito com o tradutor em baixo.')
            ->assertJsonPath('fr.hero.titulo', '[FR] Título escrito com o tradutor em baixo.');
    }

    public function test_com_fila_sync_a_falha_nao_entra_em_ciclo(): void
    {
        // Regressão: com QUEUE_CONNECTION=sync o job corria no pedido, falhava,
        // agendava-se outra vez e o pedido nunca acabava (20 s no painel)
        config(['queue.default' => 'sync']);
        $this->motorEmBaixo = true;
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['titulo'] = 'Gravado com fila sync e tradutor em baixo.';

        $this->putJson('/api/v1/textos', ['pt' => $pt])
            ->assertOk()
            ->assertJsonPath('pendentes', ['en', 'fr', 'es']);

        // Uma ronda só: um pedido à DeepL por idioma
        Http::assertSentCount(3);
    }

    public function test_job_nao_agenda_outro_job(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->motorEmBaixo = true;
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['titulo'] = 'Outro título.';
        $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk();
        Queue::assertPushed(TraduzirTextosSite::class, 1);

        (new TraduzirTextosSite)->handle(app(TextosSiteService::class));

        Queue::assertPushed(TraduzirTextosSite::class, 1);
    }

    public function test_tradutor_lento_nao_prende_o_painel(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        // Tempo máximo já esgotado: nenhum pedido sai, o PT grava, fica pendente
        config(['services.traducao.orcamento' => -1]);
        $pt = $this->getJson('/api/v1/textos')->json('pt');
        $pt['hero']['titulo'] = 'Gravado com o tradutor lento.';

        $this->putJson('/api/v1/textos', ['pt' => $pt])
            ->assertOk()
            ->assertJsonPath('textos.pt.hero.titulo', 'Gravado com o tradutor lento.')
            ->assertJsonPath('pendentes', ['en', 'fr', 'es']);

        Http::assertNothingSent();
        Queue::assertPushed(TraduzirTextosSite::class);
    }

    public function test_sem_motor_de_traducao_nao_grava_portugues_como_se_fosse_ingles(): void
    {
        config(['services.traducao.driver' => 'nenhum']);
        $antes = $this->getJson('/api/v1/textos')->json();
        $pt = $antes['pt'];
        $pt['hero']['titulo'] = 'Título gravado sem motor.';

        $this->putJson('/api/v1/textos', ['pt' => $pt])
            ->assertOk()
            ->assertJsonPath('pendentes', [])
            ->assertJsonPath('textos.en.hero.titulo', $antes['en']['hero']['titulo']);

        // Quando o motor é ligado, a chave ainda está por traduzir
        config(['services.traducao.driver' => 'deepl']);
        $this->putJson('/api/v1/textos', ['pt' => $pt])
            ->assertJsonPath('textos.en.hero.titulo', '[EN-GB] Título gravado sem motor.');
    }

    public function test_linhas_antigas_sem_fontes_nao_perdem_as_traducoes_do_seeder(): void
    {
        $this->assertNull(TextoSite::query()->find('en')->fontes);
        $pt = $this->getJson('/api/v1/textos')->json('pt');

        // Gravar o PT igual: nada mudou, nada é traduzido, nada se perde
        $textos = $this->putJson('/api/v1/textos', ['pt' => $pt])->assertOk()->json('textos');

        Http::assertNothingSent();
        $this->assertNotNull(TextoSite::query()->find('en')->fontes);
        $this->assertSame(json_decode(file_get_contents(database_path('seeders/dados/fr.json')), true)['hero'], $textos['fr']['hero']);
    }

    /** @param  array<string, mixed>  $corpo */
    #[DataProvider('pedidosInvalidos')]
    public function test_pedido_invalido_tem_erro_claro(array $corpo, string $campo, string $mensagem): void
    {
        $erros = $this->putJson('/api/v1/textos', $corpo)->assertUnprocessable()->json('errors');

        // A chave do erro tem pontos ("pt.hero.titulo"): lê-se literal, não como caminho
        $this->assertSame($mensagem, $erros[$campo][0] ?? null, json_encode($erros, JSON_UNESCAPED_UNICODE));
    }

    public static function pedidosInvalidos(): array
    {
        return [
            'sem português' => [['en' => ['a' => 'b']], 'pt', 'Faltam os textos em português.'],
            'português vazio' => [['pt' => []], 'pt', 'Faltam os textos em português.'],
            'texto que é número' => [['pt' => ['hero' => ['titulo' => 123]]], 'pt.hero.titulo', 'O texto “hero.titulo” tem de ser texto simples.'],
            'texto longo demais' => [['pt' => ['hero' => ['titulo' => str_repeat('a', 2001)]]], 'pt.hero.titulo', 'O texto “hero.titulo” pode ter no máximo 2000 caracteres.'],
        ];
    }

    public function test_sem_sessao_nao_grava(): void
    {
        $this->app['auth']->forgetGuards();

        $this->putJson('/api/v1/textos', ['pt' => ['hero' => ['titulo' => 'x']]])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sessão expirada.');
    }
}
