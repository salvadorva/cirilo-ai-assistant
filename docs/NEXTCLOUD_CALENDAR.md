# Integración CalDAV — Nextcloud Calendar

## Descripción

Los eventos de agenda creados en Cirilo (formulario o chat) pueden sincronizarse automáticamente
con el calendario Nextcloud del usuario. Al crear un evento, aparece un modal que pregunta si es
**personal** o **de trabajo**. Si es de trabajo, se hace un PUT CalDAV al calendario configurado.

## Configuración por usuario

Cada usuario configura sus propias credenciales desde **Configuración → Nextcloud Calendar**.

| Campo | Descripción |
|---|---|
| URL de Nextcloud | `https://cloud.example.com` |
| Usuario | nombre de usuario en Nextcloud |
| App Password | generado en Nextcloud → Configuración → Seguridad → App passwords |
| Calendario | slug del calendario de trabajo (ej: `personal`) |

> **Importante:** usar un App Password, no la contraseña principal.
> La contraseña se almacena cifrada en la BD (AES-256).

## Descubrir el slug del calendario

```bash
curl -u usuario:app_password \
  -X PROPFIND -H "Depth: 1" -H "Content-Type: application/xml" \
  https://cloud.example.com/remote.php/dav/calendars/usuario/ \
  2>/dev/null | grep -o 'href>[^<]*</d:href'
```

La respuesta muestra paths como `/remote.php/dav/calendars/usuario/personal/`.
El slug es la última parte: `personal`.

## Endpoint CalDAV

```
PUT https://{NEXTCLOUD_URL}/remote.php/dav/calendars/{username}/{calendar}/{uid}.ics
```

UID de cada evento: `asistente-{id}@asistente.example.com`

## Flujo completo

```
1. Usuario crea evento (formulario /agenda o chat con Cirilo)
2. Evento se guarda en BD local
3. Modal: "¿Personal o de trabajo?"
   ├── Personal → solo en BD local
   └── Trabajo  → POST /agenda/events/{id}/sync-nextcloud
                      ↓
                  NextcloudCalendarService::pushEvent()
                      ↓
                  PUT CalDAV con iCal RFC 5545
                      ↓
                  calendar_events.nextcloud_synced = true

4. Al eliminar el evento → DELETE CalDAV automático (si estaba sincronizado)
```

## Archivos relevantes

| Archivo | Rol |
|---|---|
| `app/Services/NextcloudCalendarService.php` | Lógica CalDAV: push, delete, test |
| `app/Http/Controllers/AgendaController.php` | syncToNextcloud, syncSeriesToNextcloud, testNextcloudConnection |
| `app/Http/Controllers/UserSettingsController.php` | updateNextcloud, testNextcloud |
| `app/Models/User.php` | hasNextcloud(), encrypted cast de nextcloud_password |
| `app/Models/CalendarEvent.php` | getNextcloudUid(), nextcloud_synced, nextcloud_uid |
| `resources/views/settings/show.blade.php` | Formulario de configuración + botón de prueba |
| `resources/views/layout/app.blade.php` | Modal global personal/trabajo + JS handler |
| `resources/views/agenda/partials/calendar-scripts.blade.php` | Dispara CustomEvent tras crear evento |
| `resources/views/home/conversar.blade.php` | Dispara CustomEvent si chat crea evento |

## Rutas

| Método | URI | Descripción |
|---|---|---|
| POST | `/agenda/events/{id}/sync-nextcloud` | Sincronizar evento individual |
| POST | `/agenda/series/{seriesId}/sync-nextcloud` | Sincronizar serie completa |
| GET | `/agenda/nextcloud/test-connection` | Probar conexión (desde agenda) |
| POST | `/settings/nextcloud` | Guardar credenciales |
| GET | `/settings/nextcloud-test` | Probar conexión (desde settings) |

## Comandos de diagnóstico

```bash
# Verificar credenciales manualmente
curl -u usuario:app_password \
  https://cloud.example.com/remote.php/dav/calendars/usuario/ -v 2>&1 | grep "< HTTP"
# Esperado: HTTP/2 200 o HTTP/2 207

# Ver eventos sincronizados en BD
php artisan tinker --execute="App\Models\CalendarEvent::where('nextcloud_synced',true)->count();"
```

## Revocar App Password

`https://cloud.example.com/settings/user/security` → App passwords → revocar `laravel-asistente`.
