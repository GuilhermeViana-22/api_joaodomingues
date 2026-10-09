<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo contacto imobiliário</title>
</head>
<body style="margin:0;padding:0;background:#f4f1ea;font-family:Georgia,'Times New Roman',serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e6e0d4;">
                    <tr>
                        <td style="background:#0A0A0A;padding:28px 32px;">
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#c9a227;">João Domingues</p>
                            <h1 style="margin:8px 0 0;font-size:22px;line-height:1.3;font-weight:normal;color:#ffffff;">Novo contacto imobiliário</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1a1a1a;">
                            <p style="margin:0 0 16px;">Recebeu um pedido pelo formulário do site.</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;width:140px;color:#6b6560;">Nome</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->nome }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">E-mail</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;"><a href="mailto:{{ $pedido->email }}" style="color:#1a1a1a;">{{ $pedido->email }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Telemóvel</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->telefone ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Pretende</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->objetivo ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Tipo de imóvel</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->tipo_imovel ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Tipologia</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->tipologia ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Localização</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->zona ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Prazo</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->prazo ?: '—' }}</td>
                                </tr>
                                @if ($pedido->imovel)
                                    <tr>
                                        <td style="padding:8px 0;border-top:1px solid #eee;color:#6b6560;">Imóvel</td>
                                        <td style="padding:8px 0;border-top:1px solid #eee;">{{ $pedido->imovel->referencia }} — {{ $pedido->imovel->titulo }} ({{ $pedido->imovel->zona }})</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:8px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;color:#6b6560;">Recebido</td>
                                    <td style="padding:8px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;">{{ $quando }}</td>
                                </tr>
                            </table>
                            @if ($pedido->mensagem)
                                <p style="margin:20px 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#6b6560;">Mensagem</p>
                                <p style="margin:0;white-space:pre-wrap;">{{ $pedido->mensagem }}</p>
                            @endif
                            <p style="margin:24px 0 0;">
                                <a href="{{ $site }}" style="color:#8d6810;">Abrir o site</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 28px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b6560;">
                            Responda a este e-mail para escrever directamente ao interessado. O remetente técnico é a conta configurada no servidor, não o visitante.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
