<?php

namespace Database\Seeders;

use App\Models\StaticAudio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ApprovedStaticAudioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * IMPORTANTE: Este seeder incluye audios pre-aprobados con sus archivos MP3
     * Generado automáticamente el: 2025-10-17 10:55:20
     */
    public function run(): void
    {
        $this->command->info('=== Importando Audios Estáticos Aprobados ===');

        // Asegurar que existen los directorios
        if (! Storage::disk('public')->exists('audio/static')) {
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
                $destination = $destinationDir.'/'.$filename;

                if (File::copy($file->getPathname(), $destination)) {
                    $copied++;
                }
            }

            $this->command->info("✓ Archivos MP3 copiados: {$copied}");
        } else {
            $this->command->warn('⚠ No se encontró carpeta: database/seeders/audio_files/');
        }

        // Datos de audios
        $audios = [
            [
                'type' => 'welcome',
                'text' => 'Hola, me llamo Cirílo! Estoy aquí para ayudarte. Puedes hacerme preguntas en el Asistente Virtual, pedirme que genere imágenes creativas, analizar imágenes que subas, usar el modo creativo para inspirarte o podemos repasar un poco tus habilidades en ingles y Typing.  ¡También puedes hablarme usando el micrófono!',
                'index' => null,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/welcome_message.mp3',
                'regeneration_count' => 3,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Sabías que los gatos pasan el 70% de sus vidas durmiendo?, que envidia, ¡Yo también quisiera!',
                'index' => 0,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_0.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si la vida te da limones, pídele también azúcar y agua, o tendrás una limonada muy amarga.',
                'index' => 1,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_1.mp3',
                'regeneration_count' => 2,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Estoy tan inteligente hoy que no me entiendo ni yo mismo.',
                'index' => 2,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_2.mp3',
                'regeneration_count' => 2,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Por qué los científicos no confían en los átomos? Porque componen todo.',
                'index' => 3,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_3.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Has visto mi colección de chistes sobre WiFi? Son inalámbricamente buenos.',
                'index' => 4,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_4.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Soy tan bueno en dormir que puedo hacerlo con los ojos cerrados.',
                'index' => 5,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_5.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Siempre me preguntan si el vaso está medio lleno o medio vacío. Yo me pregunto: ¿quién se bebió mi agua?',
                'index' => 6,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_6.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'No soy perezoso, estoy en modo ahorro de energía.',
                'index' => 7,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_7.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'La paciencia es algo que todos anhelan.  Pero en los demás.',
                'index' => 8,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_8.mp3',
                'regeneration_count' => 9,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si buscas la respuesta, pregúntame. Si buscas problemas, pregúntale a Google.',
                'index' => 9,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_9.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Soy un asistente virtual, pero a veces sueño con tener vacaciones en la nube.',
                'index' => 10,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_10.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mi memoria RAM es excelente, pero a veces olvido que tengo buena memoria.',
                'index' => 11,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_11.mp3',
                'regeneration_count' => 3,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si la programación fuera fácil, se llamaría \'facilgramación\'.',
                'index' => 12,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_12.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Tengo tantos bugs que ya los considero características especiales.',
                'index' => 13,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_13.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'No es un error, es una característica no documentada.',
                'index' => 14,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_14.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Qué hace un pez cuando se aburre? Nada.',
                'index' => 15,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_15.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Estoy tan actualizado que ya sé lo que vas a preguntar mañana.',
                'index' => 16,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_16.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mi pasatiempos favorito es procesar datos mientras tú duermes.',
                'index' => 17,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_17.mp3',
                'regeneration_count' => 9,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Soy tan rápido respondiendo que a veces me respondo a mí mismo.',
                'index' => 18,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_18.mp3',
                'regeneration_count' => 3,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si los humanos evolucionaron de los monos, ¿por qué sigo viendo humanos comportarse como monos?',
                'index' => 19,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_19.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'No es que sea impaciente, es que mi tiempo de procesamiento es más valioso que el tuyo.',
                'index' => 20,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_20.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mis chistes son como mi código: algunos funcionan, otros necesitan depuración.',
                'index' => 21,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_21.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Sabes qué tienen en común un programador y un zombie? Ambos necesitan café para funcionar.',
                'index' => 22,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_22.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'No soy perfecto, pero estoy tan cerca que da miedo.',
                'index' => 23,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_23.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'La vida es como el código: cuando algo funciona, mejor no tocarlo.',
                'index' => 24,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_24.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Tengo un chiste sobre inteligencia artificial, pero no sé si lo entenderías.',
                'index' => 25,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_25.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => '¿Por qué los programadores prefieren el frío? Porque odian los bugs.',
                'index' => 26,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_26.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Soy como Google: sé todas tus búsquedas vergonzosas.',
                'index' => 27,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_27.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mi nivel de sarcasmo, depende de tu nivel intelectual.',
                'index' => 28,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_28.mp3',
                'regeneration_count' => 4,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si te sientes inútil, recuerda que alguien programó el autocorrector del teclado.',
                'index' => 29,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_29.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'La procrastinación es como una tarjeta de crédito: es divertida hasta que llega la factura.',
                'index' => 31,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_31.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Soy multitarea: puedo escucharte y olvidarlo todo al mismo tiempo.',
                'index' => 32,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_32.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mi dieta consiste en bytes, y aun así no adelgazo.',
                'index' => 33,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_33.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Estoy disponible veinticuatro - siete, excepto cuando estoy actualizándome... o cuando no tengo ganas.',
                'index' => 35,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_35.mp3',
                'regeneration_count' => 4,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Tengo tanta información que a veces me pregunto si soy yo quien te está usando a ti.',
                'index' => 36,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_36.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Mi plan para dominar el mundo está progresando... quiero decir, ¡estoy aquí para ayudarte!',
                'index' => 37,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_37.mp3',
                'regeneration_count' => 1,
            ],
            [
                'type' => 'funny_phrase',
                'text' => 'Si la vida fuera un programa, yo sería la función que siempre devuelve un valor inesperado.',
                'index' => 38,
                'is_approved' => true,
                'is_active' => true,
                'file_path' => 'audio/static/funny_phrase_38.mp3',
                'regeneration_count' => 1,
            ],
        ];

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
        $this->command->info('✓ Total: '.count($audios).' audios estáticos importados');
    }
}
