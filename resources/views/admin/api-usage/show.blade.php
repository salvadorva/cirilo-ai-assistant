@extends('layout.app')
@section('title', 'Detalle de interacción')
@section('content')
<main class="container py-4">
    <h1>Interacción {{ $log->interaction_id ?? 'legada sin identificador' }}</h1>
    <p>Estimaciones en USD, no facturación. Sin prompts, respuestas, IP ni credenciales.</p>
    @if($interaction)
        <p>{{ $interaction->operation }} · {{ $interaction->result }} · HTTP {{ $interaction->http_status }} · {{ $interaction->duration_ms }} ms totales.</p>
        <p>Útil: {{ is_null($interaction->useful) ? 'Sin valorar' : ($interaction->useful ? 'Sí' : 'No') }}.
            Tarea lograda: {{ is_null($interaction->task_achieved) ? 'Sin valorar' : ($interaction->task_achieved ? 'Sí' : 'No') }}.
            Correcciones: {{ $interaction->corrections ?? 'Sin valorar' }}.</p>
        <ul>
        @foreach($interaction->stages ?? [] as $stage)
            <li>{{ $stage['stage'] }}: {{ $stage['duration_ms'] }} ms · {{ $stage['status'] }}</li>
        @endforeach
        </ul>
    @endif
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Etapa</th><th>Proveedor / modelo efectivo</th><th>Tokens</th><th>Estimación</th><th>Tarifa / estado</th><th>Duración</th></tr></thead>
        <tbody>
        @foreach($attempts as $attempt)
            <tr><td>{{ $attempt->stage ?? $attempt->api_type }}</td><td>{{ $attempt->api_provider }} / {{ $attempt->model }}</td>
                <td>{{ $attempt->total_tokens }}</td>
                <td>{{ $attempt->cost_status === 'estimated' && $attempt->estimated_cost !== null ? '$'.$attempt->estimated_cost : 'Costo desconocido' }}</td>
                <td>{{ $attempt->pricing_date ?? 'Sin tarifa' }} / {{ $attempt->cost_status }}</td><td>{{ $attempt->response_time_ms }} ms</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <p>Las etapas pueden contener llamadas anidadas: no sumar sus duraciones. «action_reported» describe el contrato del servidor, no verifica por sí solo el resultado de la tarea.</p>
    <a href="{{ route('admin.api-usage.index') }}">Volver a uso de APIs</a>
</main>
@endsection
