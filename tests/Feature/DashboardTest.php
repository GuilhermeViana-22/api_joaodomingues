<?php

namespace Tests\Feature;

use App\Models\Imovel;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calcula_os_indicadores_no_backend(): void
    {
        Passport::actingAs(User::factory()->create([
            'perfil' => 'administrador',
            'ativo' => true,
        ]));
        User::factory()->create(['perfil' => 'editor', 'ativo' => false]);

        $publicado = $this->criarImovel('JD-010', true);
        $rascunho = $this->criarImovel('JD-011', false);
        Imovel::query()->whereKey($rascunho)->update(['created_at' => now()->subMonth()]);

        $this->postJson('/api/v1/pedidos', [
            'nome' => 'Maria Silva',
            'email' => 'maria@email.pt',
            'objetivo' => 'Vender o meu imóvel',
            'tipoImovel' => 'Apartamento',
            'imovelId' => $publicado,
        ])->assertCreated();

        $antigo = $this->postJson('/api/v1/pedidos', [
            'nome' => 'Pedro Antunes',
            'email' => 'pedro@email.pt',
        ])->assertCreated()->json('id');
        Pedido::query()->whereKey($antigo)->update([
            'status' => 'visualizado',
            'created_at' => now()->subMonth(),
        ]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('imoveis.total', 2)
            ->assertJsonPath('imoveis.publicados', 1)
            ->assertJsonPath('imoveis.rascunhos', 1)
            ->assertJsonPath('imoveis.novosEsteMes', 1)
            ->assertJsonPath('pedidos.total', 2)
            ->assertJsonPath('pedidos.novos', 1)
            ->assertJsonPath('pedidos.novosEsteMes', 1)
            ->assertJsonPath('pedidos.recentes.0.email', 'maria@email.pt')
            ->assertJsonPath('pedidos.recentes.0.tipoImovel', 'Apartamento')
            ->assertJsonPath('utilizadores.total', 2)
            ->assertJsonPath('utilizadores.ativos', 1);
    }

    private function criarImovel(string $referencia, bool $publicado): string
    {
        $resposta = $this->postJson('/api/v1/imoveis', [
            'titulo' => 'Apartamento '.$referencia,
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
            'imagens' => $publicado ? ['imoveis/capa.jpg'] : [],
            'publicado' => $publicado,
            'referencia' => $referencia,
        ])->assertCreated();

        return $resposta->json('id');
    }
}
