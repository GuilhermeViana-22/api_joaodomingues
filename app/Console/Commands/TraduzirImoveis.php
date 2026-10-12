<?php

namespace App\Console\Commands;

use App\Models\Imovel;
use App\Services\ImovelService;
use App\Services\TradutorService;
use Illuminate\Console\Command;

/**
 * Gera (ou volta a gerar) as traduções EN/FR/ES dos imóveis.
 *
 * Não é preciso correr à mão: a API traduz ao gravar, o job TraduzirImovel
 * repete quando o motor falha, e o start.sh corre este comando em segundo
 * plano a cada deploy para apanhar imóveis gravados antes da tradução
 * automática. Fica disponível para forçar (--todos) se for preciso.
 *
 *   php artisan imoveis:traduzir            # só os que faltam idiomas
 *   php artisan imoveis:traduzir --todos    # todos
 *   php artisan imoveis:traduzir jd-001     # um imóvel
 */
class TraduzirImoveis extends Command
{
    protected $signature = 'imoveis:traduzir {id? : id do imóvel} {--todos : volta a traduzir mesmo os que já têm os 3 idiomas}';

    protected $description = 'Traduz os textos dos imóveis de PT-PT para EN, FR e ES';

    public function handle(ImovelService $imoveis): int
    {
        $consulta = Imovel::query();
        if ($id = $this->argument('id')) {
            $consulta->whereKey($id);
        }

        $lista = $consulta->get()->filter(function (Imovel $i) {
            $idiomas = array_keys($i->traducoes ?? []);

            return $this->option('todos') || $this->argument('id') || count(array_intersect(TradutorService::IDIOMAS_SITE, $idiomas)) < count(TradutorService::IDIOMAS_SITE);
        });

        if ($lista->isEmpty()) {
            $this->info('Nada a traduzir.');

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($lista as $imovel) {
            $idiomas = $imoveis->retraduzir($imovel);
            $falhas += count($idiomas) < count(TradutorService::IDIOMAS_SITE) ? 1 : 0;
            $this->line(sprintf('%-24s %s', $imovel->id, $idiomas ? implode(', ', $idiomas) : 'falhou'));
        }

        if ($falhas) {
            $this->warn("{$falhas} imóvel(is) sem todos os idiomas. Veja o log e repita o comando.");

            return self::FAILURE;
        }

        $this->info('Traduções atualizadas.');

        return self::SUCCESS;
    }
}
