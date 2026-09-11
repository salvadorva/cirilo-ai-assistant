@extends('emails.layout')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="color: #333; font-size: 28px; margin: 0 0 20px 0; font-weight: 600;">
            @if($campaignType === 'urgent')
                ¡{{ $userName }}, te extrañamos mucho! 💚
            @elseif($campaignType === 'final')
                Una última oportunidad...
            @else
                ¡Hola {{ $userName }}! Vuelve al Asistente Cirilo
            @endif
        </h1>
        
        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
            @if($campaignType === 'urgent')
                Han pasado <strong>{{ $daysInactive }} días</strong> desde tu última sesión. ¡Tu progreso te está esperando!
            @elseif($campaignType === 'final')
                Notamos que no has vuelto desde hace <strong>{{ $daysInactive }} días</strong>. ¿Todo está bien?
            @else
                Han pasado {{ $daysInactive }} días sin verte. ¡Te preparamos algo especial para tu regreso!
            @endif
        </p>

        <!-- Progress Stats -->
        <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 30px 0;">
            <h3 style="color: #333; font-size: 18px; margin: 0 0 15px 0;">
                📊 Tu Progreso Actual
            </h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; width: 50%;">
                        <span style="color: #666;">Nivel alcanzado:</span><br>
                        <strong style="color: #4e54c8; font-size: 24px;">{{ $currentLevel }}</strong>
                    </td>
                    <td style="padding: 8px 0; width: 50%;">
                        <span style="color: #666;">XP Total:</span><br>
                        <strong style="color: #8f94fb; font-size: 24px;">{{ number_format($totalXP) }}</strong>
                    </td>
                </tr>
                @if($longestStreak > 0)
                <tr>
                    <td colspan="2" style="padding: 8px 0;">
                        <span style="color: #666;">Mejor racha:</span><br>
                        <strong style="color: #ff6b6b; font-size: 20px;">🔥 {{ $longestStreak }} días</strong>
                    </td>
                </tr>
                @endif
            </table>
        </div>

        @if(count($incentives) > 0)
        <!-- Special Offers -->
        <div style="background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%); border-radius: 10px; padding: 25px; margin: 30px 0;">
            <h3 style="color: white; font-size: 20px; margin: 0 0 15px 0;">
                🎁 Regalos especiales por tu regreso
            </h3>
            @foreach($incentives as $incentive)
            <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 15px; margin: 10px 0;">
                <strong style="color: white; font-size: 16px; display: block; margin-bottom: 5px;">
                    {{ $incentive['title'] }}
                </strong>
                <span style="color: rgba(255,255,255,0.9); font-size: 14px;">
                    {{ $incentive['description'] }}
                </span>
            </div>
            @endforeach
            <p style="color: rgba(255,255,255,0.9); font-size: 13px; margin: 15px 0 0 0; text-align: center;">
                ⏰ Válido por 7 días desde tu primer ingreso
            </p>
        </div>
        @endif

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" style="margin: 40px 0;">
            <tr>
                <td style="text-align: center;">
                    <a href="{{ $loginUrl }}" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 30px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                        @if($campaignType === 'urgent')
                            🚀 Reactivar mi cuenta ahora
                        @else
                            ✨ Volver al Asistente Cirilo
                        @endif
                    </a>
                </td>
            </tr>
        </table>

        @if($campaignType === 'urgent')
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="color: #856404; margin: 0; font-size: 14px;">
                <strong>⚠️ Importante:</strong> Si no regresas pronto, tu progreso podría perderse. ¡No dejes que todo tu esfuerzo se desperdicie!
            </p>
        </div>
        @endif

        <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0;">
            Esperamos verte pronto,<br>
            <strong>Tu asistente Cirilo</strong>
        </p>
    </td>
</tr>

<!-- Footer Note -->
<tr>
    <td style="padding: 20px 30px; background: #f8f9fa;">
        <p style="color: #999; font-size: 12px; margin: 0; text-align: center;">
            Si prefieres no recibir estos recordatorios, puedes 
            <a href="{{ $settingsUrl }}" style="color: #4e54c8; text-decoration: none;">configurar tus preferencias aquí</a>
        </p>
    </td>
</tr>
@endsection
