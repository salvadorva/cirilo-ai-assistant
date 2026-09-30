# Contrato v1 de recordatorios contextuales (RC0)

Fixtures compartidas con el cliente de Hermes y el repositorio Android. Reloj de referencia:
`2026-09-21T14:00:00Z` (08:00 America/Guatemala). Datos ficticios.

| Archivo | Uso |
| --- | --- |
| `hermes-create-request.json` | `POST /api/integrations/hermes/v1/reminders` |
| `hermes-create-response.json` | `201`: confirmación mínima persistida, sin contexto |
| `reminder-detail-response.json` | `GET /{id}` (Hermes) y `GET /api/mobile/contextual-reminders/{id}` |
| `conflict-response.json` | `409` con `code` estable y estado `current` |
| `push-contextual-reminder-v1.json` | Payload FCM data-only v1, **congelado** en RC3 y verificado contra el despachador |
| `device-registration-request.json` | Registro de instalación y capacidad (`POST /api/mobile/device-token`, junto con `token`) |
| `receipt-request.json` | Recibo `received`/`displayed` (`POST /api/mobile/contextual-reminders/{id}/receipts`) |

`ContextualReminderContractTest` compara las claves de estas fixtures con las respuestas reales. Cambiar
una clave exige nueva versión del contrato y coordinación con Android/Hermes; no actualizar la fixture
para volver verde la suite. Los valores `<uuid>`/`<request-id>` son variables.

Cambio coordinado del 23/09/2026 (RC2, antes de publicar v1): `dispatch` pasa a `{state, error_category}` con el
estado real de la revisión vigente (`not_started`, `pending`, `processing`, `retry_wait`, `accepted`, `failed`,
`uncertain`, `skipped`). `accepted` significa aceptado por FCM, no mostrado.

Cambio coordinado del 23/09/2026 (RC3/RC4, antes de publicar v1): el detalle añade `audio_ready`, `actions`
(`complete`, `cancel`, `snooze_minutes` acotados por la caducidad) y `delivery_receipt` (`received_at`, `displayed_at`;
nulo significa desconocido). Contrato completo para Android: [docs/CONTRATO-ANDROID-RECORDATORIOS.md](../../../docs/CONTRATO-ANDROID-RECORDATORIOS.md).
