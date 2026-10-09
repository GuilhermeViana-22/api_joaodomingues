<?php

namespace App\Jobs;

use App\Mail\NovoPedidoMail;
use App\Models\Pedido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificarNovoPedido implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $pedidoId) {}

    public function handle(): void
    {
        $pedido = Pedido::query()->with('imovel')->find($this->pedidoId);
        if ($pedido === null) {
            return;
        }

        try {
            Mail::to(config('leads.notificacao'))->send(new NovoPedidoMail($pedido));
            $pedido->forceFill(['email_estado' => 'enviado'])->save();
        } catch (Throwable $e) {
            $pedido->forceFill(['email_estado' => 'falhou'])->save();
            Log::error('Falha ao notificar lead.', [
                'pedido' => $pedido->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
