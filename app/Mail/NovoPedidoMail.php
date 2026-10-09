<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NovoPedidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pedido $pedido) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo contacto imobiliário — '.$this->pedido->nome,
            replyTo: [
                new Address($this->pedido->email, $this->pedido->nome),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.novo-pedido',
            with: [
                'pedido' => $this->pedido,
                'quando' => $this->pedido->created_at?->timezone('Europe/Lisbon')->format('d/m/Y \à\s H:i'),
                'site' => rtrim((string) config('leads.frontend_url'), '/'),
            ],
        );
    }
}
