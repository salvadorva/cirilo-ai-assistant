<?php

namespace App\Services\Agenda;

use App\Support\AiLog as Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Extrae datos de un evento del texto con el modelo de extracción. Devuelve null si falla el
 * proveedor; un campo que el usuario no dijo vuelve como null (nunca se inventa la hora).
 */
class AgendaExtractor
{
    public function extract(string $text): ?array
    {
        // Sin clave la llamada falla de forma explícita (401) y se devuelve null.
        $apiKey = (string) config('services.openai.api_key');

        $now = Carbon::now(config('app.timezone'));
        $prompt = 'Analiza el siguiente texto y extrae los datos de UN evento de calendario. '
            ."Responde SOLO con JSON válido, sin texto adicional ni bloques de código.\n\n"
            ."Fecha y hora actual: {$now->format('Y-m-d H:i')} ({$now->locale('es')->isoFormat('dddd')}, zona horaria America/Guatemala, UTC-6)\n\n"
            ."Texto:\n\"\"\"\n{$text}\n\"\"\"\n\n"
            ."Devuelve un JSON con esta estructura (null en lo que el usuario no dijo):\n"
            .'{"title":null,"start_date":null,"start_time":null,"end_date":null,"end_time":null,"all_day":false,"description":null,"location":null,"reminder_minutes_before":null,"recurrence_type":"none","recurrence_days":null,"recurrence_end_date":null}'."\n\n"
            ."Reglas:\n"
            ."- title: nombre concreto (\"Llamar a Ana\", \"Dentista\"). Si no hay uno, null.\n"
            ."- start_date: YYYY-MM-DD. «mañana», «el lunes» (el próximo), fechas concretas. Si da hora sin fecha, hoy. Si no hay fecha ni hora, null.\n"
            ."- start_time: HH:MM en 24 h. Si no dijo hora, null. NO supongas una hora.\n"
            ."- all_day: true SOLO si dijo «todo el día» o es claramente de día completo (cumpleaños, feriado) sin hora.\n"
            ."- end_time / end_date: solo si los dijo.\n"
            ."- reminder_minutes_before: solo si pidió un aviso previo concreto.\n"
            ."- recurrence_type: none | daily | weekdays | weekly | custom. recurrence_days en inglés en minúsculas; recurrence_end_date YYYY-MM-DD si lo dijo.\n"
            .'- Si hay «Datos ya reunidos», consérvalos y completa o corrige con lo nuevo.';

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.$apiKey])->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('ai.models.extraction'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0,
                    'max_tokens' => 400,
                ]);
            if (! $response->successful()) {
                Log::error('AgendaExtractor: el proveedor respondió '.$response->status());

                return null;
            }
            $content = preg_replace(['/^```(?:json)?\s*/m', '/^```\s*/m'], '', (string) $response->json('choices.0.message.content'));
            $data = json_decode(trim($content), true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('AgendaExtractor: '.$e->getMessage());

            return null;
        }
    }
}
