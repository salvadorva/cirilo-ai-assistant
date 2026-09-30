<?php

namespace App\Console\Commands;

use App\Services\Images\ImageEditService;
use Illuminate\Console\Command;

/** IE1: retención de 7 días de las imágenes editadas (decisión de Salva del 30/09/2026). */
class PurgeImageEdits extends Command
{
    protected $signature = 'images:purge-edits';

    protected $description = 'Borra las imágenes editadas vencidas';

    public function handle(ImageEditService $edits): int
    {
        $this->info('Imágenes editadas borradas: '.$edits->purgeExpired());

        return self::SUCCESS;
    }
}
