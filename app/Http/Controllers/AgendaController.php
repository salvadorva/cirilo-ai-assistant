<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Services\Agenda\AgendaResult;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\AgendaValidationException;
use App\Services\Agenda\EventNotifier;
use App\Services\NextcloudCalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;
use Illuminate\Support\Facades\Validator;
use OpenAI\Laravel\Facades\OpenAI;

class AgendaController extends Controller
{
    /**
     * Muestra la vista principal de la agenda
     */
    public function index()
    {
        return view('agenda.index');
    }

    /**
     * Obtiene los detalles de un evento específico
     */
    public function show($id)
    {
        try {
            // Obtener el evento del usuario actual
            $event = CalendarEvent::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Formatear fechas para el formulario
            $startDateTime = Carbon::parse($event->start_date);
            $endDateTime = $event->end_date ? Carbon::parse($event->end_date) : null;

            // Preparar datos para el frontend
            $formattedEvent = [
                'id'                      => $event->id,
                'title'                   => $event->title,
                'description'             => $event->description,
                'start_date'              => $startDateTime->format('Y-m-d'),
                'start_time'              => $event->all_day ? null : $startDateTime->format('H:i'),
                'end_date'                => $endDateTime ? $endDateTime->format('Y-m-d') : null,
                'end_time'                => ($endDateTime && ! $event->all_day) ? $endDateTime->format('H:i') : null,
                'all_day'                 => (bool) $event->all_day,
                'category'                => $event->category ?: 'general',
                'location'                => $event->location ?? '',
                'color'                   => $event->color ?? '#3788d8',
                'reminder_minutes_before' => (int) ($event->reminder_minutes_before ?? 0),
                // F3: estado por canal; «aceptado» es del proveedor, no confirma que se haya leído.
                'notifications'           => EventNotifier::summary($event),
            ];

            return response()->json([
                'success' => true,
                'event' => $formattedEvent,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del evento: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener los detalles del evento',
            ], 404);
        }
    }

    /**
     * Actualiza un evento existente
     */
    public function update(Request $request, $id, AgendaService $agenda)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'all_day' => 'boolean',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'reminder_minutes_before' => 'nullable|integer|min:0|max:10080',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $event = $agenda->owned(Auth::user(), $id);
        if (! $event) {
            return response()->json(['success' => false, 'message' => 'No se pudo actualizar el evento'], 404);
        }

        $tz = config('app.timezone');
        $allDay = (bool) $request->boolean('all_day');
        $start = Carbon::parse($request->start_date, $tz);
        if (! $allDay && $request->start_time) {
            [$hours, $minutes] = explode(':', $request->start_time);
            $start->setTime((int) $hours, (int) $minutes);
        }
        $end = null;
        if ($request->end_date) {
            $end = Carbon::parse($request->end_date, $tz);
            if (! $allDay && $request->end_time) {
                [$hours, $minutes] = explode(':', $request->end_time);
                $end->setTime((int) $hours, (int) $minutes);
            }
        }

        $changes = ['title' => $request->title, 'description' => $request->description ?? '', 'category' => $request->category ?? 'general',
            'start' => $start, 'end' => $end, 'all_day' => $allDay];
        if ($request->reminder_minutes_before !== null) {
            $changes['reminder_minutes_before'] = (int) $request->reminder_minutes_before;
        }

        try {
            $event = $agenda->update(Auth::user(), $event, $changes);
        } catch (AgendaValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos: '.$e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar evento: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo actualizar el evento'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Evento actualizado correctamente', 'event' => $event]);
    }

    /**
     * Elimina un evento existente
     */
    public function destroy($id, AgendaService $agenda)
    {
        $event = $agenda->owned(Auth::user(), $id);
        if (! $event) {
            return response()->json(['success' => false, 'message' => 'No se pudo eliminar el evento'], 404);
        }

        try {
            $agenda->delete(Auth::user(), $event);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar evento: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'No se pudo eliminar el evento'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Evento eliminado correctamente']);
    }

    /**
     * Actualiza todos los eventos de una serie (mismos campos comunes, fechas individuales intactas).
     * Solo actualiza: título, descripción, categoría, ubicación, color, recordatorio y la hora
     * (desplazando la hora de inicio/fin en todos los eventos de la serie).
     */
    public function updateSeries(Request $request, string $seriesId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title'                   => 'required|string|max:255',
                'description'             => 'nullable|string',
                'category'                => 'nullable|string',
                'location'                => 'nullable|string',
                'color'                   => 'nullable|string',
                'reminder_minutes_before' => 'nullable|integer|min:0',
                'start_time'              => 'nullable|string',
                'end_time'                => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $events = CalendarEvent::where('series_id', $seriesId)
                ->where('user_id', Auth::id())
                ->get();

            if ($events->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Serie no encontrada'], 404);
            }

            foreach ($events as $event) {
                $event->title                   = $request->title;
                $event->description             = $request->description;
                $event->category                = $request->category ?? $event->category;
                $event->location                = $request->location ?? $event->location;
                $event->color                   = $request->color ?? $event->color;
                $event->reminder_minutes_before = $request->reminder_minutes_before ?? $event->reminder_minutes_before;
                $event->notified                = false;

                // Si se cambia la hora, desplazamos la hora manteniendo la fecha original
                if ($request->filled('start_time')) {
                    [$h, $m] = explode(':', $request->start_time);
                    $event->start_date = $event->start_date->setHour((int) $h)->setMinute((int) $m)->setSecond(0);
                }
                if ($request->filled('end_time') && $event->end_date) {
                    [$h, $m] = explode(':', $request->end_time);
                    $event->end_date = $event->end_date->setHour((int) $h)->setMinute((int) $m)->setSecond(0);
                }

                $event->save();
            }

            return response()->json([
                'success' => true,
                'message' => "Serie actualizada ({$events->count()} eventos)",
                'count'   => $events->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar serie: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'No se pudo actualizar la serie'], 500);
        }
    }

