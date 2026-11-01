<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FotoSobreTest extends TestCase
{
    use RefreshDatabase;

    /** PNG válido de W×H (os testes correm sem GD). */
    private function png(int $largura, int $altura): UploadedFile
    {
        $bloco = fn (string $tipo, string $dados) => pack('N', strlen($dados)).$tipo.$dados.pack('N', crc32($tipo.$dados));
        $linhas = str_repeat("\0".str_repeat("\x80\x80\x80", $largura), $altura);
        $conteudo = "\x89PNG\r\n\x1a\n"
            .$bloco('IHDR', pack('NNCCCCC', $largura, $altura, 8, 2, 0, 0, 0))
            .$bloco('IDAT', gzcompress($linhas))
            .$bloco('IEND', '');

        return UploadedFile::fake()->createWithContent('joao.png', $conteudo);
    }

    public function test_site_le_a_foto_e_sem_foto_vem_null(): void
    {
        $this->getJson('/api/v1/definicoes')->assertOk()->assertJsonPath('fotoSobre', null);
    }

    public function test_editor_troca_a_foto_e_a_antiga_sai_do_disco(): void
    {
        Storage::fake('public');
        Passport::actingAs(User::factory()->create(['perfil' => 'editor']));

        $primeira = $this->post('/api/v1/definicoes/foto-sobre', ['foto' => $this->png(880, 1080)], ['Accept' => 'application/json'])
            ->assertCreated()->json('fotoSobre');
        $this->getJson('/api/v1/definicoes')->assertJsonPath('fotoSobre', $primeira);

        $segunda = $this->post('/api/v1/definicoes/foto-sobre', ['foto' => $this->png(880, 1080)], ['Accept' => 'application/json'])
            ->assertCreated()->json('fotoSobre');

        $this->assertNotSame($primeira, $segunda);
        $this->assertCount(1, Storage::disk('public')->files('site'));

        $this->deleteJson('/api/v1/definicoes/foto-sobre')->assertOk()->assertJsonPath('fotoSobre', null);
        $this->assertCount(0, Storage::disk('public')->files('site'));
        $this->getJson('/api/v1/definicoes')->assertJsonPath('fotoSobre', null);
    }

    public function test_recusa_foto_fora_do_formato_do_site(): void
    {
        Storage::fake('public');
        Passport::actingAs(User::factory()->create(['perfil' => 'editor']));

        $this->post('/api/v1/definicoes/foto-sobre', ['foto' => $this->png(1080, 1080)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.foto.0', 'A foto tem de ter 880×1080 px. Recorte-a no painel antes de enviar.');
    }

    public function test_visitante_nao_troca_a_foto(): void
    {
        $this->postJson('/api/v1/definicoes/foto-sobre')->assertUnauthorized();
        $this->deleteJson('/api/v1/definicoes/foto-sobre')->assertUnauthorized();
    }
}
