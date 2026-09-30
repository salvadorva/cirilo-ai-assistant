# Cirilo — Asistente IA multiplataforma

Asistente conversacional educativo con **memoria persistente de usuario**, voz, generación de imágenes,
tutor de inglés, gamificación y agenda sincronizada. Nació como una aplicación de escritorio, creció
hasta una plataforma web completa y hoy suma un cliente Android nativo.

**Tres clientes, una sola API:**

| Plataforma | Carpeta | Tecnología |
|---|---|---|
| 🌐 **Web** (backend y API) | raíz del repositorio | Laravel 11 · PHP 8.2+ · MySQL · Blade + Bootstrap · Sanctum |
| 📱 **Android** | [`android/`](android/) | Kotlin · Jetpack Compose · Material 3 · FCM |
| 🖥️ **Escritorio** | [`desktop/`](desktop/) | Python · PyQt6 · SpeechRecognition · pyttsx3 |

Proveedores de IA soportados: **OpenAI** y **xAI (Grok)**.

> **Sobre este repositorio.** Es la publicación de un proyecto personal en funcionamiento. Los seeders
> incluyen **usuarios y prompts de demostración**, no datos reales, y toda la configuración sensible se
> toma de variables de entorno: copiá `.env.example` a `.env` y completá tus propias claves. Licencia MIT.

## Contenido

**Funcionalidades**

