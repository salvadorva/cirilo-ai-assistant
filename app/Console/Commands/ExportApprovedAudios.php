<?php

namespace App\Console\Commands;

use App\Models\StaticAudio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ExportApprovedAudios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:export-approved
                            {--all : Exportar todos los audios, no solo aprobados}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exporta audios aprobados a un seeder y copia los archivos MP3 para commit en Git';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== Exportación de Audios Aprobados ===\n");

        $exportAll = $this->option('all');

        // Obtener audios a exportar
        $query = StaticAudio::query();

        if (! $exportAll) {
            $query->where('is_approved', true);
        }

        $audios = $query->orderBy('type')->orderBy('index')->get();

        if ($audios->isEmpty()) {
            $this->error('No hay audios para exportar');
            if (! $exportAll) {
                $this->line('Usa --all para exportar todos los audios (aprobados o no)');
            }

            return 1;
        }

        $this->info('Audios a exportar: '.$audios->count());

        // Crear carpeta de destino para archivos estáticos
        $destinationDir = database_path('seeders/audio_files');
        if (! File::exists($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true);
            $this->line('✓ Carpeta creada: database/seeders/audio_files/');
        }

        // Copiar archivos MP3
        $copied = 0;
        $skipped = 0;

        $this->info("\n=== Copiando archivos MP3 ===");

        foreach ($audios as $audio) {
            if ($audio->file_path && Storage::disk('public')->exists($audio->file_path)) {
                $sourceFile = Storage::disk('public')->path($audio->file_path);
                $filename = basename($audio->file_path);
                $destinationFile = $destinationDir.'/'.$filename;

                if (File::copy($sourceFile, $destinationFile)) {
                    $this->line("✓ Copiado: {$filename}");
                    $copied++;
                } else {
                    $this->error("✗ Error al copiar: {$filename}");
                    $skipped++;
                }
            } else {
                $this->warn("⊘ Sin archivo: {$audio->type} #{$audio->index}");
                $skipped++;
            }
        }

        // Generar seeder
        $this->info("\n=== Generando Seeder ===");

        $seederContent = $this->generateSeederContent($audios);
        $seederPath = database_path('seeders/ApprovedStaticAudioSeeder.php');

        File::put($seederPath, $seederContent);
        $this->info('✓ Seeder generado: database/seeders/ApprovedStaticAudioSeeder.php');

        // Generar archivo .gitignore para audio/dynamic
        $this->info("\n=== Configurando Git ===");

        $gitignorePath = storage_path('app/public/audio/.gitignore');
        $gitignoreContent = "# Ignorar audios dinámicos (temporales)\ndynamic/*\n!dynamic/.gitkeep\n\n# Mantener audios estáticos en Git\n!static/\n";

        try {
            File::put($gitignorePath, $gitignoreContent);
            $this->line('✓ .gitignore creado en: storage/app/public/audio/.gitignore');
        } catch (\Exception $e) {
            $this->warn('⚠ No se pudo crear .gitignore: '.$e->getMessage());
        }

        // Crear .gitkeep en dynamic
        $gitkeepPath = storage_path('app/public/audio/dynamic/.gitkeep');

        // Asegurar que la carpeta dynamic existe
        $dynamicDir = storage_path('app/public/audio/dynamic');
        if (! File::exists($dynamicDir)) {
            File::makeDirectory($dynamicDir, 0755, true);
        }

        try {
            File::put($gitkeepPath, '');
            $this->line('✓ .gitkeep creado en: storage/app/public/audio/dynamic/.gitkeep');
        } catch (\Exception $e) {
            $this->warn('⚠ No se pudo crear .gitkeep: '.$e->getMessage());
            $this->line("  Ejecuta manualmente: touch {$gitkeepPath}");
        }

        // Resumen
        $this->info("\n=== Resumen ===");
        $this->line("Total audios exportados: {$audios->count()}");
        $this->line("Archivos MP3 copiados: <fg=green>{$copied}</>");
        $this->line("Archivos omitidos: <fg=yellow>{$skipped}</>");

        // Instrucciones finales
        $this->info("\n=== Próximos Pasos ===");
        $this->line('1. Revisa el seeder generado: database/seeders/ApprovedStaticAudioSeeder.php');
        $this->line('2. Revisa los archivos MP3: database/seeders/audio_files/');
        $this->line('3. Commit de los cambios:');
        $this->comment('   git add database/seeders/');
        $this->comment('   git add storage/app/public/audio/.gitignore');
        $this->comment('   git add storage/app/public/audio/dynamic/.gitkeep');
        $this->comment('   git commit -m "Add approved static audios for production"');
        $this->line("\n4. En producción, ejecuta:");
        $this->comment('   php artisan db:seed --class=ApprovedStaticAudioSeeder');

        return 0;
    }

    /**
     * Genera el contenido del seeder
     */
    private function generateSeederContent($audios)
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $audioData = [];

        foreach ($audios as $audio) {
            $audioData[] = [
                'type' => $audio->type,
                'text' => $audio->text,
                'index' => $audio->index,
                'is_approved' => $audio->is_approved ? 'true' : 'false',
                'is_active' => $audio->is_active ? 'true' : 'false',
                'file_path' => $audio->file_path,
                'regeneration_count' => $audio->regeneration_count,
            ];
        }

        $content = <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StaticAudio;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ApprovedStaticAudioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * IMPORTANTE: Este seeder incluye audios pre-aprobados con sus archivos MP3
     * Generado automáticamente el: TIMESTAMP_PLACEHOLDER
     */
    public function run(): void
    {
        $this->command->info('=== Importando Audios Estáticos Aprobados ===');

        // Asegurar que existen los directorios
        if (!Storage::disk('public')->exists('audio/static')) {
            Storage::disk('public')->makeDirectory('audio/static');
        }

        // Copiar archivos MP3 desde database/seeders/audio_files/ a storage
        $sourceDir = database_path('seeders/audio_files');
        $destinationDir = storage_path('app/public/audio/static');

        if (File::exists($sourceDir)) {
            $files = File::files($sourceDir);
            $copied = 0;

            foreach ($files as $file) {
                $filename = $file->getFilename();
                $destination = $destinationDir . '/' . $filename;

                if (File::copy($file->getPathname(), $destination)) {
                    $copied++;
                }
            }

            $this->command->info("✓ Archivos MP3 copiados: {$copied}");
        } else {
            $this->command->warn("⚠ No se encontró carpeta: database/seeders/audio_files/");
        }

        // Datos de audios
        $audios = AUDIO_DATA_PLACEHOLDER;

        $created = 0;
        $updated = 0;

        foreach ($audios as $audioData) {
            $audio = StaticAudio::updateOrCreate(
                [
                    'type' => $audioData['type'],
                    'index' => $audioData['index'],
                ],
                [
                    'text' => $audioData['text'],
                    'file_path' => $audioData['file_path'],
                    'is_approved' => $audioData['is_approved'],
                    'is_active' => $audioData['is_active'],
                    'regeneration_count' => $audioData['regeneration_count'] ?? 0,
                ]
            );

            if ($audio->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("✓ Audios creados: {$created}");
        $this->command->info("✓ Audios actualizados: {$updated}");
        $this->command->info("✓ Total: " . count($audios) . " audios estáticos importados");
    }
}
PHP;

        // Reemplazar placeholders
        $content = str_replace('TIMESTAMP_PLACEHOLDER', $timestamp, $content);
        $content = str_replace('AUDIO_DATA_PLACEHOLDER', $this->arrayToString($audioData), $content);

        return $content;
    }

    /**
     * Convierte array a string formateado para el seeder
     */
    private function arrayToString($array)
    {
        $lines = ['['];

        foreach ($array as $item) {
            $lines[] = '            [';
            $lines[] = "                'type' => '{$item['type']}',";
            $lines[] = "                'text' => ".var_export($item['text'], true).',';
            $lines[] = "                'index' => ".($item['index'] === null ? 'null' : $item['index']).',';
            $lines[] = "                'is_approved' => {$item['is_approved']},";
            $lines[] = "                'is_active' => {$item['is_active']},";
            $lines[] = "                'file_path' => ".($item['file_path'] ? "'{$item['file_path']}'" : 'null').',';
            $lines[] = "                'regeneration_count' => {$item['regeneration_count']},";
            $lines[] = '            ],';
        }

        $lines[] = '        ]';

        return implode("\n", $lines);
    }
}