    /**
     * Elimina todos los eventos de una serie.
     */
    public function destroySeries(string $seriesId)
    {
        try {
            $user   = Auth::user();
            $events = CalendarEvent::where('series_id', $seriesId)
                ->where('user_id', $user->id)
                ->get();

            if ($events->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Serie no encontrada'], 404);
            }

            // Eliminar de Nextcloud los que estén sincronizados
            if ($user->hasNextcloud()) {
                $service = new NextcloudCalendarService($user);
                foreach ($events->where('nextcloud_synced', true) as $event) {
                    try { $service->deleteEvent($event); } catch (\Exception $e) {
                        Log::warning('No se pudo eliminar evento de serie de Nextcloud: '.$e->getMessage());
                    }
                }
            }

            $count = $events->count();
            CalendarEvent::where('series_id', $seriesId)->where('user_id', $user->id)->delete();

            if ($count === 0) {
                return response()->json(['success' => false, 'message' => 'Serie no encontrada'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Serie eliminada ({$count} eventos)",
                'count'   => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar serie: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'No se pudo eliminar la serie'], 500);
        }
    }

    /**
     * Devuelve eventos próximos cuyo recordatorio debe mostrarse como notificación local.
     * La página la llama cada 5 min para disparar notificaciones sin VAPID.
     */
    public function upcomingNotifications(Request $request)
    {
        $user = Auth::user();
        $tz = 'America/Guatemala';
        $now = Carbon::now($tz);
        // Ventana: eventos que empiezan en los próximos 35 minutos
        $windowEnd = $now->copy()->addMinutes(35);

        $events = CalendarEvent::where('user_id', $user->id)
            ->where('notified', false)
            ->where('reminder_minutes_before', '>', 0)
            ->whereBetween('start_date', [$now->toDateTimeString(), $windowEnd->toDateTimeString()])
            ->get()
            ->filter(function ($event) use ($now, $tz) {
                // Solo incluir si ya llegó el momento del recordatorio
                $reminderTime = Carbon::parse($event->start_date)
                    ->setTimezone($tz)
                    ->subMinutes($event->reminder_minutes_before);

                return $now->gte($reminderTime);
            })
            ->map(function ($event) use ($tz) {
                $start = Carbon::parse($event->start_date)->setTimezone($tz);

                return [
                    'id'            => $event->id,
                    'title'         => $event->title,
                    'start'         => $start->format('H:i'),
                    'minutesBefore' => $event->reminder_minutes_before,
                ];
            })
            ->values();

        return response()->json(['events' => $events]);
    }

    /**
     * Obtiene todos los eventos del usuario actual
     */
    public function getEvents(Request $request)
    {
        // Soportar también la consulta por fecha única (parámetro "date") además de los rangos start/end.
        if ($request->filled('date')) {
            $singleDate = Carbon::parse($request->input('date'));
            $start = $singleDate->copy()->startOfDay()->toDateTimeString();
            $end = $singleDate->copy()->endOfDay()->toDateTimeString();
        } else {
            $start = $request->input('start');
            $end = $request->input('end');
        }

        // Si FullCalendar no envía los parámetros o llegan vacíos, evitamos errores asignando un rango amplio por defecto
        if (empty($start) || empty($end)) {
            // Rango por defecto: primer día del mes actual hasta último día del mes
            $start = Carbon::now()->startOfMonth()->toDateString();
            $end = Carbon::now()->endOfMonth()->toDateString();
        }

        $events = CalendarEvent::where('user_id', Auth::id())
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'titulo' => $event->title,
                    'start' => $event->start_date->format('Y-m-d H:i:s'),
                    'end' => $event->end_date ? $event->end_date->format('Y-m-d H:i:s') : null,
                    // Hora formateada (HH:mm) o null si all_day
                    'hora' => $event->all_day ? null : $event->start_date->format('H:i'),
                    'allDay' => $event->all_day,
                    'backgroundColor' => $event->color,
                    'borderColor' => $event->color,
                    'extendedProps' => [
                        'description'             => $event->description,
                        'category'                => $event->category,
                        'status'                  => $event->status,
                        'location'                => $event->location,
                        'reminder_minutes_before' => (int) ($event->reminder_minutes_before ?? 0),
                        'series_id'               => $event->series_id,
                    ],
                ];
            });

        return response()->json($events);
    }

    /**
     * Detecta si el comando de creación es genérico (sin detalles específicos)
     */
    private function isGenericCreateCommand($command)
    {
        $command = strtolower($command);

        // Patrones que indican comandos genéricos
        $genericPatterns = [
            'crear un nuevo evento',
            'crear evento',
            'crear un evento',
            'agendar evento',
            'programar evento',
            'nuevo evento',
            'quiero crear',
        ];

        foreach ($genericPatterns as $pattern) {
            if (strpos($command, $pattern) !== false) {
                return true;
            }
        }

        // Si contiene palabras como "reunión", "cita", "ejercicio", etc., no es genérico
        $specificWords = ['reunión', 'reunìon', 'cita', 'ejercicio', 'comida', 'almuerzo', 'desayuno', 'cena', 'junta', 'clase', 'trabajo', 'llamada', 'entrevista'];
        foreach ($specificWords as $word) {
            if (strpos($command, $word) !== false) {
                return false;
            }
        }

        // Si contiene información de tiempo específica, no es genérico
        if (preg_match('/a las? \d{1,2}/i', $command) || preg_match('/\d{1,2}:\d{2}/i', $command)) {
            return false;
        }

        return true;
    }

    /**
     * Obtiene los eventos para una fecha específica
     */
    protected function getEventsForDate($dateStr)
    {
        try {
            $date = Carbon::parse($dateStr);
            $userId = auth()->id();

            return CalendarEvent::where('user_id', $userId)
                ->whereDate('start_date', $date->toDateString())
                ->get();
        } catch (\Exception $e) {
            Log::error('Error al parsear la fecha de la consulta.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Formatea el mensaje de eventos para la respuesta de voz
     */
    protected function formatEventsMessage($events, $dateStr)
    {
        if ($events->isEmpty()) {
            return 'No tienes eventos programados para el '.Carbon::parse($dateStr)->format('d \d\e F \d\e Y').'.';
        }

        $message = 'Estos son tus eventos para el '.Carbon::parse($dateStr)->format('d \d\e F \d\e Y').":\n";
        foreach ($events as $index => $event) {
            $startTime = Carbon::parse($event->start_date)->format('H:i');
            $message .= ($index + 1).". $event->title a las $startTime.\n";
        }

        return $message;
    }

    /**
     * Almacena un nuevo evento desde el formulario
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, AgendaService $agenda)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'reminder_minutes_before' => 'nullable|integer|min:0|max:10080',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos: '.$validator->errors()->first()], 422);
        }

        $key = $request->header('Idempotency-Key');
        $result = $agenda->create(Auth::user(), $this->formAttributes($request) + ['reminder_minutes_before' => 10], is_string($key) && $key !== '' ? mb_substr($key, 0, 100) : null, 'web');

        if ($result->status === AgendaResult::INVALID) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos: '.$result->message], 422);
        }
        if (! $result->persisted()) {
            return response()->json(['success' => false, 'message' => 'No se pudo crear el evento. Intenta de nuevo más tarde.'],
                $result->status === AgendaResult::CONFLICT ? 409 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result->status === AgendaResult::DUPLICATE ? 'Ese evento ya estaba en tu agenda' : 'Evento creado correctamente',
            'event' => $result->first(),
            'duplicate' => $result->status === AgendaResult::DUPLICATE,
        ]);
    }

    /**
     * Formulario web (fecha + hora por separado, «todo el día») → entrada de AgendaService.
     */
    private function formAttributes(Request $request): array
    {
        $allDay = in_array($request->input('all_day'), ['on', true, 1, '1', 'true'], true);
        $startDate = Carbon::parse($request->input('start_date'))->format('Y-m-d');
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->format('Y-m-d') : $startDate;
        $tz = config('app.timezone');

        $attributes = [
            'title' => $request->input('title'),
            'description' => $request->input('description') ?? '',
            'category' => $request->input('category') ?: 'general',
            'location' => $request->input('location') ?? '',
            'color' => $request->input('color') ?: '#3788d8',
            'all_day' => $allDay,
            'start' => Carbon::parse($startDate.' '.($allDay ? '00:00' : ($request->input('start_time') ?: '00:00')), $tz),
            // Como antes: sin hora de fin, el evento dura hasta las 23:59 de su último día.
            'end' => Carbon::parse($endDate.' '.($allDay ? '00:00' : ($request->input('end_time') ?: '23:59')), $tz),
        ];
        if ($request->filled('reminder_minutes_before')) {
            $attributes['reminder_minutes_before'] = (int) $request->input('reminder_minutes_before');
        }

        return $attributes;
    }

    /**
     * Obtiene las credenciales de la API de OpenAI
     *
     * @return array Credenciales de la API
     */
    private function getApiCredentials()
    {
        return [
            'api_key' => config('services.openai.api_key'),
            'base_url' => 'https://api.openai.com/v1',
        ];
    }

    /**
     * Determina si la solicitud es una consulta de eventos
     */
    private function isQueryRequest($userInput)
    {
        $queryPatterns = [
            '/como esta mi calendario/i',
            '/que tengo para/i',
            '/eventos para/i',
            '/agenda para/i',
            '/que hay en mi agenda/i',
            '/mostrar eventos/i',
            '/ver eventos/i',
            '/consultar agenda/i',
            '/consultar calendario/i',
            '/consultar mis eventos/i',
            '/que tengo programado/i',
            '/cuales son mis eventos/i',
            '/cuales son mis compromisos/i',
            '/tengo algo para/i',
            '/eventos de hoy/i',
            '/agenda de hoy/i',
            '/calendario de hoy/i',
            '/eventos del dia/i',
            '/agenda del dia/i',
        ];

        // Primero verificar patrones exactos
        foreach ($queryPatterns as $pattern) {
            if (preg_match($pattern, $userInput)) {
                Log::info('Detectada consulta de eventos mediante patrón: '.$pattern);

                return true;
            }
        }

        // Si no hay coincidencia exacta, verificar palabras clave
        $keywords = ['eventos', 'agenda', 'calendario', 'citas', 'compromisos'];
        $queryWords = ['consultar', 'mostrar', 'ver', 'que hay', 'cuales son'];

        $lowercaseInput = strtolower($userInput);

        foreach ($keywords as $keyword) {
            if (strpos($lowercaseInput, $keyword) !== false) {
                foreach ($queryWords as $queryWord) {
                    if (strpos($lowercaseInput, $queryWord) !== false) {
                        Log::info('Detectada consulta de eventos mediante palabras clave: '.$keyword.' + '.$queryWord);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Maneja una solicitud de consulta de eventos
     */
    private function handleQueryRequest($queryDate, $user)
    {
        // Parse the date from the query
        try {
            $date = Carbon::parse($queryDate);
            $formattedDate = $date->format('Y-m-d');

            // Fetch events for the specified date
            $events = CalendarEvent::where('user_id', $user->id)
                ->whereDate('start', $formattedDate)
                ->get();

            // Prepare the response message
            if ($events->isEmpty()) {
                $message = 'No tienes eventos programados para el '.$date->format('d \d\e F \d\e Y').'.';
                $voiceMessage = 'No tienes eventos para el '.$date->format('d \d\e F');
            } else {
                $message = 'Estos son tus eventos para el '.$date->format('d \d\e F \d\e Y').":\n";
                $voiceMessage = 'Tus eventos para el '.$date->format('d \d\e F').': ';
                foreach ($events as $index => $event) {
                    $startTime = Carbon::parse($event->start_date)->format('H:i');
                    $message .= ($index + 1).". $event->title a las $startTime.\n";
                    $voiceMessage .= ($index + 1).". $event->title a las $startTime. ";
                }
            }

            return [
                'next_step' => 'final',
                'message' => $message,
                'voice_message' => $voiceMessage,
                'intent' => 'query_result',
                'events' => $events->toArray(),
                'conversation_context' => [],
                'partial_event' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Error al parsear la fecha de la consulta.', ['error' => $e->getMessage()]);

            return [
                'next_step' => 'final',
                'message' => 'Lo siento, no pude entender la fecha que mencionaste. Por favor, intenta de nuevo.',
                'voice_message' => 'No entendí la fecha. Por favor, intenta de nuevo.',
                'intent' => 'error',
                'conversation_context' => [],
                'partial_event' => [],
            ];
        }
    }

    /**
     * Extrae el rango de fechas de una consulta
     */
    private function extractDateRangeFromQuery($query)
    {
        $today = Carbon::today();
        $start = $today->copy();
        $end = $today->copy()->endOfDay();

        // Patrones comunes
        if (preg_match('/hoy/i', $query)) {
            $start = $today;
            $end = $today->copy()->endOfDay();
        } elseif (preg_match('/mañana/i', $query)) {
            $start = $today->copy()->addDay();
            $end = $start->copy()->endOfDay();
        } elseif (preg_match('/esta semana/i', $query)) {
            $start = $today->copy()->startOfWeek();
            $end = $today->copy()->endOfWeek();
        } elseif (preg_match('/próxima semana|semana que viene/i', $query)) {
            $start = $today->copy()->addWeek()->startOfWeek();
            $end = $start->copy()->endOfWeek();
        } elseif (preg_match('/este mes/i', $query)) {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->endOfMonth();
        } elseif (preg_match('/próximo mes|mes que viene/i', $query)) {
            $start = $today->copy()->addMonth()->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        // Días específicos de la semana
        $weekdays = ['lunes', 'martes', 'miércoles', 'miercoles', 'jueves', 'viernes', 'sábado', 'sabado', 'domingo'];
        foreach ($weekdays as $index => $day) {
            if (preg_match('/(?:este|el|próximo|proximo) '.$day.'/i', $query)) {
                $dayOfWeek = $index + 1; // Carbon usa 1 (lunes) a 7 (domingo)
                $start = $today->copy();

                // Si hoy es el día mencionado, usar hoy
                if ($start->dayOfWeek === $dayOfWeek) {
                    // Ya estamos en el día correcto
                }
                // Si el día mencionado ya pasó esta semana, ir a la próxima semana
                elseif ($start->dayOfWeek > $dayOfWeek) {
                    $start->next($dayOfWeek);
                }
                // Si el día mencionado aún no ha llegado esta semana
                else {
                    $start->next($dayOfWeek);
                }

                $end = $start->copy()->endOfDay();
            }
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Obtiene eventos en un rango de fechas
     */
    private function getEventsInRange($start, $end)
    {
        return CalendarEvent::where('user_id', auth()->id())
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->orderBy('start_date')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->format('Y-m-d H:i'),
                    'end_date' => $event->end_date->format('Y-m-d H:i'),
                    'all_day' => $event->all_day,
                    'category' => $event->category,
                    'location' => $event->location,
                ];
            });
    }

    /**
     * Obtiene el cliente de la API de OpenAI
     */
    protected function getOpenAIClient()
    {
        $apiCredentials = $this->getApiCredentials();
        Log::info('API Credentials retrieved', ['credentials' => $apiCredentials]);
        $apiKey = $apiCredentials['api_key'] ?? config('services.openai.api_key', '');

        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        return $apiKey;
    }

    /**
     * Generates a response from OpenAI based on the given messages.
     */
    protected function generateOpenAIResponse($messages)
    {
        $apiKey = $this->getOpenAIClient();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.models.vision'),
                'messages' => $messages,
                'max_tokens' => 500,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['choices'][0]['message']['content'] ?? 'No se recibió respuesta válida de OpenAI.';
            } else {
                Log::error('Error en la respuesta de OpenAI: '.$response->status());
                throw new \Exception('Error al generar respuesta de OpenAI');
            }
        } catch (\Exception $e) {
            Log::error('Excepción al generar respuesta de OpenAI: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generates an audio file from the given text using OpenAI.
     */
    protected function generateAudio($text)
    {
        $apiKey = $this->getOpenAIClient();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/audio/speech', [
                'model' => config('ai.models.tts'),
                'input' => $text,
                'voice' => 'echo',
                'output_format' => 'mp3',
            ]);

            if ($response->successful()) {
                // Guardar en carpeta dinámica
                $fileName = 'audio/dynamic/'.uniqid().'.mp3';

                // Asegurar que existe el directorio
                if (! \Illuminate\Support\Facades\Storage::disk('public')->exists('audio/dynamic')) {
                    \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('audio/dynamic');
                }

                \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $response->body());
                $audioUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($fileName);

                return $audioUrl;
            } else {
                Log::error('Error generando audio de OpenAI: '.$response->status());
                throw new \Exception('Error al generar audio de OpenAI');
            }
        } catch (\Exception $e) {
            Log::error('Excepción generando audio: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesa una solicitud de voz y devuelve una respuesta
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processVoiceRequest(Request $request)
    {
        try {
            // Validar que el comando de voz existe
            $validator = Validator::make($request->all(), [
                'voice_command' => 'required|string|min:3',
            ]);

            if ($validator->fails()) {
                \App\Support\AiLog::error('Validación fallida', ['errors' => $validator->errors()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Comando de voz inválido',
                    'voice_message' => 'No pude entender tu comando. Por favor, intenta de nuevo.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $voiceText = $request->voice_command;
            \App\Support\AiLog::info('Procesando comando de voz:', ['text' => $voiceText]);

            // Detectar intención
            $response = ['intent' => $this->detectIntent($voiceText)];

            // Procesar según la intención
            if ($response['intent'] === 'create_event') {
                $response['partial_event'] = $this->parseEventDetails($voiceText);
                \App\Support\AiLog::info('Evento parseado:', $response['partial_event']);
            } elseif ($response['intent'] === 'query_events') {
                $dateRange = $this->extractDateRangeFromQuery($voiceText);
                $events = $this->getEventsInRange($dateRange['start'], $dateRange['end']);
                $response['events'] = $events;
                \App\Support\AiLog::info('Eventos encontrados para consulta de voz:', ['count' => $events->count()]);
            }

            // Generar respuesta de voz
            $response['voice_message'] = $this->generateVoiceResponse($response);
            try {
                $response['audio_url'] = $this->generateAudio($response['voice_message']);
            } catch (\Exception $e) {
                \App\Support\AiLog::error('Error generando audio', ['error' => $e->getMessage()]);
                $response['audio_url'] = null;
            }

            return response()->json(array_merge(['success' => true], $response));

        } catch (\Exception $e) {
            \App\Support\AiLog::error('Error procesando comando de voz', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el comando de voz',
                'voice_message' => 'Lo siento, ocurrió un error al procesar tu solicitud.',
                'error' => 'voice_request_failed',
            ], 500);
        }
    }

    /**
     * Interpreta un comando de voz y determina la acción a realizar
     */
    private function interpretVoiceCommand($command, $user)
    {
        $lowercaseCommand = strtolower($command);

        // Comando de bienvenida
        if ($lowercaseCommand === 'bienvenida') {
            return [
                'message' => 'Bienvenido a tu agenda, te saluda Cirilo tu asistente',
                'voice_message' => 'Hola, soy tu asistente Cirilo, ¿como estas? Puedes decirme "eventos del día 11 o cualquier día del mes actual, si deseas otro mes deberás decirlo como eventos del 5 de junio." o "crear nuevo evento" para agendar una cita. ejemplo "Crea el evento Almuerzo hoy a las 12 de medio día" ¿Dime, en qué puedo ayudarte?',
                'intent' => 'welcome',
            ];
        }

        // Detectar intención de consultar eventos para una fecha
        if (preg_match('/eventos.*(hoy|mañana|para el|del|día|fecha|\d+)/i', $lowercaseCommand)) {
            // Extraer fecha de la consulta
            $dateInfo = $this->extractDateFromQuery($lowercaseCommand);

            if ($dateInfo) {
                $events = $this->getEventsForDate($dateInfo);

                return $this->formatEventsResponse($events, $dateInfo);
            }
        }

        // Detectar intención de crear un evento
        if (preg_match('/(crear|nuevo|agregar|añadir|agendar).*(evento|cita|reunión)/i', $lowercaseCommand)) {
            // Extraer información básica para crear un evento
            $eventInfo = $this->extractEventInfo($lowercaseCommand);

            return [
                'message' => 'Perfecto, voy a ayudarte a crear un nuevo evento. He prellenado algunos campos con la información que mencionaste.',
                'voice_message' => 'Perfecto, voy a ayudarte a crear un nuevo evento. He prellenado algunos campos con la información que mencionaste. Por favor completa los detalles restantes.',
                'intent' => 'create_event',
                'partial_event' => $eventInfo,
            ];
        }

        // Respuesta por defecto
        return [
            'message' => 'No pude entender completamente tu solicitud. Intenta decir "eventos del día 15" o "crear nuevo evento".',
            'voice_message' => 'No pude entender completamente tu solicitud. Puedes consultar eventos diciendo "eventos del día 15" para una fecha específica, o "crear nuevo evento" para agendar una cita. ¿Podrías repetir tu solicitud?',
            'intent' => 'unknown',
        ];
    }

    /**
     * Extrae información de fecha de una consulta de voz
     */
    private function extractDateFromQuery($query)
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // Patrones comunes de fecha
        if (preg_match('/\bhoy\b/i', $query)) {
            return $today->format('Y-m-d');
        }

        if (preg_match('/\bmañana\b/i', $query)) {
            return $tomorrow->format('Y-m-d');
        }

        // Buscar número de día (ej: "eventos del 11", "eventos del día 15")
        if (preg_match('/\b(?:del|día)\s+(\d{1,2})\b/i', $query, $matches)) {
            $day = (int) $matches[1];
            $currentMonth = $today->month;
            $currentYear = $today->year;

            try {
                // Crear la fecha con el día especificado del mes actual
                $targetDate = Carbon::createFromDate($currentYear, $currentMonth, $day);

                // SIEMPRE usar el mes actual, sin importar si ya pasó la fecha
                // El usuario puede querer ver eventos pasados del mes actual
                return $targetDate->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('Día inválido para el mes actual', ['day' => $day, 'month' => $currentMonth]);

                // Fallback al día actual
                return $today->format('Y-m-d');
            }
        }

        // Buscar solo un número (ej: "eventos 15")
        if (preg_match('/\b(\d{1,2})\b/i', $query, $matches)) {
            $day = (int) $matches[1];
            if ($day >= 1 && $day <= 31) {
                $currentMonth = $today->month;
                $currentYear = $today->year;

                try {
                    $targetDate = Carbon::createFromDate($currentYear, $currentMonth, $day);

                    // SIEMPRE usar el mes actual, igual que el patrón anterior
                    return $targetDate->format('Y-m-d');
                } catch (\Exception $e) {
                    // Día inválido para el mes actual
                }
            }
        }

        // Intentar extraer una fecha con mes (ej: "15 de enero")
        try {
            if (preg_match('/(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)\b/i', $query, $matches)) {
                $day = (int) $matches[1];
                $month = $this->spanishMonthToNumber($matches[2]);
                $year = (int) date('Y'); // Año actual

                return Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            Log::error('Error al extraer fecha de la consulta', ['error' => $e->getMessage(), 'query' => $query]);
        }

        // Si no se pudo extraer una fecha, devolver la fecha actual
        return $today->format('Y-m-d');
    }

    /**
     * Convierte nombre de mes en español a número
     */
    private function spanishMonthToNumber($month)
    {
        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        return $months[strtolower($month)] ?? date('n'); // Mes actual como fallback
    }

    /**
     * Extrae información básica para crear un evento desde un comando de voz
     */
    private function extractEventInfo($command)
    {
        $eventInfo = [
            'title' => '',
            'start_date' => Carbon::today()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'description' => '',
        ];

        // Extraer título potencial - mejores patrones
        if (preg_match('/(crear|nuevo|agregar|añadir|agendar)\s+(evento|cita|reunión)\s+(llamado|con nombre|titulado|sobre|para|de)?\s*([\w\s]+?)(?:\s+(?:para|el|a las|desde)\s|$)/i', $command, $matches)) {
            $eventInfo['title'] = trim($matches[4]);
        } elseif (preg_match('/(crear|nuevo|agregar|añadir|agendar)\s+(evento|cita|reunión)\s+([\w\s]+?)(?:\s+(?:para|el|a las|desde)\s|$)/i', $command, $matches)) {
            $eventInfo['title'] = trim($matches[3]);
        }

        // Extraer hora específica (formato de 24 horas o 12 horas)
        if (preg_match('/(a las |a la |)(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $command, $matches)) {
            $hour = $matches[2];
            $minute = $matches[3] ?? '00';
            $meridiem = strtolower($matches[4] ?? '');

            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($meridiem === 'am' && $hour == 12) {
                $hour = 0;
            }

            $eventInfo['start_time'] = sprintf('%02d:%02d', $hour, $minute);
            // Duración predeterminada de 1 hora
            $endHour = ($hour + 1) % 24;
            $eventInfo['end_time'] = sprintf('%02d:%02d', $endHour, $minute);
        }

        // Extraer fecha si está presente
        try {
            $dateStr = $this->extractDateFromQuery($command);
            if ($dateStr) {
                $eventInfo['start_date'] = $dateStr;
            }
        } catch (\Exception $e) {
            // Usar la fecha actual como fallback
        }

        // Extraer descripción adicional
        if (preg_match('/(?:sobre|acerca de|para|tema)\s+([\w\s]+?)(?:\s+(?:el|para|a las)\s|$)/i', $command, $matches)) {
            $eventInfo['description'] = trim($matches[1]);
        }

        // Si no se pudo extraer un título, usar uno genérico
        if (empty($eventInfo['title'])) {
            $eventInfo['title'] = 'Nuevo evento';
        }

        return $eventInfo;
    }

    /**
     * Formatea la respuesta para consultas de eventos
     */
    private function formatEventsResponse($events, $dateStr)
    {
        try {
            $date = Carbon::parse($dateStr);
            $formattedDate = $date->format('d \\d\\e F \\d\\e Y');

            if ($events->isEmpty()) {
                return [
                    'message' => "No tienes eventos programados para el {$formattedDate}.",
                    'voice_message' => "No tienes eventos programados para el {$date->format('d \\d\\e F')}.",
                    'intent' => 'query_result',
                    'events' => [],
                ];
            } else {
                $message = "Estos son tus eventos para el {$formattedDate}:\n";
                $voiceMessage = 'Tienes '.$events->count()." eventos para el {$date->format('d \\d\\e F')}: ";

                foreach ($events as $index => $event) {
                    $startTime = Carbon::parse($event->start_date)->format('H:i');
                    $message .= ($index + 1).". {$event->title} a las {$startTime}.\n";
                    $voiceMessage .= ($index + 1).". {$event->title} a las {$startTime}. ";
                }

                return [
                    'message' => $message,
                    'voice_message' => $voiceMessage,
                    'intent' => 'query_result',
                    'events' => $events->toArray(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error al formatear respuesta de eventos', ['error' => $e->getMessage()]);

            return [
                'message' => 'Lo siento, ocurrió un error al procesar la información de tus eventos.',
                'voice_message' => 'Lo siento, ocurrió un error al procesar la información de tus eventos.',
                'intent' => 'error',
                'events' => [],
            ];
        }
    }

    protected function parseEventDetails($text)
    {
        // Log de entrada
        \App\Support\AiLog::info('Parseando detalles de evento desde texto:', ['text' => $text]);

        // Fecha por defecto es hoy en formato Y-m-d
        $eventInfo = ['title' => 'Evento sin nombre', 'date' => date('Y-m-d'), 'time' => ''];

        // Nuevos patrones mejorados para título
        $titlePatterns = [
            '/crear (?:evento|cita|reunión) (?:nuevo )?llamado (.+?) (?:el|para|hoy|mañana|a las)/i',
            '/crear (?:evento|cita|reunión) (?:nuevo )?(.+?) (?:el|para|hoy|mañana|a las)/i',
            '/(?:nuevo evento|crear) (.+?) a las/i',
        ];

        foreach ($titlePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $eventInfo['title'] = trim($matches[1]);
                break;
            }
        }

        // Extraer fecha (mantener lógica existente)
        if (preg_match('/(hoy|mañana|el (\d{1,2}) de (enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)|(\d{4}-\d{2}-\d{2}))/i', $text, $dateMatches)) {
            $date = strtolower($dateMatches[1]);

            if ($date === 'hoy') {
                $eventInfo['date'] = date('Y-m-d');
            } elseif ($date === 'mañana') {
                $eventInfo['date'] = date('Y-m-d', strtotime('+1 day'));
            } elseif (isset($dateMatches[2]) && isset($dateMatches[3])) {
                // Formato "el 15 de julio"
                $eventInfo['date'] = date('Y-m-d', strtotime($dateMatches[2].' '.$dateMatches[3]));
            } else {
                $eventInfo['date'] = $dateMatches[1]; // Formato YYYY-MM-DD
            }
        }

        // Extraer hora (mantener lógica existente)
        if (preg_match('/(a las |a la |)(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $text, $timeMatches)) {
            $hour = $timeMatches[2];
            $minute = $timeMatches[3] ?? '00';
            $meridiem = strtolower($timeMatches[4] ?? '');

            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($meridiem === 'am' && $hour == 12) {
                $hour = 0;
            }

            $eventInfo['time'] = sprintf('%02d:%02d', $hour, $minute);
        }

        \App\Support\AiLog::info('Resultado de parseo:', $eventInfo);

        return $eventInfo;
    }

    /**
     * Detecta la intención de un comando de voz
     *
     * @param  string  $command
     * @return string
     */
    private function detectIntent($command)
    {
        $command = strtolower($command);

        // Patrones para creación de eventos
        $createPatterns = [
            '/crea(r)? (un |)evento/',
            '/nuevo evento/',
            '/agenda(r)? (un |)(evento|cita|reunión)/',
            '/programa(r)? (una? |)(cita|reunión|evento)/',
            '/añade(r)? (un |)evento/',
            '/agrega(r)? (un |)evento/',
        ];

        foreach ($createPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return 'create_event';
            }
        }

        // Patrones para consulta de eventos
        $queryPatterns = [
            '/qué tengo|que tengo/',
            '/mis eventos/',
            '/tengo algo/',
            '/agenda de|agenda del|agenda para/',
            '/eventos (de|del|para|hoy|mañana|esta)/',
            '/calendario/',
            '/mis citas/',
            '/qué hay|que hay/',
            '/muéstrame|muestrame/',
            '/ver eventos/',
            '/consultar/',
            '/qué tengo programado|que tengo programado/',
        ];

        foreach ($queryPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return 'query_events';
            }
        }

        return 'unknown';
    }

    /**
     * Genera una respuesta de voz basada en la intención detectada
     *
     * @param  array  $response
     * @return string
     */
    private function generateVoiceResponse($response)
    {
        switch ($response['intent']) {
            case 'welcome':
                return 'Hola, soy tu asistente de agenda. ¿En qué puedo ayudarte hoy?';

            case 'create_event':
                $event = $response['partial_event'] ?? [];
                $title = $event['title'] ?? 'sin título';
                $date  = $event['date']  ?? '';
                $time  = $event['time']  ?? '';

                $msg = "Abriendo el formulario para crear el evento: {$title}";
                if ($date) $msg .= ", para el {$date}";
                if ($time) $msg .= " a las {$time}";
                $msg .= '. Por favor revisa y guarda los datos.';

                return $msg;

            case 'query_events':
                $events = $response['events'] ?? collect();

                if ($events->isEmpty()) {
                    return 'No tienes eventos programados para ese período.';
                }

                $count = $events->count();
                $msg = "Tienes {$count} " . ($count === 1 ? 'evento' : 'eventos') . ': ';
                foreach ($events->take(5) as $i => $e) {
                    $startDt = Carbon::parse($e['start_date'])->format('H:i');
                    $msg .= ($i + 1) . '. ' . $e['title'] . ' a las ' . $startDt . '. ';
                }
                if ($count > 5) {
                    $msg .= 'Y ' . ($count - 5) . ' más. Revisa tu agenda para ver todos.';
                }

                return $msg;

            default:
                return 'No entendí tu solicitud. Puedes decir "crear evento" o "qué tengo hoy".';
        }
    }

    // ── Integración Nextcloud CalDAV ─────────────────────────────────────────

    /**
     * Sincroniza un evento individual con el calendario Nextcloud del usuario.
     */
    public function syncToNextcloud(Request $request, int $id)
    {
        $user  = Auth::user();
        $event = CalendarEvent::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (! $user->hasNextcloud()) {
            return response()->json(['success' => false, 'message' => 'Nextcloud no configurado en tu perfil'], 422);
        }

        $result = (new NextcloudCalendarService($user))->pushEvent($event);

        if ($result['success']) {
            $event->update(['nextcloud_synced' => true, 'nextcloud_uid' => $result['uid']]);
        }

        return response()->json([
            'success'          => $result['success'],
            'nextcloud_synced' => $result['success'],
            'message'          => $result['success']
                ? 'Evento sincronizado con Nextcloud'
                : 'No se pudo sincronizar: ' . ($result['error'] ?? ''),
        ]);
    }

    /**
     * Sincroniza todos los eventos de una serie con Nextcloud.
     */
    public function syncSeriesToNextcloud(Request $request, string $seriesId)
    {
        $user = Auth::user();

        if (! $user->hasNextcloud()) {
            return response()->json(['success' => false, 'message' => 'Nextcloud no configurado en tu perfil'], 422);
        }

        // Verificar que la serie pertenece al usuario
        $owns = CalendarEvent::where('series_id', $seriesId)->where('user_id', $user->id)->exists();
        if (! $owns) {
            return response()->json(['success' => false, 'message' => 'Serie no encontrada'], 404);
        }

        $result = (new NextcloudCalendarService($user))->pushSeries($seriesId, $user->id);

        return response()->json([
            'success' => $result['failed'] === 0,
            'synced'  => $result['synced'],
            'failed'  => $result['failed'],
            'message' => $result['failed'] === 0
                ? "{$result['synced']} eventos sincronizados con Nextcloud"
                : "{$result['synced']} sincronizados, {$result['failed']} fallidos",
        ]);
    }

    /**
     * Verifica la conexión Nextcloud del usuario y retorna los calendarios disponibles.
     */
    public function testNextcloudConnection()
    {
        $user = Auth::user();

        if (! $user->hasNextcloud()) {
            return response()->json(['success' => false, 'message' => 'Configura primero la URL, usuario y contraseña de Nextcloud'], 422);
        }

        $result = (new NextcloudCalendarService($user))->testConnection();

        return response()->json($result);
    }
}
