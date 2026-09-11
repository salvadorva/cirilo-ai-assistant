<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Evento</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            color: #3788d8;
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
        }
        .event-card {
            border-left: 4px solid {{ $event->color ?? '#3788d8' }};
            background-color: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .event-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .event-time {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }
        .event-location {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .event-description {
            font-size: 14px;
            color: #555;
            margin-top: 15px;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3788d8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recordatorio de Evento</h1>
        </div>
        
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            
            <p>Te recordamos que tienes un evento programado en <strong>10 minutos</strong>:</p>
            
            <div class="event-card">
                <div class="event-title">{{ $event->title }}</div>
                
                <div class="event-time">
                    <strong>Fecha y hora:</strong> 
                    @if($event->all_day)
                        {{ $event->start_date->format('d/m/Y') }} (Todo el día)
                    @else
                        {{ $event->start_date->format('d/m/Y H:i') }} - 
                        @if($event->end_date)
                            @if($event->start_date->format('d/m/Y') == $event->end_date->format('d/m/Y'))
                                {{ $event->end_date->format('H:i') }}
                            @else
                                {{ $event->end_date->format('d/m/Y H:i') }}
                            @endif
                        @endif
                    @endif
                </div>
                
                @if($event->location)
                <div class="event-location">
                    <strong>Ubicación:</strong> {{ $event->location }}
                </div>
                @endif
                
                @if($event->description)
                <div class="event-description">
                    {{ $event->description }}
                </div>
                @endif
            </div>
            
            <p>No olvides prepararte para este evento.</p>
            
            <div style="text-align: center;">
                <a href="{{ url('/agenda') }}" class="button">Ver en mi agenda</a>
            </div>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
            <p>&copy; {{ date('Y') }} Asistente Virtual - Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>