- [🧠 Memoria del Usuario](#-memoria-del-usuario)
- [🤖 Chat con IA](#-chat-con-ia)
- [🎙️ Modo Conversación (`/conversar`)](#-modo-conversación-conversar)
- [🔊 Voz](#-voz)
- [🖼️ Imágenes](#-imágenes)
- [🎓 Tutor de Inglés (Cirilo)](#-tutor-de-inglés-cirilo)
- [⌨️ TypeMaster](#-typemaster)
- [🎨 Modo Creativo](#-modo-creativo)
- [📅 Agenda / Calendario](#-agenda--calendario)
- [📲 Notificaciones Telegram](#-notificaciones-telegram)
- [🏆 Gamificación](#-gamificación)
- [📊 Panel de Administración](#-panel-de-administración)
- [📱 PWA (Progressive Web App)](#-pwa-progressive-web-app)

**Referencia**

- [Aplicación Android](#aplicación-android)
- [Versión de Escritorio](#versión-de-escritorio)
- [Stack Tecnológico](#stack-tecnológico)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Instalación](#instalación)
- [Tareas Programadas](#tareas-programadas)
- [Notificaciones — Configuración por usuario](#notificaciones--configuración-por-usuario)
- [Variables de Entorno](#variables-de-entorno)
- [Rutas Principales](#rutas-principales)
- [Documentación Adicional](#documentación-adicional)
- [Comandos Útiles](#comandos-útiles)

---

## Características Principales

### 🧠 Memoria del Usuario
- **Perfil estructurado**: el asistente extrae y almacena hechos categorizados sobre el usuario (información personal, preferencias, contexto laboral, metas, relaciones)
- **Extracción automática**: ocurre en la misma llamada gpt-4o-mini que genera el resumen de conversación — sin costo adicional de API
- **Contexto personalizado**: el perfil se inyecta en el system prompt de cada conversación para respuestas más personalizadas
- **Memoria de pendientes**: los items pendientes de conversaciones anteriores quedan disponibles para que el asistente los retome proactivamente
- **Control del usuario**: pestaña "Memoria" en `/settings` para ver y eliminar hechos guardados por categoría

### 🤖 Chat con IA
- Conversación en lenguaje natural con **GPT-4.1** (OpenAI Responses API) o **Grok** (xAI)
- Web search integrado (`web_search_preview`) para respuestas actualizadas
- Prompt personalizable por usuario
- **Contexto de agenda**: detecta keywords de calendario en el chat e inyecta los eventos reales del usuario antes de responder
- **Creación de eventos desde el chat**: el asistente solicita los datos del evento y lo crea directamente en la agenda
- **Resumen semántico de conversaciones**: las conversaciones guardadas se resumen con gpt-4o-mini para proveer contexto inteligente en futuras sesiones (no solo los últimos mensajes)
- Historial de conversaciones con búsqueda, filtros y vista de detalle

### 🎙️ Modo Conversación (`/conversar`)
- **Hands-free**: vista dedicada, optimizada para móvil, sin necesidad de teclado
- **Cirilo animado**: el avatar reacciona visualmente al estado (escuchando / pensando / respondiendo / pausado)
- **Auto-inicio del micrófono**: comienza a escuchar al abrir la vista
- **Countdown de 2 segundos**: muestra lo que detectó antes de enviar; presiona "Editar" para corregir
- **Conversación continua**: el micrófono se reabre automáticamente tras cada respuesta de la IA
- **Pausa por voz**: di "Cirilo mute" / "Cirilo para" / "Cirilo silenciar" para pausar; botón en pantalla para reanudar
- **Toggle TTS**: puede desactivarse para respuestas solo en texto

### 🔊 Voz
- **Text-to-Speech**: convierte respuestas a audio (OpenAI TTS-1, voz "echo")
- **Speech-to-Text**: reconocimiento de voz del usuario (OpenAI Whisper)
- **Audios estáticos**: pre-aprobados para frases frecuentes, sin costo de API en producción

### 🖼️ Imágenes
- **Generación**: crea imágenes con `gpt-image-1` desde texto; modelos centralizados en `config/ai.php`
- **Análisis**: describe imágenes subidas por el usuario (GPT-4o Vision)
- **Límite diario**: 4 imágenes/día por usuario, sin límite para admins

### 🎓 Tutor de Inglés (Cirilo)
- Evaluación inicial del nivel (beginner, intermediate, advanced)
- Cursos personalizados generados con IA
- Sesiones de práctica: vocabulario, gramática, listening, speaking
- Evaluación de pronunciación con feedback por IA
- Juegos: **Word Match Rush** y **Sentence Builder**

### ⌨️ TypeMaster
- Mecanografía con textos generados por IA
- Niveles y temas configurables
- Métricas: WPM y precisión

### 🎨 Modo Creativo
- Ideas creativas, historias y reflexiones filosóficas
- Prompts aleatorios o por tema

### 📅 Agenda / Calendario
- Calendario de eventos completo (FullCalendar 5)
- CRUD de eventos con categorías, colores, ubicación y recordatorios
- **Recordatorio configurable por evento**: Sin recordatorio / 5 / 10 / 15 / 30 min / 1h / 2h / 1 día antes
- **Recordatorios por email**: enviados automáticamente antes del evento (respeta horario 7-18h)
- **Recordatorios por Telegram**: vía n8n, sin restricción de horario, canal independiente del email
- **Creación desde el chat**: el asistente detecta el intent y crea el evento; si faltan datos, los solicita antes de guardar
- **Asistente de voz**: comandos de voz para crear y consultar eventos

### 📲 Notificaciones Telegram
- Integración via webhook n8n → Bot de Telegram
- El usuario configura su `chat_id` en `/settings` escribiendo `/start` al bot
- Canal completamente independiente del email
- Notificaciones de recordatorio y de inicio de evento

### 🏆 Gamificación
- XP, niveles y rachas de actividad diaria
- 14+ tipos de logros desbloqueables
- Notificaciones in-app de logros y subidas de nivel

### 📊 Panel de Administración
- Dashboard de engagement: usuarios activos, XP, rachas, tasa de completación
- Bitácora de APIs: uso y costos estimados por usuario y tipo
- Gestión de usuarios con toggle de notificaciones
- Gestión de audios estáticos

### 📱 PWA (Progressive Web App)
- Instalable en móvil y escritorio
- Funciona offline con caché inteligente (Cache-First para estáticos, Network-First para HTML)
- Service Worker con detección y notificación de actualizaciones
- Accesos directos: Agenda, Tutor, Cursos

---

## Aplicación Android

Cliente móvil nativo del asistente, en `android/`. **Kotlin + Jetpack Compose + Material 3**, se
autentica contra la misma API (Sanctum) y recibe notificaciones por FCM.

| | |
|---|---|
| Ubicación | [`android/`](android/) |
| Package | `com.salvadorva.asistente` |
| Requisitos | Android Studio · JDK 17 |

**Antes de compilar:**
1. Configurá la URL de tu backend en `android/app/src/main/java/com/salvadorva/asistente/network/ApiClient.kt` (constante `BASE_URL`).
2. Registrá tu propia app en Firebase y colocá tu `google-services.json` en `android/app/`. El archivo
   **no se versiona** a propósito.

---

## Versión de Escritorio

El origen del proyecto: cliente de escritorio en **Python + PyQt6**, en `desktop/`. Conversación por
voz con reconocimiento y síntesis de habla, generación de imágenes y ventana de configuración donde
se introduce la clave de API.

| | |
|---|---|
| Ubicación | [`desktop/`](desktop/) |
| Requisitos | Python 3.11+ · PyAudio · PyQt6 |
| Ejecutar | `pip install -r requirements.txt && python main.py` |

La clave de OpenAI **no se versiona**: se introduce desde la ventana de configuración de la app.

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 11, PHP 8.4.1+ |
| Base de datos | MySQL 8+ |
| Frontend | Blade, Bootstrap 5, FullCalendar 5, Chart.js 4.4 |
| IA — Chat | OpenAI GPT-4.1 (Responses API), Grok (xAI) |
| IA — Imágenes | DALL-E 3, GPT-4o Vision |
| IA — Voz | OpenAI TTS-1, Whisper |
| IA — Resúmenes y Memoria | gpt-4o-mini (resúmenes + extracción de perfil de usuario) |
| Notificaciones | Email (Laravel Mail) + Telegram (n8n webhook) |
| Autenticación | Laravel Auth + Google reCAPTCHA |

---

## Estructura del Proyecto

```
app/
├── Console/Commands/
│   ├── ScheduleEventNotifications.php  # Recordatorios de agenda (email + Telegram)
│   └── ...                             # Gamificación, engagement, audios
├── Http/Controllers/
│   ├── AIController.php                # Chat, imágenes, TTS, STT + contexto agenda
│   ├── AgendaController.php            # CRUD calendario + asistente de voz
│   ├── ConversationController.php      # Historial + resumen semántico
│   ├── TutorController.php             # Tutor de inglés
│   ├── TypeMasterController.php        # Mecanografía
│   ├── EnglishGamesController.php      # Juegos de inglés
│   ├── CreativeModeController.php      # Modo creativo
│   ├── ImageAnalysisController.php     # GPT-4o Vision
│   ├── HomeController.php              # Dashboard principal
│   ├── UserSettingsController.php      # Configuración (notificaciones, Telegram)
│   ├── PWAController.php               # PWA manifest y offline
│   └── Admin/                          # Dashboard, bitácora, usuarios, audios
├── Services/
│   ├── MemoryService.php               # Perfil estructurado del usuario (hechos + contexto)
│   ├── AchievementService.php
│   ├── StreakService.php
│   ├── NotificationService.php         # Notificaciones in-app + email
│   ├── TelegramNotificationService.php # Envío via webhook n8n
│   ├── AudioCacheService.php
│   └── InactivityDetector.php
└── Traits/
    ├── LogsApiUsage.php
    └── TracksUserActivity.php
```

---

## Instalación

### Requisitos
- PHP 8.4.1+, MySQL 8+, Composer
- Servidor web con HTTPS (requerido para PWA y Telegram webhook)

```bash
git clone <url-repo> asistente-web && cd asistente-web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan audio:setup
```

### Cron (Scheduler)

```bash
* * * * * cd /var/www/asistente-web && php artisan schedule:run >> /dev/null 2>&1
```

---

## Tareas Programadas

| Tarea | Frecuencia | Descripción |
|-------|-----------|-------------|
| `agenda:schedule-notifications` | Cada 5 min | Recordatorios de agenda por email y Telegram |
| `achievements:grant-retroactive` | Cada hora | Otorga logros automáticamente |
| `streaks:recalculate --all` | Diario 3:00 AM | Recalcula rachas |
| `admin:sync-data` | Diario 4:00 AM | Sincroniza datos del dashboard |
| `engagement:detect-inactive` | Diario 9:00 AM | Detecta inactivos y envía emails |
| `engagement:update-scores` | Cada 6 horas | Actualiza engagement scores |
| `engagement:send-weekly-reports` | Domingos 10:00 AM | Reportes semanales |

> Emails del sistema solo se envían entre 7:00 y 18:00. Telegram no tiene restricción horaria.

---

## Notificaciones — Configuración por usuario

En `/settings → Notificaciones` el usuario controla tres canales independientes:

| Toggle | Canal | Restricción horaria |
|--------|-------|---------------------|
| Notificaciones del sistema por Email | Reportes, inactividad, avisos | No |
| Recordatorios de Agenda por Email | Eventos de la agenda | Sí (7-18h) |
| Recordatorios de Agenda por Telegram | Eventos de la agenda | No |

Para Telegram: buscar `@CiriloWeb_bot` → enviar `/start` → copiar el Chat ID → pegarlo en `/settings`.

---

## Variables de Entorno

```env
# Base de datos
DB_CONNECTION=mysql
DB_DATABASE=asistente_web
DB_USERNAME=...
DB_PASSWORD=...

# APIs de IA
OPENAI_API_KEY=sk-...
GROK_API_KEY=xai-...           # Opcional

# reCAPTCHA
RECAPTCHA_SITE_KEY=...
RECAPTCHA_SECRET_KEY=...

# App
APP_URL=https://tu-dominio.com
APP_TIMEZONE=America/Guatemala

# n8n / Telegram
N8N_TELEGRAM_WEBHOOK_URL=https://n8n.tu-dominio.com/webhook/telegram-asistente
N8N_WEBHOOK_SECRET=...
TELEGRAM_BOT_USERNAME=@NombreDelBot
```

---

## Rutas Principales

| URL | Descripción |
|-----|-------------|
| `/` | Dashboard |
| `/preguntas` | Chat con IA (modo texto) |
| `/conversar` | Modo conversación por voz (mobile-first, Cirilo animado) |
| `/agenda` | Calendario de eventos |
| `/imagenes` | Generación de imágenes |
| `/modo-creativo` | Modo creativo |
| `/image-analysis` | Análisis de imágenes |
| `/tutor` | Tutor de inglés |
| `/typing` | TypeMaster |
| `/english-games` | Juegos de inglés |
| `/historial` | Historial de conversaciones |
| `/settings` | Configuración (notificaciones, Telegram) |
| `/admin/dashboard` | Dashboard de engagement |
| `/admin/api-usage` | Bitácora de APIs |
| `/pwa/install` | Instrucciones PWA |

---

## Documentación Adicional

| Documento | Contenido |
|---|---|
| [Sistema de perfil de usuario](docs/USER_PROFILE_SYSTEM.md) | Cómo se extraen, almacenan y reinyectan los hechos del usuario |
| [Integración con Telegram](docs/TELEGRAM_INTEGRATION.md) | Envío de notificaciones vía n8n |
| [Servicio de notificaciones](docs/NOTIFICATION_SERVICE_GUIDE.md) | Guía del `NotificationService` |
| [Calendario Nextcloud](docs/NEXTCLOUD_CALENDAR.md) | Sincronización CalDAV de la agenda |
| [Panel de administración](docs/ADMIN_DASHBOARD.md) | Métricas y gestión de usuarios |
| [Reconocimiento de voz](docs/FIX-VOICE-RECOGNITION.md) | Notas de implementación del dictado |
| [Reporte de pruebas](docs/testing-report.md) | Cobertura y resultados |

---

## Comandos Útiles

```bash
php artisan agenda:schedule-notifications  # Enviar recordatorios manualmente
php artisan achievements:grant-retroactive # Otorgar logros
php artisan streaks:recalculate --all      # Recalcular rachas
php artisan admin:diagnose                 # Diagnosticar dashboard
php artisan audio:clean --days=7           # Limpiar audios dinámicos viejos
php artisan audio:setup                    # Configurar directorios de audio
php artisan pwa:generate                   # Generar archivos PWA
php artisan schedule:list                  # Ver tareas programadas
```
