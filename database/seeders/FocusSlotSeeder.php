<?php

namespace Database\Seeders;

use App\Models\DeviceToken;
use App\Models\FocusSlot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Rutina de organización personal de Salvador (vault: Personales/organizacion/rutina-semanal).
 * Los slots se asignan al usuario dueño del teléfono (el que tiene device_tokens FCM);
 * si no hay tokens registrados, cae al primer usuario.
 */
class FocusSlotSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DeviceToken::query()->value('user_id') ?? User::query()->value('id');

        if (! $userId) {
            $this->command->error('No hay usuarios en la base — no se crearon focus slots.');

            return;
        }

        $weekdays = [1, 2, 3, 4, 5]; // lunes a viernes
        $saturday = [6];

        $slots = [
            ['time' => '08:30', 'days' => $weekdays, 'title' => 'Fin de noticias',
                'message' => 'Listo jefe: se acabaron las noticias. Cierra YouTube y dale al ritual del proyecto: tickets, logs y uso. Treinta minutos y fuera.'],
            ['time' => '09:00', 'days' => $weekdays, 'title' => 'Trabajo profundo',
                'message' => 'Arranca el bloque profundo. Un solo foco: el proyecto de la semana. Las ideas nuevas van al índice y se sueltan. Yo vigilo la puerta.'],
            ['time' => '10:45', 'days' => $weekdays, 'title' => 'Pausa',
                'message' => 'Corta ahí. Levántate, estira, agua. Nada de pantallas. En quince minutos seguimos con el estudio.'],
            ['time' => '11:00', 'days' => $weekdays, 'title' => 'Estudio',
                'message' => 'Hora de entrenar el cerebro. Regla de los veinte minutos: empieza aunque no haya ganas. Si a los veinte no fluye, cierras sin culpa.'],
            ['time' => '13:00', 'days' => $weekdays, 'title' => 'Bandeja del cliente',
                'message' => 'Cambio de sombrero: revisa solicitudes, correo y coordinación. Media hora, y después una tarea del backlog proactivo, terminada.'],
            ['time' => '17:30', 'days' => $weekdays, 'title' => 'Cierre y bitácora',
                'message' => 'Último esfuerzo del día: cinco líneas de bitácora. Qué hiciste, qué queda, plan de mañana. Eso mata el sentir que perdiste el tiempo.'],
            ['time' => '08:30', 'days' => $saturday, 'title' => 'Revisión semanal',
                'message' => 'Sábado de revisión: lee las bitácoras, anota los logros y define el proyecto de la semana entrante. Media hora bien invertida.'],
            ['time' => '09:00', 'days' => $saturday, 'title' => 'Sábado libre',
                'message' => 'El resto de la mañana es tuyo: estudio, proyecto personal o descanso sin culpa. Tú decides, jefe.'],
        ];

        foreach ($slots as $slot) {
            FocusSlot::updateOrCreate(
                ['user_id' => $userId, 'time' => $slot['time'], 'title' => $slot['title']],
                $slot + ['user_id' => $userId, 'voice' => 'echo', 'enabled' => true]
            );
        }

        $this->command->info('Focus slots creados para user_id '.$userId.' ('.count($slots).' slots).');
    }
}
