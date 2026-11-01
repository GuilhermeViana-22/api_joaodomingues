<?php

use Illuminate\Support\Facades\Schedule;

/*
| Rede de segurança da tradução dos imóveis (PT-PT -> EN, FR, ES).
|
| A API já traduz ao gravar e o job TraduzirImovel repete durante ~1h se o
| motor falhar. Depois disso só uma nova gravação ou um deploy voltavam a
| tentar; a cada 6h este comando apanha os imóveis que ainda faltam idiomas.
| Quando não falta nada, termina sem chamar o tradutor.
|
| Quem corre o agendador é o `schedule:work` do start.sh.
*/
Schedule::command('imoveis:traduzir --no-interaction')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();
