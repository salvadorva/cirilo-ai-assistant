@extends('../layout.app')
@section('title', 'Hoy')
@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-sun me-2 text-warning"></i>Hoy, {{ $today['date_label'] }}</h1>
            <p class="text-muted mb-0">{{ $today['summary'] }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('preguntas') }}" class="btn btn-primary"><i class="fas fa-comments me-1"></i>Escribir a Cirilo</a>
            <a href="{{ url('/conversar') }}" class="btn btn-outline-primary"><i class="fas fa-microphone me-1"></i>Hablar</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="fas fa-calendar-day me-2"></i>Compromisos de hoy</h2>
            <a href="{{ url('/agenda') }}" class="small">Abrir agenda</a>
        </div>
        <ul class="list-group list-group-flush">
            @forelse($today['events'] as $event)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>{{ $event['all_day'] ? 'Todo el día' : $event['time'] }}</strong> — {{ $event['title'] }}@if($event['location']) <span class="text-muted">· {{ $event['location'] }}</span>@endif</span>
                    <a href="{{ $event['url'] }}" class="small text-nowrap">Ver o editar</a>
                </li>
            @empty
                <li class="list-group-item text-muted">No tienes compromisos agendados para hoy.</li>
            @endforelse
        </ul>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="fas fa-list-check me-2"></i>Pendientes</h2></div>
        <ul class="list-group list-group-flush">
            @forelse($today['tasks'] as $task)
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2" id="tarea-{{ $task['id'] }}">
                    <span>
                        {{ $task['title'] }}
                        @if($task['due_date'] && $task['due_date'] < $today['date'])<span class="badge bg-danger-subtle text-danger ms-1">Vencido {{ \Carbon\Carbon::parse($task['due_date'])->format('d/m') }}</span>
                        @elseif($task['due_date'])<span class="badge bg-warning-subtle text-warning-emphasis ms-1">Vence hoy</span>
                        @else<span class="badge bg-light text-muted ms-1">Sin fecha</span>@endif
                    </span>
                    <span class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success" data-task="{{ $task['id'] }}" data-action="complete"><i class="fas fa-check me-1"></i>Hecho</button>
                        <button class="btn btn-outline-secondary" data-task="{{ $task['id'] }}" data-action="postpone" title="Posponer a mañana">Mañana</button>
                        <button class="btn btn-outline-danger" data-task="{{ $task['id'] }}" data-action="dismiss" title="Descartar">Descartar</button>
                    </span>
                </li>
            @empty
                <li class="list-group-item text-muted">No tienes pendientes para hoy.</li>
            @endforelse
            <li class="list-group-item">
                <form id="new-task-form" class="d-flex flex-wrap gap-2">
                    <input type="text" name="title" class="form-control form-control-sm flex-grow-1" maxlength="255" placeholder="Nuevo pendiente (por ejemplo: revisar la propuesta)" required style="min-width: 12rem;">
                    <input type="date" name="due_date" class="form-control form-control-sm w-auto" title="Fecha límite (opcional)">
                    <button class="btn btn-sm btn-primary">Agregar</button>
                </form>
            </li>
        </ul>
    </div>

    @if(count($today['suggestions']))
    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-info">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0"><i class="fas fa-lightbulb me-2 text-info"></i>Sugerencias de tus conversaciones</h2>
            <small class="text-muted">No son compromisos hasta que los aceptes.</small>
        </div>
        <ul class="list-group list-group-flush">
            @foreach($today['suggestions'] as $task)
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2" id="tarea-{{ $task['id'] }}">
                    <span class="fst-italic">{{ $task['title'] }}</span>
                    <span class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" data-task="{{ $task['id'] }}" data-action="accept">Aceptar</button>
                        <button class="btn btn-outline-secondary" data-task="{{ $task['id'] }}" data-action="dismiss">Descartar</button>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <details class="card border-0 shadow-sm mb-3">
        <summary class="card-header bg-white h6 mb-0" style="cursor:pointer;"><i class="fas fa-bell me-2"></i>Resumen diario (opcional)</summary>
        <form id="summary-form" class="card-body row g-2 align-items-end">
            <div class="col-12 form-check form-switch ms-2">
                <input class="form-check-input" type="checkbox" id="summary-enabled" @checked($user->daily_summary_enabled)>
                <label class="form-check-label" for="summary-enabled">Enviarme cada mañana mis compromisos y pendientes</label>
            </div>
            <div class="col-auto"><label class="form-label small">Hora</label><input type="time" id="summary-time" class="form-control form-control-sm" value="{{ $user->daily_summary_time ?: '07:30' }}"></div>
            <div class="col-auto"><label class="form-label small">Canal</label>
                <select id="summary-channel" class="form-select form-select-sm">
                    <option value="internal" @selected($user->daily_summary_channel === 'internal')>Notificación en Cirilo</option>
                    <option value="telegram" @selected($user->daily_summary_channel === 'telegram')>Telegram</option>
                    <option value="email" @selected($user->daily_summary_channel === 'email')>Correo</option>
                </select></div>
            <div class="col-auto"><label class="form-label small">Días</label>
                <select id="summary-days" class="form-select form-select-sm">
                    <option value="weekdays" @selected($user->daily_summary_days === 'weekdays')>Lunes a viernes</option>
                    <option value="daily" @selected($user->daily_summary_days === 'daily')>Todos los días</option>
                </select></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Guardar</button> <span id="summary-status" class="small text-muted" role="status"></span></div>
        </form>
    </details>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };
    const tomorrow = () => { const d = new Date(); d.setDate(d.getDate() + 1); return d.toISOString().slice(0, 10); };

    document.querySelectorAll('[data-task]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        const body = { action: button.dataset.action };
        if (body.action === 'postpone') body.until = tomorrow();
        const response = await fetch('/pendientes/' + encodeURIComponent(button.dataset.task), { method: 'PATCH', headers, body: JSON.stringify(body) }).catch(() => null);
        if (response && response.ok) { window.location.reload(); } else { button.disabled = false; }
    }));

    document.getElementById('new-task-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.target;
        const response = await fetch('/pendientes', { method: 'POST', headers,
            body: JSON.stringify({ title: form.title.value, due_date: form.due_date.value || null }) }).catch(() => null);
        if (response && response.ok) window.location.reload();
    });

    document.getElementById('summary-form').addEventListener('submit', async event => {
        event.preventDefault();
        const status = document.getElementById('summary-status');
        const response = await fetch('/hoy/preferencias', { method: 'PUT', headers, body: JSON.stringify({
            enabled: document.getElementById('summary-enabled').checked,
            time: document.getElementById('summary-time').value,
            channel: document.getElementById('summary-channel').value,
            days: document.getElementById('summary-days').value,
        }) }).catch(() => null);
        status.textContent = response && response.ok ? 'Guardado' : 'No se pudo guardar';
    });
})();
</script>
@endpush
