<?php

namespace Tests\Feature;

use App\Jobs\TraduzirImovel;
use App\Models\Imovel;
use App\Models\User;
use App\Services\ImovelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ImovelTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    public function test_cria_edita_e_apaga_imovel(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $criado = $this->postJson('/api/v1/imoveis', $this->dados())->assertCreated();
        $id = $criado->json('id');

        $this->assertSame('JD-010', $criado->json('referencia'));
        $this->assertSame('imoveis/capa.jpg', $criado->json('imagens.0'));

        $this->putJson('/api/v1/imoveis/'.$id, $this->dados([
            'titulo' => 'Moradia com jardim amplo',
            'referencia' => 'JD-010',
        ]))->assertOk()->assertJsonPath('titulo', 'Moradia com jardim amplo');

        $this->deleteJson('/api/v1/imoveis/'.$id)->assertNoContent();
        $this->assertSoftDeleted('imoveis', ['id' => $id]);
    }

    public function test_recusa_campos_obrigatorios(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', ['titulo' => 'Oi'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_publico_nao_ve_rascunhos_e_admin_ve(): void
    {
        Passport::actingAs($admin = User::factory()->create(['perfil' => 'administrador']));
        $this->postJson('/api/v1/imoveis', $this->dados(['publicado' => true, 'referencia' => 'JD-001']))->assertCreated();
        $this->postJson('/api/v1/imoveis', $this->dados([
            'publicado' => false,
            'referencia' => 'JD-002',
            'titulo' => 'Rascunho ainda invisível',
            'imagens' => [],
        ]))->assertCreated();

        $this->app['auth']->forgetGuards();

        $publico = $this->getJson('/api/v1/imoveis')->assertOk()->json();
        $this->assertCount(1, $publico);
        $this->assertSame('JD-001', $publico[0]['referencia']);

        $this->getJson('/api/v1/imoveis/jd-002')->assertNotFound();

        Passport::actingAs($admin);
        $this->getJson('/api/v1/imoveis')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/imoveis/jd-002')->assertOk()->assertJsonPath('publicado', false);
    }

    public function test_filtra_e_pagina(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $this->postJson('/api/v1/imoveis', $this->dados(['referencia' => 'JD-001', 'zona' => 'Benfica, Lisboa', 'preco' => 200000]));
        $this->postJson('/api/v1/imoveis', $this->dados(['referencia' => 'JD-002', 'zona' => 'Cascais', 'preco' => 900000, 'tipo' => 'Moradia']));

        $this->getJson('/api/v1/imoveis?q=Cascais')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.zona', 'Cascais');

        $pagina = $this->getJson('/api/v1/imoveis?paginar=1&per_page=1')->assertOk()->json();
        $this->assertCount(1, $pagina['data']);
        $this->assertSame(2, $pagina['meta']['total']);
    }

    public function test_publicar_exige_foto_e_destaque_so_lista_publicados(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $id = $this->postJson('/api/v1/imoveis', $this->dados([
            'publicado' => false,
            'imagens' => [],
            'referencia' => 'JD-003',
        ]))->json('id');

        $this->patchJson('/api/v1/imoveis/'.$id, ['publicado' => true])->assertUnprocessable();

        $this->putJson('/api/v1/imoveis/'.$id, $this->dados([
            'referencia' => 'JD-003',
            'publicado' => true,
            'destaque' => true,
            'imagens' => ['imoveis/capa.jpg'],
        ]))->assertOk();

        $this->getJson('/api/v1/imoveis/destaque')->assertOk()->assertJsonPath('0.referencia', 'JD-003');
    }

    public function test_upload_rejeita_ficheiro_invalido_e_aceita_imagem(): void
    {
        Storage::fake('public');
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->post('/api/v1/imagens', [
            'foto' => UploadedFile::fake()->create('notas.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable();

        $url = $this->post('/api/v1/imagens', [
            // JPEG 1x1 real: não precisa da extensão GD (ausente na imagem Docker)
            'foto' => UploadedFile::fake()->createWithContent('fachada.jpg', base64_decode(self::JPEG_1X1)),
        ])->assertCreated()->json('url');

        $caminho = 'imoveis/'.basename((string) parse_url($url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($caminho);
    }

    public function test_excluir_imovel_com_pedido_preserva_o_contacto(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $id = $this->postJson('/api/v1/imoveis', $this->dados(['referencia' => 'JD-004']))->json('id');

        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
            'imovelId' => $id,
        ])->assertCreated();

        $this->deleteJson('/api/v1/imoveis/'.$id)->assertNoContent();
        $this->assertSoftDeleted('imoveis', ['id' => $id]);
        $this->assertDatabaseHas('pedidos', ['imovel_id' => $id, 'email' => 'maria@email.pt']);
        $this->assertNull(Imovel::query()->find($id));
    }

    public function test_referencia_pode_repetir_se(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $a = $this->postJson('/api/v1/imoveis', $this->dados(['referencia' => 'JD-020']))->assertCreated();
        $b = $this->postJson('/api/v1/imoveis', $this->dados(['referencia' => 'jd-020']))->assertCreated();

        $this->assertSame('JD-020', $b->json('referencia'));
        $this->assertNotSame($a->json('id'), $b->json('id'));
    }

    public function test_erros_de_validacao_vem_por_campo_em_portugues(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', $this->dados(['preco' => 0, 'descricao' => 'curta', 'referencia' => 'JD 1']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.preco.0', 'O preço tem de ser maior que zero.')
            ->assertJsonPath('errors.descricao.0', 'A descrição precisa de pelo menos 20 caracteres.')
            ->assertJsonPath('errors.referencia.0', 'A referência só pode ter letras, números e hífen, entre 2 e 20 caracteres (ex.: JD-006).');
    }

    public function test_destaques_em_branco_sao_ignorados(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', $this->dados(['destaques' => ['Varanda', '', '  ', 'Garagem']]))
            ->assertCreated()
            ->assertJsonPath('destaques', ['Varanda', 'Garagem']);
    }

    public function test_traduz_automaticamente_ao_gravar_com_deepl(): void
    {
        config(['services.traducao.driver' => 'deepl', 'services.traducao.deepl_key' => 'chave:fx']);
        Http::fake([
            'api-free.deepl.com/*' => fn ($pedido) => Http::response([
                'translations' => array_map(
                    fn ($t) => ['text' => '['.$pedido['target_lang'].'] '.$t],
                    $pedido['text'],
                ),
            ]),
        ]);
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $criado = $this->postJson('/api/v1/imoveis', $this->dados())->assertCreated();

        $criado->assertJsonPath('traducoes.en.titulo', '[EN-GB] Apartamento em Benfica')
            ->assertJsonPath('traducoes.fr.resumo', '[FR] Apartamento luminoso com varanda.')
            ->assertJsonPath('traducoes.es.destaques', ['[ES] Varanda']);
        Http::assertSentCount(3);

        // Editar sem mudar os textos não volta a chamar o tradutor
        $this->putJson('/api/v1/imoveis/'.$criado->json('id'), $this->dados(['preco' => 300000]))->assertOk();
        Http::assertSentCount(3);

        // Mudar o título volta a traduzir
        $this->putJson('/api/v1/imoveis/'.$criado->json('id'), $this->dados(['titulo' => 'Moradia em Cascais']))
            ->assertOk()
            ->assertJsonPath('traducoes.en.titulo', '[EN-GB] Moradia em Cascais');
    }

    public function test_traduz_com_google_sem_chave(): void
    {
        config(['services.traducao.driver' => 'google']);
        Http::fake([
            'translate.googleapis.com/*' => fn ($pedido) => Http::response([[['('.$this->idiomaDoUrl($pedido->url()).') '.$pedido['q'], $pedido['q']]]]),
        ]);
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', $this->dados())
            ->assertCreated()
            ->assertJsonPath('traducoes.fr.titulo', '(fr) Apartamento em Benfica');
    }

    public function test_falha_na_traducao_nao_impede_de_gravar(): void
    {
        config(['services.traducao.driver' => 'deepl', 'services.traducao.deepl_key' => 'chave:fx']);
        Http::fake(['*' => Http::response('erro', 503)]);
        Queue::fake();
        config(['queue.default' => 'database']);
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $id = $this->postJson('/api/v1/imoveis', $this->dados())
            ->assertCreated()
            ->assertJsonPath('titulo', 'Apartamento em Benfica')
            ->assertJsonPath('traducoes', [])
            ->json('id');

        // A nova tentativa fica na fila, sem ninguém correr comandos
        Queue::assertPushed(TraduzirImovel::class, fn ($job) => $job->imovelId === $id);
    }

    public function test_com_fila_sync_nao_agenda_repeticao_da_traducao(): void
    {
        config(['services.traducao.driver' => 'deepl', 'services.traducao.deepl_key' => 'chave:fx', 'queue.default' => 'sync']);
        Http::fake(['*' => Http::response('erro', 503)]);
        Queue::fake();
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', $this->dados())->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_job_completa_a_traducao_quando_o_motor_volta(): void
    {
        config(['services.traducao.driver' => 'deepl', 'services.traducao.deepl_key' => 'chave:fx']);
        $motorLigado = false;
        Http::fake([
            'api-free.deepl.com/*' => function ($pedido) use (&$motorLigado) {
                if (! $motorLigado) {
                    return Http::response('erro', 503);
                }

                return Http::response([
                    'translations' => array_map(fn ($t) => ['text' => '['.$pedido['target_lang'].'] '.$t], $pedido['text']),
                ]);
            },
        ]);
        Queue::fake();
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $id = $this->postJson('/api/v1/imoveis', $this->dados())->json('id');
        $this->assertSame([], Imovel::query()->find($id)->traducoes);

        // O motor volta: o job traduz e grava os 3 idiomas
        $motorLigado = true;
        (new TraduzirImovel($id))->handle(app(ImovelService::class));

        $this->assertSame(['en', 'fr', 'es'], array_keys(Imovel::query()->find($id)->traducoes));
    }

    public function test_foto_retirada_so_e_apagada_depois_de_gravar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('imoveis/velha.jpg', 'x');
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $velha = Storage::disk('public')->url('imoveis/velha.jpg');

        $id = $this->postJson('/api/v1/imoveis', $this->dados(['imagens' => [$velha]]))->json('id');

        // Pedido inválido: nada muda, a foto continua no disco
        $this->putJson('/api/v1/imoveis/'.$id, $this->dados(['imagens' => ['imoveis/nova.jpg'], 'preco' => 0]))->assertUnprocessable();
        Storage::disk('public')->assertExists('imoveis/velha.jpg');

        $this->putJson('/api/v1/imoveis/'.$id, $this->dados(['imagens' => ['imoveis/nova.jpg']]))->assertOk();
        Storage::disk('public')->assertMissing('imoveis/velha.jpg');
    }

    private function idiomaDoUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return (string) $q['tl'];
    }

    /** @param  array<string, mixed>  $extra */
    private function dados(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Apartamento em Benfica',
            'tipologia' => 'T2',
            'tipo' => 'Apartamento',
            'zona' => 'Benfica, Lisboa',
            'preco' => 285000,
            'area' => 78,
            'quartos' => 2,
            'casasBanho' => 1,
            'estado' => 'Disponível',
            'resumo' => 'Apartamento luminoso com varanda.',
            'descricao' => 'Descrição completa do imóvel com mais de vinte caracteres.',
            'destaques' => ['Varanda'],
            'imagens' => ['imoveis/capa.jpg'],
            'publicado' => true,
            'referencia' => 'JD-010',
        ], $extra);
    }
}
