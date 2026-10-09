<?php

return [

    /*
    | Destinatário das notificações de contacto. O visitante nunca é o remetente.
    */
    'notificacao' => env('LEADS_NOTIFICATION_EMAIL', 'jmdomingues@remax.pt'),

    /*
    | Site público. Usado no e-mail do lead e no link de redefinição de senha.
    */
    'frontend_url' => env('FRONTEND_URL', 'https://joaodomingues.vercel.app'),

];
