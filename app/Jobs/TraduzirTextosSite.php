<?php

namespace App\Jobs;

use App\Services\TextosSiteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Repete a tradução dos textos do site quando o motor falhou ao gravar.
 *
 * Como o TraduzirImovel: o TextosSiteService põe-no na fila, o worker
 * (start.sh) tenta de novo com intervalos crescentes, e só as chaves ainda
 * desatualizadas (pelas `fontes`) vão ao tradutor. Várias gravações seguidas
 * podem pôr vários jobs na fila; os que chegarem depois não têm nada a fazer.
 */
class TraduzirTextosSite implements ShouldQueue
{
    use Queueable;

    /** Espera antes de cada nova tentativa, em segundos: 1 min, 5 min, 15 min, 1 h. */
    private const ESPERAS = [60, 300, 900, 3600];

    public int $tries = 5;

    public function handle(TextosSiteService $textos): void
    {
        // Sem agendar outro job: este repete-se a si próprio com release()
        $pendentes = $textos->sincronizarTraducoes(agendarRepeticao: false);
        if ($pendentes === []) {
            return;
        }

        $tentativa = $this->attempts();
        if ($tentativa <= count(self::ESPERAS)) {
            $this->release(self::ESPERAS[$tentativa - 1]);

            return;
        }

        Log::error('Tradução dos textos do site desistiu depois de várias tentativas.', ['idiomas' => $pendentes]);
    }
}
