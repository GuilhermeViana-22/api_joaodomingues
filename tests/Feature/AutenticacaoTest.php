<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_devolve_token_e_utilizador(): void
    {
        $user = User::factory()->create([
            'email' => 'joao@joaodomingues.pt',
            'perfil' => 'administrador',
            'password' => 'senha-segura',
        ]);

        $resposta = $this->postJson('/api/v1/auth/login', [
            'email' => 'joao@joaodomingues.pt',
            'senha' => 'senha-segura',
        ]);

        $resposta->assertOk()
            ->assertJsonPath('utilizador.email', 'joao@joaodomingues.pt')
            ->assertJsonPath('utilizador.nome', $user->name)
            ->assertJsonStructure(['token', 'utilizador' => ['id', 'perfil', 'ativo']]);

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'revoked' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$resposta->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'joao@joaodomingues.pt');
    }

    public function test_login_recusa_senha_errada_e_conta_inactiva(): void
    {
        User::factory()->create([
            'email' => 'ana@joaodomingues.pt',
            'password' => 'senha-segura',
            'ativo' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@joaodomingues.pt',
            'senha' => 'outra-senha',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@joaodomingues.pt',
            'senha' => 'senha-segura',
        ])->assertForbidden();
    }

    public function test_rotas_administrativas_exigem_sessao(): void
    {
        $this->getJson('/api/v1/pedidos')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->postJson('/api/v1/imoveis', [])->assertUnauthorized();
    }

    public function test_editor_nao_gere_utilizadores(): void
    {
        Passport::actingAs(User::factory()->create(['perfil' => 'editor']));

        $this->getJson('/api/v1/utilizadores')->assertForbidden();
        $this->putJson('/api/v1/definicoes', [])->assertForbidden();
    }

    public function test_logout_revoga_o_token(): void
    {
        $user = User::factory()->create(['perfil' => 'administrador']);
        $token = $user->createToken('painel')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'revoked' => true,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_comando_cria_administrador_e_recusa_senha_fraca(): void
    {
        $this->artisan('admin:criar', [
            '--nome' => 'João Domingues',
            '--email' => 'joao@joaodomingues.pt',
            '--senha' => 'admin123',
        ])->assertFailed();

        $this->artisan('admin:criar', [
            '--nome' => 'João Domingues',
            '--email' => 'joao@joaodomingues.pt',
            '--senha' => 'senha-segura',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'joao@joaodomingues.pt',
            'perfil' => 'administrador',
        ]);
    }
}
