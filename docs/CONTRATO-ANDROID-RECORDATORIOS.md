# Contrato Android v1 — recordatorios contextuales (RC3)

Fecha: 23/09/2026 (revisión v1.1 el mismo día; ver [§8](#8-cambios-de-contrato-v11--android-debe-implementarlos)). Estado: **backend implementado y probado en local; app Android pendiente**. El repo Android está fuera de este árbol y no se tocó. Este documento fija lo que la app debe cumplir para que el backend la considere un destino compatible. Fixtures de referencia: [tests/Fixtures/contextual-reminders/](../tests/Fixtures/contextual-reminders/README.md).

Decisiones del piloto que aplican: un solo teléfono, **solo texto y sin audio**, aviso genérico en pantalla bloqueada, detalle solo al abrir la app autenticada, caducidad de 90 min sugerida (máximo 4 h), horizonte de 7 días, 5 pendientes y 10 altas por día. El contenido se retira 7 días después del cierre y los metadatos se conservan 30 días.

## 1. Registro de instalación y capacidad

`POST /api/mobile/device-token` (Sanctum). Ejemplo: [device-registration-request.json](../tests/Fixtures/contextual-reminders/device-registration-request.json) + `token`.

| Campo | Regla |
| --- | --- |
| `token` | Token FCM actual (obligatorio, como hasta ahora) |
| `installation_id` | UUID generado **una vez** por instalación y persistido en la app; `^[A-Za-z0-9-]{8,100}$`. No es el token FCM ni el ID de Android |
| `capabilities` | Arreglo (≤ 10). Enviar `contextual_reminders_v1` **solo** si el receptor de esta sección está implementado. El backend guarda solo las capacidades que conoce |
| `app_version`, `platform` | Opcionales |

- Registrar al iniciar sesión, al rotar el token (`onNewToken`) y al actualizar la app. Rotar el token actualiza la misma instalación.
- Omitir `capabilities` con `installation_id` equivale a no tener capacidades: una app degradada deja de recibir recordatorios contextuales.
- **Apps antiguas**, sin `installation_id`: se mantiene el comportamiento anterior y nunca reciben `contextual_reminder`.
- `409 installation_conflict`: esa instalación o token pertenece a otra cuenta. Al cerrar sesión hay que llamar a `DELETE /api/mobile/device-token`. Si ese DELETE no llegó (por ejemplo, sin red), la nueva cuenta usa el **reclamo explícito** de [§8.1](#81-reclamo-explícito-de-instalación-nuevo). El registro normal **nunca** transfiere una instalación en silencio.
- La respuesta incluye `contextual_reminders_ready`, que solo es `true` en el teléfono elegido para el piloto. La app no debe mostrar funciones contextuales si es `false`.

## 2. Push data-only `contextual_reminder` v1

Fixture congelada: [push-contextual-reminder-v1.json](../tests/Fixtures/contextual-reminders/push-contextual-reminder-v1.json). Todos los valores son strings, sin bloque `notification`, `android.priority=high` y `android.ttl` igual a los segundos que quedan hasta `expires_at`.

| Campo | Uso obligatorio en la app |
| --- | --- |
| `type` = `contextual_reminder`, `schema_version` = `1` | Enrutar. Ignorar versiones de esquema desconocidas sin fallar |
| `reminder_id` = `occurrence_id`, `version` | Identidad del aviso y clave de deduplicación `(occurrence_id, version)` |
| `scheduled_at`, `expires_at` | UTC. No mostrar si `now ≥ expires_at`. Retirar el aviso al caducar |
| `title`, `body` | Texto genérico («Cirilo» / «Tienes un recordatorio acordado»). Es **lo único** que puede verse en pantalla bloqueada |
| `audio_ready` | Siempre `"false"` en el piloto. No reproducir ni descargar audio |

El push nunca trae contexto, siguiente acción, URL ni token.

## 3. Recepción, deduplicación y expiración

1. `onMessageReceived` debe ser breve: validar, deduplicar y mostrar el aviso genérico. Las consultas van a `WorkManager`.
2. Deduplicación persistente, no en memoria: ignorar un `(occurrence_id, version)` ya procesado y cualquier versión menor que la última conocida de esa ocurrencia. Un reintento incierto del backend puede entregar el mismo push dos veces. No usar `collapse_key` como identidad.
3. Un aviso por ocurrencia (ID de notificación derivado de `occurrence_id`). Una versión mayor lo reemplaza sin volver a sonar.
4. Canal propio de recordatorios contextuales, separado del de `focus_message`. Sin forzar sonido ni saltar No molestar. Verificar `POST_NOTIFICATIONS` (Android 13+) y que el canal esté habilitado.
5. Visibilidad `VISIBILITY_PRIVATE` o una versión pública genérica. El contexto solo se muestra tras abrir la app con sesión válida (`GET /api/mobile/contextual-reminders/{id}`).
6. Al abrir la app o sincronizar, reconciliar con `GET /api/mobile/contextual-reminders?state=pending` y retirar los avisos cerrados o caducados. El listado trae `id`, `state`, `version`, horarios, `dispatch` y `title`, **sin** `context` ni `next_action` ([§8.2](#82-listado-sin-contexto-cambio)). El contexto se pide al detalle solo cuando se abre.

## 4. Acciones: Hecho, Posponer 15/30/60 y Cancelar

La notificación tiene tres acciones: **Hecho**, **Posponer** (abre un selector de 15, 30 o 60) y **Cancelar**. Todas usan intents explícitos ligados a `(id, version)`.

| Acción | Endpoint (Sanctum) | Body |
| --- | --- | --- |
| Hecho | `POST /api/mobile/contextual-reminders/{id}/complete` | `{"expected_version": v}` |
| Cancelar | `POST …/{id}/cancel` | `{"expected_version": v}` |
| Posponer | `POST …/{id}/snooze` | `{"expected_version": v, "minutes": 15\|30\|60}` |

- La cabecera `Idempotency-Key` es obligatoria: un valor aleatorio de 16 a 100 caracteres, generado una vez por intención del usuario y persistido junto con la petición. Si hay timeout, se repite **la misma** petición con la misma clave. Un doble toque produce un solo efecto (`Idempotent-Replayed: true`).
- El detalle expone `actions` = `{complete, cancel, snooze_minutes}`. Solo se ofrecen las opciones de posponer que no alcanzan la caducidad. El servidor vuelve a validarlas.
- `409` (`version_conflict`, `invalid_state`, `reminder_expired`, `snooze_exceeds_expiry`) incluye `current`: hay que refrescar el aviso con ese estado. No reintentar forzando otra versión.
- Sin conexión, Hecho y Cancelar se muestran como «pendiente de sincronizar» y se reenvían después con la misma clave. Posponer exige conexión. Nunca afirmar como confirmada una acción que el servidor no confirmó.

## 5. Recibos mínimos

`POST /api/mobile/contextual-reminders/{id}/receipts` con [receipt-request.json](../tests/Fixtures/contextual-reminders/receipt-request.json): `event` = `received` o `displayed`, `version`, `installation_id`.
- Idempotente: prevalece la primera marca. Solo sirve de diagnóstico y **nunca** completa la tarea. `recorded: false` si no hubo despacho de esa versión a esa instalación.
- Si no llega recibo, el estado de entrega es **desconocido**, no fallido.

## 6. Pruebas que debe tener el repo Android

Tres acciones y el selector de 15/30/60; doble toque; push duplicado o desordenado; versión menor después de una mayor; app cerrada; reinicio del teléfono; sin conexión; permiso denegado o canal silenciado; Doze; sesión expirada; token renovado (misma `installation_id`); reloj incorrecto; aviso ya cancelado o caducado; app antigua sin la capacidad. **v1.1:** cierre de sesión sin red y reclamo confirmado por el usuario; reclamo con `403`, `404` y `429`; limpieza del estado local de la cuenta anterior antes de reclamar; logcat sin bearer, token, `installation_id` ni contexto en release.

## 7. Lo que el backend ya garantiza (probado en este árbol)

`ContextualReminderDeviceTest`, `DeviceInstallationClaimTest`, `ReminderDispatchTest`, `ContextualReminderApiTest` y `ContextualReminderContractTest` cubren:
- registro de instalación y capacidad, rotación de token, antiapropiación y degradación;
- envío solo a instalaciones compatibles y seleccionadas, con payload genérico y TTL restante;
- despacho único por revisión y destino, y reintentos acotados;
- acciones con versión e idempotencia, y opciones de posponer acotadas por la caducidad;
- recibos idempotentes que no cierran la tarea;
- vencimiento sin necesidad de tick del scheduler;
- recorrido local completo: registro → alta Hermes → despacho → recibo → Hecho → no vuelve a enviarse.
- reclamo explícito (v1.1): transferencia con prueba de posesión, idempotencia, invalidación de despachos y recibos del dueño anterior, denegación auditada, límite por cuenta e instalación, rechazo de credenciales Hermes y anónimas, liberación por operador y listado sin contexto.

## 8. Cambios de contrato v1.1 — ANDROID DEBE IMPLEMENTARLOS

> **Cambio de contrato (23/09/2026)**, originado por los hallazgos del agente Android y decidido por Salva. El backend ya lo implementa y prueba. **La app Android debe adoptar §8.1–§8.3 antes del piloto.** No hay otros cambios: el payload del push v1 sigue congelado.

### 8.1 Reclamo explícito de instalación (nuevo)

**Problema:** si se cierra sesión sin red, el `DELETE /device-token` no llega. La instalación queda ligada a la cuenta anterior y cualquier otra cuenta recibe `409 installation_conflict` indefinidamente.

`POST /api/mobile/device-token/claim` (**Sanctum** obligatorio; la credencial de Hermes y las peticiones anónimas reciben `401`). Cuerpo igual al del registro: `installation_id` y `token` son obligatorios; `capabilities`, `app_version` y `platform` son opcionales.

| Respuesta | Significado |
| --- | --- |
| `200 {claimed: true, already_owned: false, installation_id, capabilities, contextual_reminders_ready}` | Instalación transferida a la cuenta autenticada |
| `200 {claimed: false, already_owned: true, …}` | Ya era de esta cuenta. Es **idempotente**: repetir el reclamo no cambia nada |
| `403 possession_not_proven` | El `token` no es el token FCM vigente registrado para esa instalación |
| `404 installation_not_found` | No existe: usar el registro normal |
| `422` | Datos inválidos |
| `429 rate_limited` + `Retry-After` | Más de 5 intentos por hora por cuenta **o** por instalación |

Semántica y reglas para la app:
- **Explícito:** solo se llama tras recibir `409 installation_conflict` al registrar **y** con una acción consciente del usuario en el teléfono («Este teléfono estaba vinculado a otra cuenta. ¿Usarlo con esta?»). Nunca en segundo plano ni automáticamente.
- **Efecto de la transferencia:** la instalación pasa a la nueva cuenta. Los despachos pendientes del dueño anterior hacia este teléfono se invalidan (`installation_transferred`) y un job ya encolado no envía al nuevo dueño. El dueño anterior deja de recibir pushes en este teléfono y ya no puede enviar recibos con esa `installation_id`. Sus recordatorios siguen siendo suyos: la nueva cuenta no puede verlos ni actuar sobre ellos, porque todo endpoint comprueba el propietario por Sanctum.
- Antes de reclamar, la app debe **borrar su estado local de la cuenta anterior**: avisos mostrados, deduplicación, acciones pendientes de sincronizar y detalle cacheado.
- Si el token FCM rotó mientras la sesión estaba cerrada, el reclamo responde `403` y no hay prueba posible desde la app. La vía es soporte: `php artisan reminders:installation-release <installation_id> --reason=…`, que se audita.

**Decisión sobre la prueba de posesión:** se exige que el `token` enviado coincida exactamente (comparación en tiempo constante) con el token FCM vigente guardado para esa `installation_id`. No se añade un desafío por push adicional.
- Para reclamar hacen falta **dos datos que solo tiene el teléfono**: su `installation_id` (UUID generado localmente, que el backend nunca devuelve a otra cuenta) y su token FCM vigente, que el backend no expone en ninguna respuesta ni en logs (el log FCM heredado ahora guarda una huella SHA-256 en lugar del prefijo del token).
- Quien tiene ambos ya controla el teléfono o sus datos. Un desafío por push sobre ese mismo token no añade una prueba independiente: iría al mismo token que el reclamante ya demostró conocer. Además no resuelve el caso del token rotado, y exigiría un nuevo tipo de push y un flujo de ida y vuelta en Android.
- **Riesgo residual aceptado:** si un tercero obtiene ambos datos (backup del teléfono sin cifrar, app comprometida o logs del cliente), puede reclamar la instalación. Por eso son obligatorios §8.3 (no registrar el token ni la `installation_id` en logcat) y el límite de 5 intentos por hora, por cuenta y por instalación, que impide adivinar. Cada intento (`transferred`, `already_owned`, `denied_possession`, `not_found`, `rate_limited`, `operator_release`) queda en la auditoría `device_installation_claims`, sin tokens, IP ni user-agent.
- **Nota operativa:** la allowlist del piloto es por ID de dispositivo. Si otra cuenta reclama el teléfono del piloto, pasaría a ser un destino listo para esa cuenta. Revisar `REMINDERS_DISPATCH_DEVICE_IDS` tras cualquier transferencia (queda visible en la auditoría).

### 8.2 Listado sin contexto (cambio)

`GET /api/mobile/contextual-reminders` (y el listado de Hermes) ya **no** devuelve `context`, `next_action`, `actions`, `audio_ready` ni `delivery_receipt`: solo el resumen (`id`, `occurrence_id`, `state`, `version`, `scheduled_at`, `expires_at`, `timezone`, `with_audio`, `dispatch`) más `title`. El contexto y la siguiente acción solo viajan en `GET …/{id}`, que se pide cuando el usuario abre el recordatorio. Las respuestas de mutaciones, conflictos, recibos y registro nunca incluyen contexto.

### 8.3 Logs del cliente (obligatorio)

El hallazgo de que `HttpLoggingInterceptor` en nivel `BODY` deja el bearer y el detalle en logcat es del lado Android. En builds de release:
- el nivel debe ser `NONE`;
- en debug, como máximo `HEADERS` con `redactHeader("Authorization")`;
- nunca `BODY` en rutas de recordatorios, dispositivo ni reclamo;
- no registrar el token FCM, la `installation_id`, `Idempotency-Key`, contexto ni siguiente acción.

El backend ya minimiza lo que devuelve (§8.2), pero el detalle necesita contexto y el registro necesita el token: solo la app puede evitar que queden en logcat.

### 8.4 Idempotencia: ámbito por usuario (sin cambio, aclarado)

El comentario anterior del controlador («la instalación estable llega con RC3») estaba desactualizado. **Decisión: el ámbito de `Idempotency-Key` en la API móvil es el usuario autenticado por Sanctum, no la instalación.**
- La `installation_id` la declara el cliente; el usuario sale del token verificado por el servidor.
- Con claves aleatorias de 16 a 100 caracteres, que dos instalaciones del mismo usuario colisionen es despreciable. Aun así, un replay desde otro teléfono del mismo usuario devuelve el mismo resultado sin duplicar el efecto, que es lo correcto.
- La app no tiene que cambiar nada: generar una clave aleatoria por intención y conservarla hasta tener un resultado definitivo.
