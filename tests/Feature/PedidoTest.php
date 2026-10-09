<?php

namespace Tests\Feature;

use App\Mail\NovoPedidoMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_recebe_pedido_e_envia_email(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $id = $this->postJson('/api/v1/imoveis', [
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
            'imagens' => ['imoveis/capa.jpg'],
            'publicado' => true,
            'referencia' => 'JD-001',
        ])->json('id');

        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
            'telefone' => '+351 910 000 000',
            'mensagem' => 'Gostava de vender.',
            'imovelId' => $id,
            'consentimento' => 'sim',
        ])->assertCreated()->assertJsonStructure(['id', 'message']);

        $this->assertDatabaseHas('pedidos', [
            'email' => 'maria@email.pt',
            'imovel_id' => $id,
            'status' => 'novo',
            'email_estado' => 'enviado',
        ]);

        Mail::assertSent(NovoPedidoMail::class, function (NovoPedidoMail $mail) {
            return $mail->hasTo('jmdomingues@remax.pt')
                && $mail->pedido->email === 'maria@email.pt';
        });
    }

    public function test_pedido_fica_gravado_quando_o_email_falha(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP em baixo'));

        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
        ])->assertCreated();

        $this->assertDatabaseHas('pedidos', [
            'email' => 'maria@email.pt',
            'email_estado' => 'falhou',
        ]);
    }

    public function test_rejeita_dados_invalidos_e_honeypot(): void
    {
        $this->postJson('/api/v1/pedidos', ['nome' => 'A'])->assertUnprocessable();

        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
            'website' => 'http://spam.test',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_admin_lista_e_actualiza_estado_e_visitante_nao_le(): void
    {
        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
        ])->assertCreated();

        $this->getJson('/api/v1/pedidos')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['perfil' => 'editor']));

        $id = $this->getJson('/api/v1/pedidos?q=maria@email.pt')->assertOk()->json('0.id');

        $this->patchJson('/api/v1/pedidos/'.$id, [
            'status' => 'em-contacto',
            'observacoes' => 'Ligar amanhã de manhã.',
        ])->assertOk()->assertJsonPath('status', 'em-contacto');

        $this->assertDatabaseHas('pedidos', ['id' => $id, 'status' => 'em-contacto']);
    }

    public function test_textos_publicos_e_gravacao_protegida(): void
    {
        $this->seed();

        $this->getJson('/api/v1/textos')
            ->assertOk()
            ->assertJsonPath('pt.hero.eyebrow', 'Consultor imobiliário');

        $this->putJson('/api/v1/textos', [])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['perfil' => 'editor']));

        $textos = $this->getJson('/api/v1/textos')->json();
        $textos['pt']['hero']['eyebrow'] = 'Consultor em Lisboa';

        $this->putJson('/api/v1/textos', $textos)
            ->assertOk()
            ->assertJsonPath('pt.hero.eyebrow', 'Consultor em Lisboa');
    }
}
