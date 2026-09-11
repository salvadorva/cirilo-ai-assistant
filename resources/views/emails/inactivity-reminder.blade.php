@extends('emails.layout')

@section('title', 'Te extrañamos - Asistente Cirilo')

@section('header-title')
    Te extrañamos, {{ $userName }}! 💚
@endsection

@section('header-subtitle')
    @if($daysInactive >= 14)
        Han pasado {{ $daysInactive }} días desde tu última sesión
    @else
        Tu racha te está esperando
    @endif
@endsection

@section('content')
    <div class="greeting">
        ¡Hola {{ $userName }}! <span class="emoji">👋</span>
    </div>
    
    <div class="main-message">
        @if($daysInactive >= 14)
            <p>Hemos notado que no has visitado el Asistente Cirilo en un tiempo. ¡Te extrañamos! <span class="emoji">😢</span></p>
            <p>Tu progreso sigue aquí esperándote, y hemos preparado algo especial para tu regreso.</p>
        @elseif($daysInactive >= 7)
            <p>Tu racha de {{ $longestStreak }} días fue impresionante. <span class="emoji">🔥</span></p>
            <p>¿Qué dices si la retomamos? Solo necesitas unos minutos al día para seguir mejorando.</p>
        @else
            <p>Notamos que has estado un poco ausente. <span class="emoji">⏰</span></p>
            <p>¡Es el momento perfecto para una sesión rápida y mantener tu progreso!</p>
        @endif
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value">{{ $currentLevel }}</span>
            <div class="stat-label">Nivel Actual</div>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ number_format($totalXP) }}</span>
            <div class="stat-label">XP Total</div>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ $longestStreak }}</span>
            <div class="stat-label">Mejor Racha</div>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ $daysInactive }}</span>
            <div class="stat-label">Días Inactivo</div>
        </div>
    </div>
    
    @if($daysInactive >= 7)
        <div class="incentive-card">
            <div class="incentive-title">🎁 Bonus de Regreso</div>
            <p>¡Gana {{ min(100, $daysInactive * 5) }} XP gratis por regresar hoy!</p>
        </div>
    @endif
    
    @if($longestStreak > 3)
        <div class="incentive-card">
            <div class="incentive-title">⚡ Protección de Racha</div>
            <p>¡Puedes recuperar tu racha de {{ $longestStreak }} días si juegas hoy!</p>
        </div>
    @endif
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $loginUrl }}" class="cta-button">
            @if($daysInactive >= 14)
                ✨ Volver al Asistente Cirilo <span class="emoji">🚀</span>
            @else
                Continuar mi Progreso <span class="emoji">⚡</span>
            @endif
        </a>
    </div>
    
    <div class="main-message">
        <p><strong>¿Por qué es importante la consistencia?</strong></p>
        <p>Solo 10 minutos diarios pueden marcar la diferencia en tu aprendizaje. Tu cerebro retiene mejor la información con práctica regular.</p>
    </div>
    
    @if($engagementScore < 30)
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p><strong>💡 Consejo:</strong> Comienza con sesiones cortas de 5-10 minutos. ¡La consistencia es más importante que la duración!</p>
        </div>
    @endif
@endsection

@section('settings-url', $settingsUrl)
@section('login-url', $loginUrl)