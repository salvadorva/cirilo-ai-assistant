<!DOCTYPE html>
<html>
<head>
    <title>Test Menu - Configuración de Features</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .config-box { border: 1px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>Test de Configuración del Menú</h1>
    
    <div class="config-box">
        <h2>Configuración actual:</h2>
        <ul>
            <li>agenda_enabled: <strong class="{{ config('features.agenda_enabled') ? 'success' : 'error' }}">{{ config('features.agenda_enabled') ? 'true' : 'false' }}</strong></li>
            <li>tutor_enabled: <strong class="{{ config('features.tutor_enabled') ? 'success' : 'error' }}">{{ config('features.tutor_enabled') ? 'true' : 'false' }}</strong></li>
        </ul>
    </div>
    
    <div class="config-box">
        <h2>Simulación del menú (código del layout):</h2>
        <pre>@if(config('features.agenda_enabled', false))</pre>
        @if(config('features.agenda_enabled', false))
        <div class="success">✅ Esta sección SE MOSTRARÍA en el menú</div>
        <div style="border: 1px solid green; padding: 10px; margin: 5px 0;">
            <i class="fa-solid fa-calendar-alt"></i> Agenda
        </div>
        @else
        <div class="error">❌ Esta sección NO SE MOSTRARÍA en el menú</div>
        @endif
        <pre>@endif</pre>
    </div>
    
    <div class="config-box">
        <h2>Simulación del menú de Tutor:</h2>
        <pre>@if(config('features.tutor_enabled', false))</pre>
        @if(config('features.tutor_enabled', false))
        <div class="success">✅ Esta sección SE MOSTRARÍA en el menú</div>
        <div style="border: 1px solid green; padding: 10px; margin: 5px 0;">
            <i class="fa-solid fa-robot"></i> Tutor IA
        </div>
        @else
        <div class="error">❌ Esta sección NO SE MOSTRARÍA en el menú</div>
        @endif
        <pre>@endif</pre>
    </div>
    
    <div class="config-box">
        <h2>Información adicional:</h2>
        <ul>
            <li>Timestamp: {{ now() }}</li>
            <li>Environment: {{ app()->environment() }}</li>
            <li>Config cached: {{ app()->configurationIsCached() ? 'Yes' : 'No' }}</li>
        </ul>
    </div>
    
    <div class="config-box">
        <h2>Contenido del archivo config/features.php:</h2>
        <pre>{{ file_get_contents(config_path('features.php')) }}</pre>
    </div>
</body>
</html>
