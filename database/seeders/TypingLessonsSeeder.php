<?php

namespace Database\Seeders;

use App\Models\TypingLesson;
use Illuminate\Database\Seeder;

class TypingLessonsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lessons = [
            // Nivel Principiante
            [
                'title' => 'Fila Central - ASDF JKLÑ',
                'description' => 'Aprende a escribir con la fila central del teclado español',
                'level' => 'beginner',
                'lesson_number' => 1,
                'content' => 'asdf jklñ asdf jklñ fff jjj aaa sss ddd kkk lll ññ as df jk lñ sad jad fad kas las das',
                'focus_keys' => ['a', 's', 'd', 'f', 'j', 'k', 'l', 'ñ'],
                'target_wpm' => 15,
                'target_accuracy' => 85.00,
                'estimated_duration' => 300,
                'instructions' => 'Coloca tus dedos en la posición inicial: A, S, D, F para la mano izquierda y J, K, L, Ñ para la derecha.',
                'sort_order' => 1,
            ],
            [
                'title' => 'Palabras Simples',
                'description' => 'Forma palabras simples con las teclas centrales',
                'level' => 'beginner',
                'lesson_number' => 2,
                'content' => 'as la sal las fall fall ask dad sad lad folk folk flask flask adds adds señas años',
                'focus_keys' => ['a', 's', 'd', 'f', 'j', 'k', 'l', 'ñ'],
                'target_wpm' => 18,
                'target_accuracy' => 87.00,
                'estimated_duration' => 360,
                'prerequisites' => [1],
                'instructions' => 'Concéntrate en formar palabras reales manteniendo la posición correcta. Incluye la ñ en tu práctica.',
                'sort_order' => 2,
            ],
            [
                'title' => 'Fila Superior - QWER UIOP',
                'description' => 'Extiende tu alcance a la fila superior',
                'level' => 'beginner',
                'lesson_number' => 3,
                'content' => 'qwer uiop qwer uiop qqq www eee rrr uuu iii ooo ppp que qui por que para que',
                'focus_keys' => ['q', 'w', 'e', 'r', 'u', 'i', 'o', 'p'],
                'target_wpm' => 20,
                'target_accuracy' => 85.00,
                'estimated_duration' => 420,
                'prerequisites' => [2],
                'instructions' => 'Usa los mismos dedos pero extiéndelos hacia arriba para alcanzar las teclas.',
                'sort_order' => 3,
            ],
            [
                'title' => 'Fila Inferior - ZXCV BNM',
                'description' => 'Completa el alfabeto con la fila inferior',
                'level' => 'beginner',
                'lesson_number' => 4,
                'content' => 'zxcv bnm zxcv bnm zzz xxx ccc vvv bbb nnn mmm van can ban man zen cab',
                'focus_keys' => ['z', 'x', 'c', 'v', 'b', 'n', 'm'],
                'target_wpm' => 22,
                'target_accuracy' => 87.00,
                'estimated_duration' => 480,
                'prerequisites' => [3],
                'instructions' => 'Extiende los dedos hacia abajo para alcanzar estas teclas.',
                'sort_order' => 4,
            ],

            // Nivel Intermedio
            [
                'title' => 'Palabras Completas',
                'description' => 'Combina todas las letras para formar palabras',
                'level' => 'intermediate',
                'lesson_number' => 1,
                'content' => 'casa mesa silla perro gato libro mundo tiempo persona trabajo familia amor paz bien señor niño año España',
                'focus_keys' => [],
                'target_wpm' => 25,
                'target_accuracy' => 90.00,
                'estimated_duration' => 540,
                'prerequisites' => [4],
                'instructions' => 'Ahora puedes escribir palabras completas. Mantén un ritmo constante e incluye la ñ.',
                'sort_order' => 5,
            ],
            [
                'title' => 'Números y Signos',
                'description' => 'Incorpora números y signos de puntuación',
                'level' => 'intermediate',
                'lesson_number' => 2,
                'content' => '123 456 789 0 hola, mundo. ¿cómo estás? muy bien, gracias. son 25 personas en total.',
                'focus_keys' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', ',', '.', '?', '¿'],
                'target_wpm' => 28,
                'target_accuracy' => 88.00,
                'estimated_duration' => 600,
                'prerequisites' => [5],
                'instructions' => 'Usa el dedo meñique para llegar a los números y practica los signos.',
                'sort_order' => 6,
            ],
            [
                'title' => 'Oraciones Completas',
                'description' => 'Escribir oraciones con sentido completo',
                'level' => 'intermediate',
                'lesson_number' => 3,
                'content' => 'El gato está en el jardín. María lee un libro interesante. Los niños juegan en el parque. Mañana es un día especial.',
                'focus_keys' => [],
                'target_wpm' => 30,
                'target_accuracy' => 92.00,
                'estimated_duration' => 660,
                'prerequisites' => [6],
                'instructions' => 'Practica escribir oraciones completas manteniendo el ritmo. Incluye palabras con ñ.',
                'sort_order' => 7,
            ],

            // Nivel Avanzado
            [
                'title' => 'Texto Técnico',
                'description' => 'Vocabulario técnico y especializado',
                'level' => 'advanced',
                'lesson_number' => 1,
                'content' => 'La implementación del algoritmo requiere optimización. Las variables deben ser declaradas correctamente. El sistema operativo gestiona los recursos eficientemente.',
                'focus_keys' => [],
                'target_wpm' => 35,
                'target_accuracy' => 94.00,
                'estimated_duration' => 720,
                'prerequisites' => [7],
                'instructions' => 'Vocabulario técnico requiere mayor precisión y velocidad.',
                'sort_order' => 8,
            ],
            [
                'title' => 'Símbolos Especiales',
                'description' => 'Incorpora símbolos y caracteres especiales',
                'level' => 'advanced',
                'lesson_number' => 2,
                'content' => 'function() { return "hello@world.com"; } $variable = 100%; [array] #hashtag &symbol *multiply +plus -minus',
                'focus_keys' => ['@', '#', '$', '%', '&', '*', '(', ')', '[', ']', '{', '}', '+', '-', '='],
                'target_wpm' => 32,
                'target_accuracy' => 90.00,
                'estimated_duration' => 780,
                'prerequisites' => [8],
                'instructions' => 'Los símbolos requieren combinaciones de teclas. Practica lentamente.',
                'sort_order' => 9,
            ],
            [
                'title' => 'Texto Profesional',
                'description' => 'Redacción profesional y empresarial',
                'level' => 'advanced',
                'lesson_number' => 3,
                'content' => 'Estimado Sr. García, adjunto el informe solicitado. La reunión se realizará el viernes a las 15:30. Quedamos a la espera de su confirmación.',
                'focus_keys' => [],
                'target_wpm' => 40,
                'target_accuracy' => 96.00,
                'estimated_duration' => 840,
                'prerequisites' => [9],
                'instructions' => 'Texto formal requiere alta precisión y velocidad sostenida.',
                'sort_order' => 10,
            ],
        ];

        foreach ($lessons as $lesson) {
            TypingLesson::create($lesson);
        }
    }
}
