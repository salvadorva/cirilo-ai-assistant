<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Asistente Cirilo')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .main-message {
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.7;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .achievement-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            margin: 5px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
        }
        
        .incentive-card {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        
        .incentive-title {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 10px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            border-radius: 10px;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .unsubscribe-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 15px 0;
            font-size: 13px;
            color: #856404;
        }
        
        .unsubscribe-notice strong {
            color: #664d03;
        }
        
        .settings-link {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 25px;
            margin-top: 10px;
            font-weight: 600;
            text-decoration: none !important;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s;
        }
        
        .settings-link:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8a 100%);
            color: #ffffff !important;
            transform: translateY(-2px);
        }
        
        /* Forzar color blanco en links dentro de botones */
        a.settings-link,
        a.settings-link:visited,
        a.settings-link:active,
        a.settings-link:link {
            color: #ffffff !important;
        }
        
        .instructions {
            background: #e7f3ff;
            border-left: 3px solid #667eea;
            padding: 12px 15px;
            margin: 10px 0;
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        .instructions strong {
            color: #667eea;
        }
        
        .emoji {
            font-size: 1.2em;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>@yield('header-title', 'Asistente Cirilo')</h1>
            <div class="subtitle">@yield('header-subtitle', 'Tu asistente de aprendizaje')</div>
        </div>
        
        <div class="content">
            @yield('content')
        </div>
        
        <div class="footer">
            <div class="unsubscribe-notice">
                <strong>📧 ¿No deseas recibir estos correos?</strong>
                <p style="margin: 8px 0 0 0;">
                    Puedes desactivar las notificaciones por email en cualquier momento desde tu configuración.
                </p>
                <a href="{{ url('/settings') }}" class="settings-link" style="color: #ffffff !important;">
                    ⚙️ Ir a Configuración
                </a>
                
                <div class="instructions">
                    <strong>📍 ¿Cómo llegar?</strong><br>
                    1. Haz clic en el botón de arriba o inicia sesión en el Asistente Cirilo<br>
                    2. Haz clic en tu nombre (esquina superior derecha)<br>
                    3. Selecciona "<strong>Configuración</strong>" en el menú desplegable<br>
                    4. Ve a la pestaña "<strong>Notificaciones</strong>"<br>
                    5. Desactiva "<strong>Notificaciones por Email</strong>"
                </div>
            </div>
            
            <p style="margin-top: 15px;">Este email fue enviado desde tu asistente <strong>Cirilo</strong></p>
            <p style="font-size: 12px; color: #999;">
                <a href="{{ url('/') }}">Ir a la app</a> | 
                <a href="{{ url('/settings') }}">Ajustes</a> | 
                <a href="{{ url('/profile') }}">Mi perfil</a>
            </p>
        </div>
    </div>
</body>
</html>