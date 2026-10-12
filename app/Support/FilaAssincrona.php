<?php

namespace App\Support;

/**
 * A fila corre em segundo plano (database/redis, com o worker do start.sh)?
 *
 * Com QUEUE_CONNECTION=sync um job "para daqui a 1 minuto" corre na hora,
 * dentro do próprio pedido: repetir uma tradução que acabou de falhar só
 * deixava a gravação mais lenta (e martelava o tradutor). Nesse caso não se
 * agenda nada; a próxima gravação volta a tentar.
 */
class FilaAssincrona
{
    public static function ativa(): bool
    {
        return config('queue.default') !== 'sync';
    }
}
