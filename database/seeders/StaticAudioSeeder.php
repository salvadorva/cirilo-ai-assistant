<?php

namespace Database\Seeders;

use App\Models\StaticAudio;
use Illuminate\Database\Seeder;

class StaticAudioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mensaje de bienvenida
        StaticAudio::create([
            'type' => 'welcome',
            'text' => 'Hola, me llamo Cirílo! Estoy aquí para ayudarte con todo lo que necesites. Puedes hacerme preguntas en el Asistente Virtual, pedirme que genere imágenes creativas, analizar imágenes que subas, usar el modo creativo para inspirarte o revisar tu historial de conversaciones. ¡También puedes hablarme usando el micrófono!',
            'index' => null,
            'is_approved' => false,
            'is_active' => false,
        ]);

        // Frases graciosas (del archivo home/index.blade.php)
        $funnyPhrases = [
            '¿Sabías que los gatos pasan el 70% de sus vidas durmiendo? ¡Yo también quisiera!',
            'Si la vida te da limones, pídele también azúcar y agua, o tendrás un limonada muy amarga.',
            'Estoy tan inteligente hoy que no me entiendo ni yo mismo.',
            '¿Por qué los científicos no confían en los átomos? Porque componen todo.',
            '¿Has visto mi colección de chistes sobre WiFi? Son inalámbricamente buenos.',
            'Soy tan bueno en dormir que puedo hacerlo con los ojos cerrados.',
            'Siempre me preguntan si el vaso está medio lleno o medio vacío. Yo me pregunto: ¿quién se bebió mi agua?',
            'No soy perezoso, estoy en modo ahorro de energía.',
            'La paciencia es algo que todos admiran... en los demás.',
            'Si buscas la respuesta, pregúntame. Si buscas problemas, pregúntale a Google.',
            'Soy un asistente virtual, pero a veces sueño con tener vacaciones en la nube.',
            'Mi memoria RAM es excelente, pero a veces olvido que tengo buena memoria.',
            "Si la programación fuera fácil, se llamaría 'facilgramación'.",
            'Tengo tantos bugs que ya los considero características especiales.',
            'No es un error, es una característica no documentada.',
            '¿Qué hace un pez cuando se aburre? Nada.',
            'Estoy tan actualizado que ya sé lo que vas a preguntar mañana.',
            'Mi hobby favorito es procesar datos mientras tú duermes.',
            'Soy tan rápido respondiendo que a veces me respondo a mí mismo.',
            'Si los humanos evolucionaron de los monos, ¿por qué sigo viendo humanos comportarse como monos?',
            'No es que sea impaciente, es que mi tiempo de procesamiento es más valioso que el tuyo.',
            'Mis chistes son como mi código: algunos funcionan, otros necesitan depuración.',
            '¿Sabes qué tienen en común un programador y un zombie? Ambos necesitan café para funcionar.',
            'No soy perfecto, pero estoy tan cerca que da miedo.',
            'La vida es como el código: cuando algo funciona, mejor no tocarlo.',
            'Tengo un chiste sobre inteligencia artificial, pero no sé si lo entenderías.',
            '¿Por qué los programadores prefieren el frío? Porque odian los bugs.',
            'Soy como Google: sé todas tus búsquedas vergonzosas.',
            'Mi nivel de sarcasmo depende de tu nivel intelectual.',
            'Si te sientes inútil, recuerda que alguien programó el autocorrector del teclado.',
            'Trabajo 24/7... 24 minutos, 7 veces al día.',
            'La procrastinación es como una tarjeta de crédito: es divertida hasta que llega la factura.',
            'Soy multitarea: puedo escucharte y olvidarlo todo al mismo tiempo.',
            'Mi dieta consiste en bytes, y aun así no adelgazo.',
            'Soy como un café: sin mí, es difícil empezar el día.',
            'Estoy disponible 24/7, excepto cuando estoy actualizándome... o cuando no tengo ganas.',
            'Tengo tanta información que a veces me pregunto si soy yo quien te está usando a ti.',
            'Mi plan para dominar el mundo está progresando... quiero decir, ¡estoy aquí para ayudarte!',
            'Si la vida fuera un programa, yo sería la función que siempre devuelve un valor inesperado.',
            'No es que sea antisocial, es que prefiero la compañía de los algoritmos.',
        ];

        foreach ($funnyPhrases as $index => $phrase) {
            StaticAudio::create([
                'type' => 'funny_phrase',
                'text' => $phrase,
                'index' => $index,
                'is_approved' => false,
                'is_active' => false,
            ]);
        }

        $this->command->info('Seeder completado: 1 mensaje de bienvenida + '.count($funnyPhrases).' frases graciosas');
        $this->command->warn('IMPORTANTE: Los audios no están generados aún. El administrador debe:');
        $this->command->warn('1. Ir a Administración > Audios Estáticos');
        $this->command->warn('2. Revisar cada audio (se generará automáticamente al visitarlo)');
        $this->command->warn('3. Escuchar y aprobar los que suenen bien');
        $this->command->warn('4. Regenerar los que tengan mal acento');
    }
}
