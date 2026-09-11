@extends('layout.app')

@section('title', 'Configuración')

@section('content')
<div class="container py-4">
    <div class="row">
        <!-- Sidebar de navegación -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="list-group list-group-flush">
                    <a href="#general" 
                       class="list-group-item list-group-item-action active" 
                       data-bs-toggle="pill">
                        <i class="fas fa-cog me-2"></i>General
                    </a>
                    <a href="#notifications" 
                       class="list-group-item list-group-item-action" 
                       data-bs-toggle="pill">
                        <i class="fas fa-bell me-2"></i>Notificaciones
                    </a>
                    <a href="#security" 
                       class="list-group-item list-group-item-action" 
                       data-bs-toggle="pill">
                        <i class="fas fa-lock me-2"></i>Seguridad
                    </a>
                    <a href="#preferences"
                       class="list-group-item list-group-item-action"
                       data-bs-toggle="pill">
                        <i class="fas fa-sliders-h me-2"></i>Preferencias
                    </a>
                    <a href="#memory"
                       class="list-group-item list-group-item-action"
                       data-bs-toggle="pill"
                       id="memoryTabLink">
                        <i class="fas fa-brain me-2"></i>Memoria
                    </a>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="col-lg-9">
            <!-- Success Message -->
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <!-- Errors -->
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <div class="tab-content">
                <!-- Tab General -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Configuración General</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @csrf
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Idioma</label>
                                    <select name="language" class="form-select">
                                        <option value="es" {{ (session('user_settings.language', 'es') == 'es') ? 'selected' : '' }}>
                                            🇪🇸 Español
                                        </option>
                                        <option value="en" {{ (session('user_settings.language') == 'en') ? 'selected' : '' }}>
                                            🇬🇧 English
                                        </option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Tema</label>
                                    <select name="theme" class="form-select">
                                        <option value="light" {{ (session('user_settings.theme', 'light') == 'light') ? 'selected' : '' }}>
                                            ☀️ Claro
                                        </option>
                                        <option value="dark" {{ (session('user_settings.theme') == 'dark') ? 'selected' : '' }}>
                                            🌙 Oscuro
                                        </option>
                                        <option value="auto" {{ (session('user_settings.theme') == 'auto') ? 'selected' : '' }}>
                                            🔄 Automático
                                        </option>
                                    </select>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tab Notificaciones -->
                <div class="tab-pane fade" id="notifications">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notificaciones</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('settings.update') }}" method="POST">
                                @csrf
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="email_notifications_enabled"
                                               id="emailNotif"
                                               value="1"
                                               {{ $user->email_notifications_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="emailNotif">
                                            <strong>Notificaciones del sistema por Email</strong>
                                            <div class="text-muted small">Reportes semanales, alertas de inactividad y avisos generales</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="agenda_reminders_enabled"
                                               id="agendaReminders"
                                               value="1"
                                               {{ $user->agenda_reminders_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="agendaReminders">
                                            <strong>Recordatorios de Agenda por Email</strong>
                                            <div class="text-muted small">Recibe un correo antes de cada evento agendado</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="sound_effects" 
                                               id="soundEffects"
                                               value="1"
                                               {{ session('user_settings.sound_effects', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="soundEffects">
                                            <strong>Efectos de Sonido</strong>
                                            <div class="text-muted small">Reproducir sonidos en los juegos</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="background_music" 
                                               id="bgMusic"
                                               value="1"
                                               {{ session('user_settings.background_music', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="bgMusic">
                                            <strong>Música de Fondo</strong>
                                            <div class="text-muted small">Reproducir música ambiental</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sección Telegram -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fab fa-telegram me-2 text-primary"></i>Telegram</h5>
                        </div>
                        <div class="card-body">
                            @php $botUsername = config('services.n8n.bot_username'); @endphp
                            <div class="alert alert-info py-2 mb-3">
                                <strong>¿Cómo configurarlo?</strong>
                                <ol class="mb-0 mt-1">
                                    <li>Abre Telegram y busca <strong>{{ $botUsername ?? '@el bot configurado' }}</strong></li>
                                    <li>Envíale <code>/start</code> — el bot te responderá con tu Chat ID</li>
                                    <li>Pega ese número aquí y activa las notificaciones</li>
                                </ol>
                            </div>
                            <form action="{{ route('settings.update') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="telegramChatId" class="form-label">Chat ID de Telegram</label>
                                    <div class="input-group">
                                        <input type="text"
                                               class="form-control @error('telegram_chat_id') is-invalid @enderror"
                                               id="telegramChatId"
                                               name="telegram_chat_id"
                                               value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}"
                                               placeholder="Ej: 987654321"
                                               inputmode="numeric">
                                        <button type="button" class="btn btn-outline-secondary" id="btnTestTelegram" title="Enviar mensaje de prueba">
                                            <i class="fas fa-paper-plane me-1"></i>Probar
                                        </button>
                                    </div>
                                    @error('telegram_chat_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div id="telegramTestResult" class="mt-2" style="display:none;"></div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="telegram_notifications_enabled"
                                               id="telegramNotif"
                                               value="1"
                                               {{ $user->telegram_notifications_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="telegramNotif">
                                            <strong>Recibir recordatorios de agenda por Telegram</strong>
                                            <div class="text-muted small">Sin restricción de horario — tú decides cuando activarlo</div>
                                        </label>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                    <!-- Nextcloud Calendar -->
                    <div class="card mt-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">
                                <i class="fas fa-cloud me-2 text-primary"></i>Nextcloud Calendar
                            </h5>
                            @if($user->hasNextcloud())
                                <span class="badge bg-success">Conectado</span>
                            @else
                                <span class="badge bg-secondary">No configurado</span>
                            @endif
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Conecta tu cuenta de Nextcloud para sincronizar eventos de trabajo
                                automáticamente cuando los crees en Cirilo.
                            </p>
                            <form action="{{ route('settings.nextcloud') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="nextcloudUrl" class="form-label">URL de Nextcloud</label>
                                    <input type="url" class="form-control @error('nextcloud_url') is-invalid @enderror"
                                           id="nextcloudUrl" name="nextcloud_url"
                                           value="{{ old('nextcloud_url', $user->nextcloud_url) }}"
                                           placeholder="https://cloud.ejemplo.com">
                                    @error('nextcloud_url')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="nextcloudUsername" class="form-label">Usuario</label>
                                        <input type="text" class="form-control @error('nextcloud_username') is-invalid @enderror"
                                               id="nextcloudUsername" name="nextcloud_username"
                                               value="{{ old('nextcloud_username', $user->nextcloud_username) }}"
                                               placeholder="tu_usuario">
                                        @error('nextcloud_username')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nextcloudPassword" class="form-label">
                                            App Password
                                            <span class="text-muted small">(no tu contraseña principal)</span>
                                        </label>
                                        <input type="password" class="form-control @error('nextcloud_password') is-invalid @enderror"
                                               id="nextcloudPassword" name="nextcloud_password"
                                               placeholder="{{ $user->nextcloud_password ? '••••••••••••••••' : 'xxxx-xxxx-xxxx-xxxx-xxxx' }}">
                                        <div class="form-text">
                                            Genera en Nextcloud → Configuración → Seguridad → App passwords.
                                        </div>
                                        @error('nextcloud_password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="nextcloudCalendar" class="form-label">Calendario de trabajo</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control @error('nextcloud_calendar') is-invalid @enderror"
                                               id="nextcloudCalendar" name="nextcloud_calendar"
                                               value="{{ old('nextcloud_calendar', $user->nextcloud_calendar ?? 'personal') }}"
                                               placeholder="personal">
                                        <button type="button" class="btn btn-outline-secondary" id="btnTestNextcloud">
                                            <i class="fas fa-plug me-1"></i>Probar
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Slug del calendario (la última parte de la URL en Nextcloud Calendar).
                                    </div>
                                    <div id="nextcloudTestResult" class="mt-2" style="display:none;"></div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <!-- Tab Seguridad -->
                <div class="tab-pane fade" id="security">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Seguridad</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">Cambiar Contraseña</h6>
                            <form action="{{ route('settings.password') }}" method="POST">
                                @csrf
                                
                                <div class="mb-3">
                                    <label class="form-label">Contraseña Actual</label>
                                    <input type="password" 
                                           name="current_password" 
                                           class="form-control" 
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" 
                                           name="new_password" 
                                           class="form-control" 
                                           required
                                           minlength="8">
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" 
                                           name="new_password_confirmation" 
                                           class="form-control" 
                                           required>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                                    </button>
                                </div>
                            </form>

                            <hr class="my-4">

                            <div class="alert alert-info mb-0">
                                <h6 class="alert-heading"><i class="fas fa-shield-alt me-2"></i>Seguridad de tu Cuenta</h6>
                                <p class="mb-0">Tu cuenta está protegida. Si detectas actividad sospechosa, cambia tu contraseña inmediatamente.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Preferencias -->
                <div class="tab-pane fade" id="preferences">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Preferencias de Juego</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Las preferencias de juego se configuran individualmente en cada juego.
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6><i class="fas fa-keyboard me-2"></i>TypeMaster</h6>
                                            <p class="text-muted small mb-2">Tus preferencias de escritura</p>
                                            <a href="{{ route('typing.index') }}" class="btn btn-sm btn-primary">
                                                Configurar
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6><i class="fas fa-language me-2"></i>English Games</h6>
                                            <p class="text-muted small mb-2">Nivel de dificultad y más</p>
                                            <a href="{{ route('games.index') }}" class="btn btn-sm btn-success">
                                                Configurar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Memoria -->
                <div class="tab-pane fade" id="memory">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-brain me-2"></i>Lo que sé de ti</h5>
                            <button class="btn btn-sm btn-outline-danger" id="btnClearMemory" style="display:none;">
                                <i class="fas fa-trash me-1"></i>Borrar toda mi memoria
                            </button>
                        </div>
                        <div class="card-body" id="memoryContent">
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Cargando...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('btnTestTelegram').addEventListener('click', function () {
    const chatId = document.getElementById('telegramChatId').value.trim();
    const resultDiv = document.getElementById('telegramTestResult');
    const btn = this;

    if (!chatId) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-warning py-2 mb-0">Ingresa un Chat ID antes de probar.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
    resultDiv.style.display = 'none';

    fetch('{{ route("settings.telegram.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ telegram_chat_id: chatId })
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.style.display = 'block';
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-check-circle me-1"></i>' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle me-1"></i>' + data.message + '</div>';
        }
    })
    .catch(() => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">Error de conexión. Intenta de nuevo.</div>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Probar';
    });
});

// ── Memoria del usuario ───────────────────────────────────────────────────────
document.getElementById('memoryTabLink').addEventListener('click', function () {
    loadMemory();
});

function loadMemory() {
    const container = document.getElementById('memoryContent');
    const clearBtn  = document.getElementById('btnClearMemory');

    container.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando...</div>';

    fetch('{{ route("settings.memory") }}', {
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { container.innerHTML = '<div class="alert alert-danger">Error al cargar la memoria.</div>'; return; }

        const facts  = data.facts;
        const labels = data.category_labels;
        const keys   = Object.keys(facts);

        if (keys.length === 0) {
            clearBtn.style.display = 'none';
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-brain fa-3x mb-3 d-block opacity-25"></i>
                    <p class="mb-0">Aún no he aprendido nada sobre ti.</p>
                    <small>Conversa conmigo y lo haré automáticamente.</small>
                </div>`;
            return;
        }

        clearBtn.style.display = 'inline-block';
        let html = '';
        keys.forEach(cat => {
            const label = labels[cat] || cat;
            html += `<div class="mb-4"><h6 class="text-muted text-uppercase small mb-2">${label}</h6><ul class="list-group list-group-flush">`;
            facts[cat].forEach(fact => {
                const key   = fact.key.replace(/_/g, ' ');
                const badge = fact.confidence >= 0.8 ? 'success' : fact.confidence >= 0.6 ? 'warning' : 'secondary';
                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0" id="fact-${fact.id}">
                        <span><strong>${key}:</strong> ${fact.value}</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-${badge} bg-opacity-25 text-${badge}">${Math.round(fact.confidence * 100)}%</span>
                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteFact(${fact.id})" title="Eliminar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </li>`;
            });
            html += '</ul></div>';
        });
        container.innerHTML = html;
    })
    .catch(() => { container.innerHTML = '<div class="alert alert-danger">Error de conexión.</div>'; });
}

function deleteFact(id) {
    fetch(`{{ url('settings/memory') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('fact-' + id);
            if (el) el.remove();
            // Si no quedan items, recargar para mostrar el estado vacío
            if (document.querySelectorAll('[id^="fact-"]').length === 0) loadMemory();
        }
    });
}

document.getElementById('btnClearMemory').addEventListener('click', function () {
    if (!confirm('¿Eliminar toda la memoria? El asistente dejará de recordar tus datos personales.')) return;

    fetch('{{ route("settings.memory.clear") }}', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => { if (data.success) loadMemory(); });
});

// ── Test conexión Nextcloud ────────────────────────────────────────────────
const btnTestNextcloud  = document.getElementById('btnTestNextcloud');
const ncTestResult      = document.getElementById('nextcloudTestResult');
if (btnTestNextcloud) {
    btnTestNextcloud.addEventListener('click', function () {
        btnTestNextcloud.disabled = true;
        btnTestNextcloud.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Probando…';
        ncTestResult.style.display = 'none';

        fetch('{{ route('settings.nextcloud.test') }}', {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            ncTestResult.style.display = 'block';
            if (data.success) {
                const cals = data.calendars.length ? data.calendars.join(', ') : '(ninguno)';
                ncTestResult.innerHTML = `<div class="alert alert-success py-2 mb-0">
                    <i class="fas fa-check-circle me-1"></i>
                    Conexión exitosa. Calendarios disponibles: <strong>${cals}</strong>
                </div>`;
            } else {
                ncTestResult.innerHTML = `<div class="alert alert-danger py-2 mb-0">
                    <i class="fas fa-times-circle me-1"></i>
                    Error: ${data.error || data.message}
                </div>`;
            }
        })
        .catch(() => {
            ncTestResult.style.display = 'block';
            ncTestResult.innerHTML = '<div class="alert alert-danger py-2 mb-0">Error de red al probar la conexión</div>';
        })
        .finally(() => {
            btnTestNextcloud.disabled = false;
            btnTestNextcloud.innerHTML = '<i class="fas fa-plug me-1"></i>Probar';
        });
    });
}
</script>
@endpush
@endsection
