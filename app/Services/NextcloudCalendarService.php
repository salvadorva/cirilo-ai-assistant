<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject\Component\VCalendar;

class NextcloudCalendarService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $calendar;

    public function __construct(User $user)
    {
        $this->baseUrl  = rtrim($user->nextcloud_url, '/');
        $this->username = $user->nextcloud_username;
        $this->password = $user->nextcloud_password;
        $this->calendar = $user->nextcloud_calendar ?? 'personal';
    }

    /**
     * Crea o actualiza un evento en Nextcloud.
     * Retorna ['success'=>bool, 'uid'=>string, 'error'=>?string]
     */
    public function pushEvent(CalendarEvent $event): array
    {
        $uid = $event->getNextcloudUid();
        $ics = $this->buildIcs($event, $uid);
        $url = $this->buildUrl($uid);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders(['Content-Type' => 'text/calendar; charset=utf-8'])
                ->withBody($ics, 'text/calendar')
                ->timeout(15)
                ->put($url);

            $ok = $response->successful() || $response->status() === 201;

            if (! $ok) {
                Log::warning('NextcloudCalendarService: PUT fallido', [
                    'status'   => $response->status(),
                    'event_id' => $event->id,
                    'url'      => $url,
                ]);
            }

            return ['success' => $ok, 'uid' => $uid, 'error' => $ok ? null : "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            Log::error('NextcloudCalendarService: excepción en pushEvent', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return ['success' => false, 'uid' => $uid, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sincroniza todos los eventos de una serie.
     * Retorna ['synced'=>int, 'failed'=>int, 'errors'=>array]
     */
    public function pushSeries(string $seriesId, int $userId): array
    {
        $events  = CalendarEvent::where('series_id', $seriesId)
            ->where('user_id', $userId)
            ->orderBy('start_date')
            ->get();

        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($events as $event) {
            $result = $this->pushEvent($event);
            if ($result['success']) {
                $event->update(['nextcloud_synced' => true, 'nextcloud_uid' => $result['uid']]);
                $synced++;
            } else {
                $failed++;
                $errors[] = $result['error'];
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Elimina un evento de Nextcloud.
     */
    public function deleteEvent(CalendarEvent $event): bool
    {
        $uid = $event->getNextcloudUid();
        $url = $this->buildUrl($uid);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(10)
                ->delete($url);

            // 404 = ya no existe en Nextcloud, se trata como éxito
            return $response->successful() || $response->status() === 404;
        } catch (\Exception $e) {
            Log::warning('NextcloudCalendarService: excepción en deleteEvent', ['error' => $e->getMessage(), 'event_id' => $event->id]);
            return false;
        }
    }

    /**
     * Verifica que las credenciales del usuario conecten correctamente.
     * Retorna ['success'=>bool, 'calendars'=>array, 'error'=>?string]
     */
    public function testConnection(): array
    {
        $url = "{$this->baseUrl}/remote.php/dav/calendars/{$this->username}/";

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->timeout(10)
                ->withHeaders([
                    'Depth'        => '1',
                    'Content-Type' => 'application/xml',
                ])
                ->send('PROPFIND', $url, ['body' => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>']);

            if (! $response->successful() && $response->status() !== 207) {
                return ['success' => false, 'calendars' => [], 'error' => "HTTP {$response->status()}"];
            }

            // Extraer slugs de calendarios del XML
            preg_match_all('|/remote\.php/dav/calendars/[^/]+/([^/]+)/|', $response->body(), $matches);
            $calendars = array_values(array_unique(array_filter($matches[1], fn($s) => $s !== '')));

            return ['success' => true, 'calendars' => $calendars, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'calendars' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function buildUrl(string $uid): string
    {
        return "{$this->baseUrl}/remote.php/dav/calendars/{$this->username}/{$this->calendar}/{$uid}.ics";
    }

    private function buildIcs(CalendarEvent $event, string $uid): string
    {
        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT');

        $vevent->add('UID',     $uid);
        $vevent->add('DTSTAMP', Carbon::now()->utc()->format('Ymd\THis\Z'));
        $vevent->add('SUMMARY', $event->title ?? '');

        if ($event->description) {
            $vevent->add('DESCRIPTION', $event->description);
        }
        if ($event->location) {
            $vevent->add('LOCATION', $event->location);
        }

        $start = Carbon::parse($event->start_date);
        $end   = $event->end_date
            ? Carbon::parse($event->end_date)
            : $start->copy()->addHour();

        if ($event->all_day) {
            $vevent->add('DTSTART', $start->format('Ymd'));
            $vevent->DTSTART['VALUE'] = 'DATE';
            // CalDAV requiere DTEND = día siguiente para eventos de todo el día
            $vevent->add('DTEND', $end->copy()->addDay()->format('Ymd'));
            $vevent->DTEND['VALUE'] = 'DATE';
        } else {
            $vevent->add('DTSTART', $start->utc()->format('Ymd\THis\Z'));
            $vevent->add('DTEND',   $end->utc()->format('Ymd\THis\Z'));
        }

        return $vcal->serialize();
    }
}
