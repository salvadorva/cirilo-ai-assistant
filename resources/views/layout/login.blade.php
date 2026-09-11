<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="{{asset('src/assets/css/light/elements/alert.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('src/assets/css/dark/elements/alert.css')}}">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        .bg {
            background-image: url('{{asset('layout/images/asistente2.png')}}'); /* Cambia esta ruta por la ruta de tu imagen */
            height: 100%;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            background-color: rgba(255, 255, 255, 0.8); /* Fondo blanco semitransparente */
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 350px;
        }
    </style>
</head>
<body>
    <div class="bg">
        <div class="login-container">
            <div class="login-box">
                <h2 class="text-center mb-4">Iniciar Sesión</h2>
                 <!-- INICIA MENSAJES FLASH -->

                 @if(session('success'))
                 <div class="alert alert-light-success alert-dismissible fade show border-0 mb-4" role="alert">
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#03ba06" d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg></button>
                     <strong>Éxito</strong> {{ session('success') }}</button>
                 </div> 
                 @endif
     
                 @if(session('error'))
                 <div class="alert alert-light-danger alert-dismissible fade show border-0 mb-4" role="alert">
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#c70a1d" d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg></button>
                     <strong>Error</strong> {{ session('error') }}</button>
                 </div> 
                 @endif
                 @if ($errors->any())
                    <div class="alert alert-light-danger alert-dismissible fade show border-0 mb-4" role="alert">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <strong>Errores</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                     <!-- FINALIZA MENSAJES FLASH -->
                <form id="login-form" method="POST" action="{{ route('login_check') }}">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="email">Usuario</label>
                        <input type="email" class="form-control" name ="email" id="email" placeholder="Usuario" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="password">Contraseña</label>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Contraseña" required>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label text-muted" for="remember" style="font-size:0.9rem;">
                            Recordarme en este dispositivo
                        </label>
                    </div>
                    <input type="hidden" id="recaptcha-token" name="recaptcha_token">
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/js-cookie@3.0.1/dist/js.cookie.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.2/umd/popper.min.js" integrity="sha512-2rNj2KJ+D8s1ceNasTIex6z4HWyOnEYLVC3FigGOmyQCZc2eBXKgOxQmo3oKLHyfcj53uz4QMsRCWNbLd32Q1g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
   
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
   
    <!-- Cargar reCAPTCHA de manera más controlada solo si estamos en producción -->
    <script>
        // Variable para controlar si estamos en modo desarrollo
        const isDev = {{ env('DEV') === '1' ? 'true' : 'false' }};
        
        // Definir la clave del sitio directamente en el código para evitar problemas con variables de entorno
        // Esta es la misma clave que está en tu archivo .env
        const RECAPTCHA_SITE_KEY = '6LfioR0lAAAAAFNPb2rBygGGV6yH24TUhzRyZ-Xy';
        
        // Cargar el script de reCAPTCHA de manera programática solo si no estamos en modo desarrollo
        function loadRecaptchaScript() {
            if (isDev) {
                console.log('Modo desarrollo: reCAPTCHA desactivado');
                // En modo desarrollo, simplemente establecemos un valor ficticio en el campo
                document.getElementById('recaptcha-token').value = 'dev-mode-no-validation-needed';
                return;
            }
            
            // Establecer un valor de respaldo en caso de que falle la carga de reCAPTCHA
            document.getElementById('recaptcha-token').value = 'fallback-token-if-recaptcha-fails';
            
            try {
                console.log('Intentando cargar reCAPTCHA con clave:', RECAPTCHA_SITE_KEY);
                
                const script = document.createElement('script');
                script.src = 'https://www.google.com/recaptcha/api.js?render=' + RECAPTCHA_SITE_KEY;
                script.async = true;
                script.defer = true;
                
                // Establecer un timeout para detectar problemas de carga
                const timeoutId = setTimeout(function() {
                    console.warn('Tiempo de espera agotado para cargar reCAPTCHA. Continuando sin él.');
                }, 5000); // 5 segundos de timeout
                
                script.onload = function() {
                    clearTimeout(timeoutId);
                    console.log('reCAPTCHA script cargado correctamente');
                    // Inicializar reCAPTCHA después de cargar el script
                    setTimeout(refreshReCaptchaToken, 1000);
                };
                
                script.onerror = function() {
                    clearTimeout(timeoutId);
                    console.error('Error al cargar el script de reCAPTCHA. Continuando sin él.');
                };
                
                document.head.appendChild(script);
            } catch (error) {
                console.error('Error al intentar cargar reCAPTCHA:', error);
            }
        }
        
        // Cargar el script cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', loadRecaptchaScript);
    </script>
    <script>
        // Variables para control de inactividad y tokens
        let inactivityTimer;
        const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutos en milisegundos
        const CSRF_REFRESH_INTERVAL = 30 * 60 * 1000; // 30 minutos en milisegundos
        let lastActivity = Date.now();
        
        // Función para actualizar el token CSRF
        function refreshCsrfToken() {
            // Simplemente recargar la página para obtener un nuevo token
            console.log('Recargando página para obtener nuevo token CSRF...');
            window.location.reload();
        }
        
        // Función para actualizar el token de reCAPTCHA
        function refreshReCaptchaToken() {
            // Si estamos en modo desarrollo, no necesitamos actualizar el token
            if (isDev) {
                console.log('Modo desarrollo: No es necesario actualizar el token reCAPTCHA');
                return;
            }
            
            // Establecer un contador de intentos
            if (!window.recaptchaAttempts) {
                window.recaptchaAttempts = 0;
            }
            
            try {
                if (typeof grecaptcha !== 'undefined' && grecaptcha && grecaptcha.ready) {
                    grecaptcha.ready(function() {
                        try {
                            grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'login'})
                            .then(function(token) {
                                const recaptchaInput = document.getElementById('recaptcha-token');
                                if (recaptchaInput) {
                                    recaptchaInput.value = token;
                                    console.log('Token reCAPTCHA actualizado exitosamente');
                                    // Resetear contador de intentos
                                    window.recaptchaAttempts = 0;
                                } else {
                                    console.warn('Elemento recaptcha-token no encontrado');
                                }
                            })
                            .catch(function(error) {
                                console.error('Error al ejecutar reCAPTCHA:', error);
                                handleRecaptchaError();
                            });
                        } catch (innerError) {
                            console.error('Error en la ejecución de reCAPTCHA:', innerError);
                            handleRecaptchaError();
                        }
                    });
                } else {
                    console.warn('reCAPTCHA no está completamente inicializado todavía');
                    handleRecaptchaError();
                }
            } catch (error) {
                console.error('Error general en refreshReCaptchaToken:', error);
                handleRecaptchaError();
            }
        }
        
        // Función para manejar errores de reCAPTCHA
        function handleRecaptchaError() {
            window.recaptchaAttempts++;
            
            // Si hemos intentado demasiadas veces, desistir
            if (window.recaptchaAttempts >= 3) {
                console.warn('Demasiados intentos fallidos de reCAPTCHA. Continuando sin él.');
                return;
            }
            
            // Intentar nuevamente después de un tiempo
            setTimeout(refreshReCaptchaToken, 1000 * window.recaptchaAttempts); // Backoff exponencial
        }
        
        // Función para actualizar todos los tokens
        function refreshAllTokens() {
            console.log('Iniciando actualización de todos los tokens...');
            // Primero actualizar CSRF
            refreshCsrfToken();
            
            // Luego actualizar reCAPTCHA solo si está disponible
            if (typeof grecaptcha !== 'undefined' && grecaptcha && grecaptcha.ready) {
                refreshReCaptchaToken();
            } else {
                console.log('reCAPTCHA no disponible aún, se intentará más tarde');
                // Programar un intento posterior para reCAPTCHA
                setTimeout(function() {
                    if (typeof grecaptcha !== 'undefined' && grecaptcha && grecaptcha.ready) {
                        refreshReCaptchaToken();
                    }
                }, 2000);
            }
            
            // Actualizar timestamp de última actividad
            lastActivity = Date.now();
        }
        
        // Función para reiniciar el temporizador de inactividad
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                // Si han pasado más de 5 minutos desde la última actividad
                if (Date.now() - lastActivity >= INACTIVITY_TIMEOUT) {
                    refreshAllTokens();
                }
            }, INACTIVITY_TIMEOUT);
        }
        
        // Detectar actividad del usuario
        $(document).on('mousemove keypress click', function() {
            lastActivity = Date.now();
            resetInactivityTimer();
        });
        
        // Refrescar tokens periódicamente (cada 30 minutos)
        setInterval(refreshAllTokens, CSRF_REFRESH_INTERVAL);
        
        // Inicializar al cargar la página
        $(document).ready(function() {
            // Inicializar reCAPTCHA
            refreshReCaptchaToken();
            
            // Iniciar el temporizador de inactividad
            resetInactivityTimer();
            
            // Manejar el envío del formulario de login
            $('#login-form').on('submit', function(e) {
                // Verificar si estamos en modo desarrollo
                if (isDev) {
                    console.log('Modo desarrollo: Enviando formulario sin reCAPTCHA');
                    $('#recaptcha-token').val('dev-mode-no-validation-needed');
                    return true; // Permitir envío normal del formulario
                }
                
                // En producción, verificar si tenemos reCAPTCHA
                const recaptchaToken = $('#recaptcha-token').val();
                if (!recaptchaToken || recaptchaToken === 'fallback-token-if-recaptcha-fails') {
                    e.preventDefault();
                    
                    // Intentar obtener el token de reCAPTCHA
                    if (typeof grecaptcha !== 'undefined' && grecaptcha && grecaptcha.ready) {
                        grecaptcha.ready(function() {
                            grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'login'})
                            .then(function(token) {
                                $('#recaptcha-token').val(token);
                                console.log('Token reCAPTCHA obtenido, enviando formulario...');
                                $('#login-form').off('submit').submit();
                            })
                            .catch(function(error) {
                                console.error('Error al obtener token reCAPTCHA:', error);
                                // Enviar de todos modos
                                $('#login-form').off('submit').submit();
                            });
                        });
                    } else {
                        // Si no hay reCAPTCHA, enviar de todos modos
                        console.warn('reCAPTCHA no disponible, enviando formulario...');
                        $(this).off('submit').submit();
                    }
                    return false;
                }
                
                // Si ya tenemos token, enviar normalmente
                return true;
            });
        });
        
        // Manejar errores de sesión expirada
        $(document).ajaxError(function(event, xhr, settings) {
            if (xhr.status === 419) {
                console.error('Error 419: CSRF token mismatch');
                refreshAllTokens();
                
                // Mostrar mensaje al usuario
                alert('La sesión ha expirado. Se han actualizado los tokens de seguridad. Por favor, intenta nuevamente.');
            }
        });
    </script>
</body>
</html>
