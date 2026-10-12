<?php

namespace App\Jobs;

use App\Models\Imovel;
use App\Services\ImovelService;
use App\Services\TradutorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Repete a tradução de um imóvel que ficou sem algum idioma.
 *
 * A tradução normal acontece no próprio insert/update (ImovelService). Este
 * job só entra quando o motor falhou nesse momento: o ImovelService põe-no
 * na fila e o worker (start.sh) tenta de novo, com intervalos crescentes,
 * sem ninguém precisar de correr comandos. Esgotadas as tentativas, fica no
 * log; a próxima gravação do imóvel volta a tentar.
 */
class TraduzirImovel implements ShouldQueue
{
    use Queueable;

    /** Espera antes de cada nova tentativa, em segundos: 1 min, 5 min, 15 min, 1 h. */
    private const ESPERAS = [60, 300, 900, 3600];

    /** O job controla as tentativas com release(); isto é só o teto. */
    public int $tries = 5;

    public function __construct(public string $imovelId) {}

    public function handle(ImovelService $imoveis): void
    {
        $imovel = Imovel::query()->find($this->imovelId);
        if ($imovel === null) {
            return; // apagado entretanto
        }

        $idiomas = $imoveis->retraduzir($imovel);
        if (count($idiomas) >= count(TradutorService::IDIOMAS_SITE)) {
            return;
        }

        $tentativa = $this->attempts();
        if ($tentativa <= count(self::ESPERAS)) {
            $this->release(self::ESPERAS[$tentativa - 1]);

            return;
        }

        Log::error('Tradução automática desistiu depois de várias tentativas.', [
            'imovel' => $this->imovelId,
            'idiomas_ok' => $idiomas,
        ]);
    }
}
