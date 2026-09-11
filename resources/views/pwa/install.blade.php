@extends('layout.app')

@section('title', 'Instalar Aplicación - Asistente IA')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-mobile-alt me-2"></i>
                        Instalar Asistente IA como Aplicación
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-star me-2"></i>
                                Beneficios de la PWA
                            </h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Acceso rápido desde tu escritorio o pantalla de inicio
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Funciona offline para contenido previamente cargado
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Notificaciones push para recordatorios
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Experiencia similar a una app nativa
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Actualizaciones automáticas
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Menor consumo de datos
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Cómo instalar
                            </h5>
                            <div class="alert alert-info">
                                <strong>Instalación automática:</strong><br>
                                Si tu navegador soporta PWA, aparecerá un botón "Instalar App" en la esquina inferior derecha.
                            </div>
                            
                            <div class="accordion" id="installInstructions">
                                <!-- Chrome Desktop -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="chromeDesktop">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#chromeDesktopContent">
                                            <i class="fab fa-chrome me-2"></i>
                                            Chrome (Escritorio)
                                        </button>
                                    </h2>
                                    <div id="chromeDesktopContent" class="accordion-collapse collapse" data-bs-parent="#installInstructions">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Haz clic en el ícono de instalación en la barra de direcciones</li>
                                                <li>O ve a Menú → Más herramientas → Crear acceso directo</li>
                                                <li>Marca "Abrir como ventana"</li>
                                                <li>Haz clic en "Crear"</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Chrome Mobile -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="chromeMobile">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#chromeMobileContent">
                                            <i class="fas fa-mobile-alt me-2"></i>
                                            Chrome (Móvil)
                                        </button>
                                    </h2>
                                    <div id="chromeMobileContent" class="accordion-collapse collapse" data-bs-parent="#installInstructions">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Toca el menú (⋮) en la esquina superior derecha</li>
                                                <li>Selecciona "Agregar a pantalla de inicio"</li>
                                                <li>Confirma el nombre de la aplicación</li>
                                                <li>Toca "Agregar"</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Safari iOS -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="safariIOS">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#safariIOSContent">
                                            <i class="fab fa-safari me-2"></i>
                                            Safari (iOS)
                                        </button>
                                    </h2>
                                    <div id="safariIOSContent" class="accordion-collapse collapse" data-bs-parent="#installInstructions">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Toca el botón de compartir (□↗)</li>
                                                <li>Desplázate hacia abajo y toca "Agregar a inicio"</li>
                                                <li>Confirma el nombre de la aplicación</li>
                                                <li>Toca "Agregar"</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edge -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="edge">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#edgeContent">
                                            <i class="fab fa-edge me-2"></i>
                                            Microsoft Edge
                                        </button>
                                    </h2>
                                    <div id="edgeContent" class="accordion-collapse collapse" data-bs-parent="#installInstructions">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Haz clic en el ícono de instalación en la barra de direcciones</li>
                                                <li>O ve a Menú (⋯) → Aplicaciones → Instalar este sitio como aplicación</li>
                                                <li>Confirma el nombre de la aplicación</li>
                                                <li>Haz clic en "Instalar"</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <button id="manual-install-btn" class="btn btn-primary btn-lg me-3" style="display: none;">
                            <i class="fas fa-download me-2"></i>
                            Instalar Ahora
                        </button>
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver al Inicio
                        </a>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            La instalación es segura y no requiere permisos especiales
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const manualInstallBtn = document.getElementById('manual-install-btn');
    
    // Detectar si PWA puede ser instalada
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        
        // Mostrar botón manual
        manualInstallBtn.style.display = 'inline-block';
        
        manualInstallBtn.addEventListener('click', function() {
            e.prompt();
            e.userChoice.then(function(choiceResult) {
                if (choiceResult.outcome === 'accepted') {
                    Swal.fire({
                        title: '¡Instalación exitosa!',
                        text: 'La aplicación se ha instalado correctamente',
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/';
                    });
                }
                manualInstallBtn.style.display = 'none';
            });
        });
    });
    
    // Detectar si ya está instalada
    if (window.matchMedia('(display-mode: standalone)').matches) {
        document.querySelector('.card-body').innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                <h3 class="mt-3 text-success">¡Aplicación ya instalada!</h3>
                <p class="text-muted">Estás usando la versión PWA de Asistente IA</p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>
                    Ir al Inicio
                </a>
            </div>
        `;
    }
});
</script>
@endsection
