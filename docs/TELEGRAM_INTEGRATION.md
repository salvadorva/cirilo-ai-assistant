# Integración Telegram via n8n

Envío de recordatorios de agenda al Telegram del usuario, enrutados a través de n8n.

---

## Arquitectura

```
Scheduler Laravel (cada 5 min)
  → TelegramNotificationService::send()
    → POST webhook n8n  (X-Webhook-Secret)
      → n8n valida secret
        → n8n envía mensaje via Telegram Bot API
          → Usuario recibe notificación en Telegram
```

---

## Variables de entorno (producción)

```env
N8N_TELEGRAM_WEBHOOK_URL=https://n8n.tudominio.com/webhook/telegram-asistente
N8N_WEBHOOK_SECRET=tu_token_secreto_generado
TELEGRAM_BOT_USERNAME=@NombreDelBot
```

Generar un token seguro:
```bash
openssl rand -hex 32
```

---

## Credenciales del sistema n8n

| Credencial | Descripción |
|---|---|
| `X-Webhook-Secret` | Header de autenticación entre Laravel y n8n (mismo valor en `.env` y en el Webhook de n8n) |
| Bot Token | Token del bot obtenido en @BotFather — solo vive en n8n como credencial Telegram |

---

## Workflows n8n

### Workflow 1 — Auto-registro (cuando usuario escribe `/start`)
El bot responde con el `chat_id` del usuario para que lo pegue en `/settings`.

### Workflow 2 — Envío de recordatorios
**Endpoint:** `POST /webhook/telegram-asistente`

**Header requerido:** `X-Webhook-Secret: <token>`

**Payload enviado por Laravel:**
```json
{
  "chat_id": "987654321",
  "message": "⏰ *Recordatorio de Agenda*\n\n📅 *Reunión con cliente*\n🕐 A las 15:00\n⏳ Comienza en 30 minutos",
  "event_title": "Reunión con cliente",
  "event_start": "2026-03-30 15:00:00",
  "reminder_minutes": 30,
  "type": "reminder"
}
```

**Prueba manual con curl:**
```bash
curl -X POST http://44.199.92.76:5678/webhook/telegram-asistente \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: 47b9b975993ebe69f4edd7acfd3534c5dc5cdc294559abd19eb685a12dce655" \
  -d '{
    "chat_id": "TU_CHAT_ID",
    "message": "⏰ *Prueba de notificación*\n\nSi ves esto, la integración funciona.",
    "type": "test"
  }'
```

---

## Configuración del usuario

En `/settings` → sección **Telegram**:
1. Buscar el bot en Telegram y enviar `/start`
2. El bot responde con el Chat ID
3. Pegar el Chat ID y activar el toggle
4. Guardar

---

## Independencia de canales

| Canal | Toggle que lo controla | Restricción horaria |
|-------|----------------------|---------------------|
| Email sistema | `email_notifications_enabled` | No |
| Email agenda | `agenda_reminders_enabled` | Sí (7-18h) |
| Telegram agenda | `telegram_notifications_enabled` | No |

Cada canal es completamente independiente. El usuario puede tener solo email, solo Telegram, o ambos.

---

## Logs relevantes

```
# Envío exitoso
TelegramNotificationService: mensaje enviado {"user_id":1,"chat_id":"...","type":"reminder"}

# Webhook no configurado
TelegramNotificationService: N8N_TELEGRAM_WEBHOOK_URL no configurado — omitiendo envío

# Error en webhook
TelegramNotificationService: respuesta no exitosa del webhook {"status":400,"body":"..."}
```
