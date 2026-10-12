<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * O cadastro de imóveis como o painel o faz, passo a passo.
 *
 * O painel guarda a resposta da API no formulário e reenvia-a na gravação
 * seguinte. Por isso o teste mais importante aqui é o "ida e volta": o que a
 * API devolve tem de ser aceite de volta, sem mexer em nada. Foi isso que
 * falhou (finalidade/moeda a null depois do insert → 422 "validation.in").
 */
class CadastroImovelFluxoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
    }

    public function test_sequencia_completa_criar_editar_publicar_excluir(): void
    {
        // 1. Novo imóvel, só com o que o formulário do painel envia
        $criado = $this->postJson('/api/v1/imoveis', $this->novo())->assertCreated()->json();

        // 2. A resposta já traz os valores por defeito da base, nunca null
        $this->assertSame('venda', $criado['finalidade']);
        $this->assertSame('EUR', $criado['moeda']);
        $this->assertSame('Portugal', $criado['pais']);
        $this->assertFalse($criado['destaque']);
        $this->assertSame(0, $criado['ordem']);

        // 3. Ida e volta: reenviar a resposta tal como veio é aceite
        $editado = $this->putJson('/api/v1/imoveis/'.$criado['id'], $criado)->assertOk()->json();

        // 4. Segunda edição, agora a mudar campos, a partir da resposta anterior
        $editado['preco'] = 310000;
        $editado['titulo'] = 'Apartamento T2 com terraço';
        $editado['destaques'] = ['Terraço', '', 'Garagem'];
        $editado = $this->putJson('/api/v1/imoveis/'.$criado['id'], $editado)
            ->assertOk()
            ->assertJsonPath('preco', 310000)
            ->assertJsonPath('destaques', ['Terraço', 'Garagem'])
            ->json();

        // 5. Publicar sem foto é recusado com frase clara; com foto passa
        $this->patchJson('/api/v1/imoveis/'.$criado['id'], ['publicado' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.imagens.0', 'Adicione pelo menos a foto de destaque antes de publicar.');

        $editado['imagens'] = ['imoveis/capa.jpg'];
        $this->putJson('/api/v1/imoveis/'.$criado['id'], $editado)->assertOk();
        $this->patchJson('/api/v1/imoveis/'.$criado['id'], ['publicado' => true])
            ->assertOk()
            ->assertJsonPath('publicado', true);

        // 6. O site público vê-o
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/imoveis')->assertOk()->assertJsonPath('0.id', $criado['id']);

        // 7. Excluir
        Passport::actingAs(User::factory()->create(['perfil' => 'administrador']));
        $this->deleteJson('/api/v1/imoveis/'.$criado['id'])->assertNoContent();
        $this->getJson('/api/v1/imoveis/'.$criado['id'])->assertNotFound()->assertJsonPath('message', 'O registo já não existe.');
    }

    public function test_campos_que_o_painel_nao_edita_a_null_nao_bloqueiam(): void
    {
        $id = $this->postJson('/api/v1/imoveis', $this->novo())->json('id');

        // Como o painel enviava antes da correção (resposta antiga com nulls)
        $this->putJson('/api/v1/imoveis/'.$id, [
            ...$this->novo(),
            'finalidade' => null,
            'moeda' => null,
            'pais' => null,
            'destaque' => null,
            'ordem' => null,
            'areaTotal' => null,
            'vagas' => null,
            'metaTitulo' => null,
        ])
            ->assertOk()
            ->assertJsonPath('finalidade', 'venda')
            ->assertJsonPath('moeda', 'EUR')
            ->assertJsonPath('pais', 'Portugal');
    }

    public function test_varios_imoveis_seguidos_com_referencia_vazia_e_repetida(): void
    {
        $a = $this->postJson('/api/v1/imoveis', $this->novo(['referencia' => '']))->assertCreated()->json();
        $b = $this->postJson('/api/v1/imoveis', $this->novo(['referencia' => '']))->assertCreated()->json();
        $c = $this->postJson('/api/v1/imoveis', $this->novo(['referencia' => $a['referencia']]))->assertCreated()->json();

        $this->assertNotSame($a['referencia'], $b['referencia']);
        $this->assertSame($a['referencia'], $c['referencia']);
        $this->assertCount(3, array_unique([$a['id'], $b['id'], $c['id']]));
    }

    /**
     * Nenhuma resposta de erro pode trazer a chave crua do Laravel
     * ("validation.in"): tem de ser sempre uma frase em português.
     *
     * @param  array<string, mixed>  $alteracoes
     */
    #[DataProvider('dadosInvalidos')]
    public function test_erros_sao_sempre_frases_em_portugues(array $alteracoes, string $campo): void
    {
        $resposta = $this->postJson('/api/v1/imoveis', $this->novo($alteracoes))->assertUnprocessable();

        $this->assertArrayHasKey($campo, $resposta->json('errors'), "Esperava erro em {$campo}.");
        $mensagens = [$resposta->json('message'), ...collect($resposta->json('errors'))->flatten()->all()];
        foreach ($mensagens as $mensagem) {
            $this->assertStringNotContainsString('validation.', $mensagem, "Mensagem crua: {$mensagem}");
        }
    }

    public static function dadosInvalidos(): array
    {
        return [
            'título vazio' => [['titulo' => ''], 'titulo'],
            'título curto' => [['titulo' => 'Oi'], 'titulo'],
            'preço zero' => [['preco' => 0], 'preco'],
            'preço com texto' => [['preco' => 'muito'], 'preco'],
            'preço decimal' => [['preco' => 1500.5], 'preco'],
            'área zero' => [['area' => 0], 'area'],
            'quartos negativos' => [['quartos' => -1], 'quartos'],
            'casas de banho a mais' => [['casasBanho' => 99], 'casasBanho'],
            'zona vazia' => [['zona' => ''], 'zona'],
            'resumo vazio' => [['resumo' => ''], 'resumo'],
            'descrição curta' => [['descricao' => 'curta'], 'descricao'],
            'referência com espaço' => [['referencia' => 'JD 1'], 'referencia'],
            'tipo desconhecido' => [['tipo' => 'Castelo'], 'tipo'],
            'estado desconhecido' => [['estado' => 'Alugado'], 'estado'],
            'tipologia vazia' => [['tipologia' => ''], 'tipologia'],
            'sete fotos' => [['imagens' => array_fill(0, 7, 'imoveis/x.jpg')], 'imagens'],
            'publicar sem foto' => [['publicado' => true, 'imagens' => []], 'imagens'],
            'finalidade desconhecida' => [['finalidade' => 'troca'], 'finalidade'],
            'moeda longa' => [['moeda' => 'EUROS'], 'moeda'],
            'destaque longo' => [['destaques' => [str_repeat('a', 200)]], 'destaques.0'],
        ];
    }

    public function test_sem_sessao_e_editar_inexistente_dao_frase_clara(): void
    {
        $this->putJson('/api/v1/imoveis/nao-existe', $this->novo())
            ->assertNotFound()
            ->assertJsonPath('message', 'O registo já não existe.');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/imoveis', $this->novo())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Sessão expirada.');
    }

    /**
     * O que o formulário do painel envia para um imóvel novo
     * (admin/src/lib/imovelPayload.ts).
     *
     * @param  array<string, mixed>  $extra
     */
    private function novo(array $extra = []): array
    {
        return array_merge([
            'referencia' => 'JD-050',
            'titulo' => 'Apartamento T2 em Benfica',
            'tipologia' => 'T2',
            'tipo' => 'Apartamento',
            'zona' => 'Benfica, Lisboa',
            'preco' => 285000,
            'area' => 78,
            'quartos' => 2,
            'casasBanho' => 1,
            'estado' => 'Disponível',
            'resumo' => 'Luminoso, renovado e perto do metro.',
            'descricao' => 'Apartamento remodelado, com cozinha equipada e varanda.',
            'destaques' => ['Varanda'],
            'imagens' => [],
            'publicado' => false,
        ], $extra);
    }
}
