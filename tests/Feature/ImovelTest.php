<?php

namespace Tests\Feature;

use App\Models\Imovel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImovelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_edita_e_apaga_imovel(): void
    {
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));

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
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->postJson('/api/v1/imoveis', ['titulo' => 'Oi'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_publico_nao_ve_rascunhos_e_admin_ve(): void
    {
        Sanctum::actingAs($admin = User::factory()->create(['perfil' => 'administrador']));
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

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/imoveis')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/imoveis/jd-002')->assertOk()->assertJsonPath('publicado', false);
    }

    public function test_filtra_e_pagina(): void
    {
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));
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
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));
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
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));

        $this->post('/api/v1/imagens', [
            'foto' => UploadedFile::fake()->create('notas.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable();

        $url = $this->post('/api/v1/imagens', [
            'foto' => UploadedFile::fake()->image('fachada.jpg'),
        ])->assertCreated()->json('url');

        $caminho = 'imoveis/'.basename((string) parse_url($url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($caminho);
    }

    public function test_excluir_imovel_com_pedido_preserva_o_contacto(): void
    {
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));
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
            'traducoes' => [],
            'referencia' => 'JD-010',
        ], $extra);
    }
}
