@extends('emails.layout')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="color: #333; font-size: 28px; margin: 0 0 20px 0; font-weight: 600;">
            📊 Tu Reporte Semanal
        </h1>
        
        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
            Hola <strong>{{ $userName }}</strong>,<br>
            Aquí está el resumen de tu actividad en TypeMaster AI durante la última semana.
        </p>

        <!-- Week Summary -->
        <div style="background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%); border-radius: 15px; padding: 25px; margin: 30px 0; color: white;">
            <h2 style="margin: 0 0 20px 0; font-size: 22px;">Esta semana lograste:</h2>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 10px; text-align: center; width: 33.33%;">
                        <div style="font-size: 36px; font-weight: bold;">{{ $weekStats['total_sessions'] }}</div>
                        <div style="font-size: 14px; opacity: 0.9;">Sesiones</div>
                    </td>
                    <td style="padding: 10px; text-align: center; width: 33.33%;">
                        <div style="font-size: 36px; font-weight: bold;">{{ number_format($weekStats['total_xp']) }}</div>
                        <div style="font-size: 14px; opacity: 0.9;">XP Ganado</div>
                    </td>
                    <td style="padding: 10px; text-align: center; width: 33.33%;">
                        <div style="font-size: 36px; font-weight: bold;">{{ $weekStats['total_time_minutes'] }}</div>
                        <div style="font-size: 14px; opacity: 0.9;">Minutos</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Progress Stats -->
        <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 30px 0;">
            <h3 style="color: #333; font-size: 18px; margin: 0 0 15px 0;">
                🎯 Progreso General
            </h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 10px 0; width: 50%;">
                        <span style="color: #666;">Nivel Actual:</span><br>
                        <strong style="color: #4e54c8; font-size: 24px;">{{ $currentLevel }}</strong>
                    </td>
                    <td style="padding: 10px 0; width: 50%;">
                        <span style="color: #666;">XP Total:</span><br>
                        <strong style="color: #8f94fb; font-size: 24px;">{{ number_format($totalXP) }}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;">
                        <span style="color: #666;">Racha Actual:</span><br>
                        <strong style="color: #ff6b6b; font-size: 20px;">🔥 {{ $currentStreak }} días</strong>
                    </td>
                    <td style="padding: 10px 0;">
                        <span style="color: #666;">Engagement Score:</span><br>
                        <strong style="color: #51cf66; font-size: 20px;">{{ $engagementScore }}/100</strong>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Daily Breakdown -->
        @if(count($dailyActivity) > 0)
        <div style="margin: 30px 0;">
            <h3 style="color: #333; font-size: 18px; margin: 0 0 15px 0;">
                📅 Actividad Diaria
            </h3>
            <table style="width: 100%; border-collapse: collapse;">
                @foreach($dailyActivity as $day)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <strong style="color: #333;">{{ $day['day_name'] }}</strong><br>
                        <span style="color: #999; font-size: 13px;">{{ $day['date'] }}</span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: center;">
                        <span style="color: #666;">{{ $day['sessions'] }} sesiones</span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee; text-align: right;">
                        <strong style="color: #4e54c8;">+{{ number_format($day['xp']) }} XP</strong>
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        <!-- Achievements -->
        @if(count($achievements) > 0)
        <div style="background: #fff3e0; border-radius: 10px; padding: 20px; margin: 30px 0;">
            <h3 style="color: #f57c00; font-size: 18px; margin: 0 0 15px 0;">
                🏆 Logros Desbloqueados Esta Semana
            </h3>
            @foreach($achievements as $achievement)
            <div style="padding: 10px 0; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                <strong style="color: #333;">{{ $achievement['name'] }}</strong><br>
                <span style="color: #666; font-size: 14px;">{{ $achievement['description'] }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Comparison -->
        @if(isset($comparison))
        <div style="background: #e3f2fd; border-radius: 10px; padding: 20px; margin: 30px 0;">
            <h3 style="color: #1976d2; font-size: 18px; margin: 0 0 15px 0;">
                📈 Comparado con la Semana Pasada
            </h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0;">
                        Sesiones: 
                        @if($comparison['sessions_change'] > 0)
                            <strong style="color: #4caf50;">+{{ $comparison['sessions_change'] }}% ⬆️</strong>
                        @elseif($comparison['sessions_change'] < 0)
                            <strong style="color: #f44336;">{{ $comparison['sessions_change'] }}% ⬇️</strong>
                        @else
                            <strong style="color: #666;">Sin cambio</strong>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;">
                        XP Ganado: 
                        @if($comparison['xp_change'] > 0)
                            <strong style="color: #4caf50;">+{{ $comparison['xp_change'] }}% ⬆️</strong>
                        @elseif($comparison['xp_change'] < 0)
                            <strong style="color: #f44336;">{{ $comparison['xp_change'] }}% ⬇️</strong>
                        @else
                            <strong style="color: #666;">Sin cambio</strong>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        @endif

        <!-- Recommendations -->
        <div style="background: #f1f8e9; border-left: 4px solid #8bc34a; padding: 15px; margin: 30px 0; border-radius: 5px;">
            <h4 style="color: #558b2f; margin: 0 0 10px 0; font-size: 16px;">
                💡 Recomendación para esta semana
            </h4>
            <p style="color: #666; margin: 0; font-size: 14px;">
                @if($currentStreak < 3)
                    ¡Intenta practicar al menos 3 días seguidos para construir tu racha! La constancia es clave.
                @elseif($weekStats['total_sessions'] < 5)
                    ¡Excelente racha! Intenta aumentar a 5 sesiones esta semana para maximizar tu progreso.
                @else
                    ¡Increíble compromiso! Sigue así y alcanzarás el siguiente nivel muy pronto.
                @endif
            </p>
        </div>

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" style="margin: 40px 0;">
            <tr>
                <td style="text-align: center;">
                    <a href="{{ $loginUrl }}" style="background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%); color: white; padding: 16px 40px; text-decoration: none; border-radius: 30px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 15px rgba(78, 84, 200, 0.3);">
                        🚀 Continuar Practicando
                    </a>
                </td>
            </tr>
        </table>

        <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
            ¡Sigue así!<br>
            <strong>El equipo de TypeMaster AI</strong>
        </p>
    </td>
</tr>

<!-- Footer Note -->
<tr>
    <td style="padding: 20px 30px; background: #f8f9fa;">
        <p style="color: #999; font-size: 12px; margin: 0; text-align: center;">
            Recibes este reporte porque eres usuario activo de TypeMaster AI.<br>
            Si prefieres no recibirlo, puedes <a href="{{ $settingsUrl }}" style="color: #4e54c8; text-decoration: none;">configurar tus preferencias aquí</a>
        </p>
    </td>
</tr>
@endsection
