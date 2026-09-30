# Reclamo de instalación (409 `installation_conflict`)

Estado al 23/09/2026: **conectado** contra el contrato Android v1.1, §8.1
(`../docs/CONTRATO-ANDROID-RECORDATORIOS.md`). Probado en JVM con MockWebServer.
No se probó en un teléfono ni contra el backend real.

## Problema

Si al cerrar sesión el `DELETE /api/mobile/device-token` no llega al backend (sin red), la
instalación queda ligada a la cuenta anterior. La siguiente cuenta recibe `409 installation_conflict`
al registrar, y el contrato prohíbe regenerar `installation_id`.

## Flujo en la app

1. El registro (`DeviceRegistrationWorker` → `InstallationRegistry.register`) recibe 409, guarda
   `hasConflict`, apaga `isReady` y no reintenta en bucle.
2. **Ajustes → `// reminders.status`** muestra «este teléfono está vinculado a otra cuenta» y el
   botón «usar este teléfono con esta cuenta». Nunca se reclama en segundo plano.
3. El botón abre la confirmación: «Este teléfono estaba vinculado a otra cuenta. ¿Usarlo con esta?».
   Solo «Usar con esta cuenta» llama a `SettingsViewModel.confirmClaimInstallation()`, que invoca
   `InstallationClaimFlow.claim(confirmedByUser = true)`.
4. El flujo, en este orden:
   - sale sin llamar si no hay conflicto, no hay confirmación o sigue vigente un `Retry-After`;
   - **borra el estado local de la cuenta anterior** (`Reminders.clearAccountState`): avisos
     visibles, deduplicación, acciones y recibos pendientes, y el trabajo de sincronización.
     El detalle no se cachea en disco;
   - `POST /api/mobile/device-token/claim` con el **mismo cuerpo que el registro**:
     `installation_id` persistida + **token FCM vigente** (prueba de posesión), capacidades y
     `app_version`;
   - si tiene éxito, guarda `contextual_reminders_ready`, borra el conflicto y fuerza un nuevo
     registro.

## Respuestas

| Backend | `ClaimResult` | Efecto en la app |
| --- | --- | --- |
| `200 claimed: true` | `Claimed(alreadyOwned = false)` | Conflicto borrado, re-registro |
| `200 already_owned: true` | `Claimed(alreadyOwned = true)` | Igual: el reclamo es idempotente |
| `403 possession_not_proven` | `PossessionNotProven` | Se mantiene el conflicto. El token FCM rotó: solo soporte puede liberar (`php artisan reminders:installation-release <installation_id> --reason=…`) |
| `404 installation_not_found` | `NotFound` | Se borra el conflicto y se usa el registro normal |
| `429 rate_limited` + `Retry-After` | `RateLimited(s)` | Se guarda la espera. Hasta que vence no se llama ni se borra nada (sin cabecera: 1 h) |
| `401` | `SessionExpired` | Se mantiene el conflicto |
| `422` | `Failed("validation_failed", false)` | No se reintenta. Laravel responde `{message, errors}`, sin `code` |
| Red, 5xx, sin token FCM | `Failed(null, true)` | Se mantiene el conflicto; el usuario puede reintentar |

El doble toque se ignora mientras hay un reclamo en curso (`claimInProgress`).

## Código

| Pieza | Archivo |
| --- | --- |
| Llamada y mapeo de respuestas, `Retry-After` | `reminders/InstallationRegistry.kt` (`claim`) |
| Endpoint y modelo | `reminders/ContextualReminderApi.kt` (`claimInstallation`), `ReminderModels.kt` (`DeviceClaimResponse`) |
| Flujo explícito | `reminders/InstallationClaim.kt` (`InstallationClaimFlow`, `ApiInstallationClaimer`) |
| Cableado y limpieza de cuenta | `reminders/Reminders.kt` (`claimFlow`, `clearAccountState`) |
| UI | `ui/settings/SettingsScreen.kt` (`RemindersStatusCard`), `SettingsViewModel.confirmClaimInstallation` |
| Pruebas | `InstallationClaimFlowTest` (13) |

## Logs (§8.3)

`network/HttpLogging.kt`: en release no hay logging. En debug el nivel máximo es `HEADERS` en todas
las rutas, con `Authorization`, `Idempotency-Key` y cookies redactados. Así nunca se registran cuerpos:
ni el token FCM, ni la `installation_id`, ni el contexto. `/api/mobile/device-token/claim` está en
`BODYLESS_PATH_PREFIXES` (prefijo `/api/mobile/device-token`) y `HttpLoggingTest` lo cubre.

## Pendiente real

- **Prueba en teléfono y contra el backend:** conflicto real entre dos cuentas, reclamo, recepción del
  primer push tras la transferencia y comprobación de que la cuenta anterior deja de recibir.
- **Operación (backend):** tras cualquier transferencia, revisar `REMINDERS_DISPATCH_DEVICE_IDS`. La
  allowlist del piloto es por ID de dispositivo, y otra cuenta que reclame el teléfono del piloto
  pasaría a ser destino listo.
- El 409 se detecta en segundo plano. Hoy solo se ve al abrir Ajustes; no hay notificación ni aviso
  en la pantalla principal.
