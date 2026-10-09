<?php

namespace App\Services;

use App\Enums\EstadoPedido;
use App\Jobs\NotificarNovoPedido;
use App\Models\Imovel;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Str;

class PedidoService
{
    public function receber(array $dados): Pedido
    {
        $imovelId = $this->imovelExistente($dados['imovelId'] ?? null);
        $agora = now()->toIso8601String();

        $pedido = Pedido::query()->create([
            'id' => 'pd-'.Str::lower((string) Str::ulid()),
            'nome' => $dados['nome'],
            'email' => $dados['email'],
            'telefone' => $dados['telefone'] ?? null,
            'objetivo' => $dados['objetivo'] ?? null,
            'tipo_imovel' => $dados['tipoImovel'] ?? null,
            'tipologia' => $dados['tipologia'] ?? null,
            'zona' => $dados['zona'] ?? null,
            'prazo' => $dados['prazo'] ?? null,
            'mensagem' => $dados['mensagem'] ?? null,
            'imovel_id' => $imovelId,
            'status' => EstadoPedido::Novo->value,
            'historico' => [[
                'em' => $agora,
                'descricao' => 'Pedido recebido pelo formulário do site',
            ]],
            'origem' => 'site',
            'consentimento' => $this->consentiu($dados['consentimento'] ?? null),
            'email_estado' => 'pendente',
        ]);

        // Depois do INSERT. Uma falha de SMTP fica no job e não desfaz o contacto.
        NotificarNovoPedido::dispatch($pedido->id)->afterCommit();

        return $pedido;
    }

    public function actualizar(Pedido $pedido, User $utilizador, string $status, ?string $observacoes): Pedido
    {
        $historico = $pedido->historico ?? [];

        if ($pedido->status !== $status) {
            $rotulo = EstadoPedido::from($status)->rotulo();
            $historico[] = [
                'em' => now()->toIso8601String(),
                'descricao' => $status === EstadoPedido::Visualizado->value
                    ? 'Pedido visualizado'
                    : 'Estado alterado para '.$rotulo,
                'por' => $utilizador->name,
            ];
            $pedido->status = $status;
        }

        if ($observacoes !== null && trim($observacoes) !== '') {
            $pedido->observacoes = trim($observacoes);
            $historico[] = [
                'em' => now()->toIso8601String(),
                'descricao' => trim($observacoes),
                'por' => $utilizador->name,
            ];
        }

        $pedido->historico = $historico;
        $pedido->save();

        return $pedido->load('imovel');
    }

    private function imovelExistente(mixed $id): ?string
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        return Imovel::query()->whereKey($id)->exists() ? $id : null;
    }

    private function consentiu(mixed $valor): bool
    {
        return in_array($valor, [true, 1, '1', 'sim', 'true', 'on'], true);
    }
}
