<?php

use App\Models\TextoSite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

/*
| Formulário de contacto: a mensagem passa a ser obrigatória (mínimo 3
| caracteres) e o envio mostra primeiro o agradecimento e só depois pergunta
| se a pessoa quer seguir para o WhatsApp.
|
| Os textos do site vivem na base (o seeder só corre com a base vazia), por
| isso estas chaves são escritas aqui nos 4 idiomas. Nas linhas en/fr/es a
| `fontes` da chave fica com a impressão do novo português, para o tradutor
| não as refazer na próxima gravação do painel.
*/
return new class extends Migration
{
    public const TEXTOS = [
        'pt' => [
            'form.mensagem' => 'Mensagem *',
            'form.mensagemPh' => 'Descreva brevemente o imóvel e o que pretende.',
            'form.erroMensagem' => 'Escreva uma mensagem com pelo menos 3 caracteres.',
            'form.obrigado' => 'Obrigado, {{nome}}! O seu pedido de contacto foi enviado para o meu WhatsApp. Entro em contacto consigo com a maior brevidade. Até já!',
            'form.ok' => 'OK',
            'form.whatsPergunta' => 'Quer seguir já para o WhatsApp?',
            'form.whatsTexto' => 'Abro a conversa com o seu pedido já escrito, pronto a enviar.',
            'form.whatsSim' => 'Sim, abrir o WhatsApp',
            'form.whatsNao' => 'Não, ficar no site',
        ],
        'en' => [
            'form.mensagem' => 'Message *',
            'form.mensagemPh' => 'Briefly describe the property and what you need.',
            'form.erroMensagem' => 'Please write a message with at least 3 characters.',
            'form.obrigado' => 'Thank you, {{nome}}! Your contact request has been sent to my WhatsApp. I will get back to you as soon as possible. Talk soon!',
            'form.ok' => 'OK',
            'form.whatsPergunta' => 'Would you like to go to WhatsApp now?',
            'form.whatsTexto' => 'The chat opens with your request already written, ready to send.',
            'form.whatsSim' => 'Yes, open WhatsApp',
            'form.whatsNao' => 'No, stay on the site',
        ],
        'fr' => [
            'form.mensagem' => 'Message *',
            'form.mensagemPh' => 'Décrivez brièvement le bien et votre projet.',
            'form.erroMensagem' => 'Écrivez un message d\'au moins 3 caractères.',
            'form.obrigado' => 'Merci, {{nome}} ! Votre demande de contact a été envoyée sur mon WhatsApp. Je vous réponds dans les meilleurs délais. À bientôt !',
            'form.ok' => 'OK',
            'form.whatsPergunta' => 'Voulez-vous passer sur WhatsApp maintenant ?',
            'form.whatsTexto' => 'La conversation s\'ouvre avec votre demande déjà rédigée, prête à être envoyée.',
            'form.whatsSim' => 'Oui, ouvrir WhatsApp',
            'form.whatsNao' => 'Non, rester sur le site',
        ],
        'es' => [
            'form.mensagem' => 'Mensaje *',
            'form.mensagemPh' => 'Describa brevemente el inmueble y lo que necesita.',
            'form.erroMensagem' => 'Escriba un mensaje de al menos 3 caracteres.',
            'form.obrigado' => '¡Gracias, {{nome}}! Su solicitud de contacto se ha enviado a mi WhatsApp. Me pondré en contacto con usted lo antes posible. ¡Hasta pronto!',
            'form.ok' => 'OK',
            'form.whatsPergunta' => '¿Quiere ir ya a WhatsApp?',
            'form.whatsTexto' => 'La conversación se abre con su solicitud ya escrita, lista para enviar.',
            'form.whatsSim' => 'Sí, abrir WhatsApp',
            'form.whatsNao' => 'No, quedarme en el sitio',
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTOS as $locale => $textos) {
            $linha = TextoSite::query()->find($locale);
            if ($linha === null) {
                continue; // base vazia: o seeder já traz estes textos
            }

            $conteudo = $linha->conteudo ?? [];
            foreach ($textos as $chave => $texto) {
                Arr::set($conteudo, $chave, $texto);
            }
            $linha->conteudo = $conteudo;

            if ($locale !== 'pt' && is_array($linha->fontes)) {
                $fontes = $linha->fontes;
                foreach (array_keys($textos) as $chave) {
                    $fontes[$chave] = md5(self::TEXTOS['pt'][$chave]);
                }
                $linha->fontes = $fontes;
            }

            $linha->save();
        }
    }

    public function down(): void
    {
        // Textos editáveis no painel: não há o que repor.
    }
};
