<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AgendamentoTest extends TestCase
{
    public function test_traduz_os_imoveis_em_falta_a_cada_6_horas(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn (Event $e) => str_contains($e->command, 'imoveis:traduzir'));

        $this->assertCount(1, $eventos);
        $this->assertSame('0 */6 * * *', $eventos->first()->expression);
        $this->assertTrue($eventos->first()->withoutOverlapping);
    }
}
