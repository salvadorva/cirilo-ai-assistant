@extends('layout.app')

@section('title', 'Sin conexión - Asistente IA')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 text-center">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <i class="fas fa-wifi-slash text-warning" style="font-size: 4rem;"></i>
                    </div>
                    
                    <h2 class="text-dark mb-3">Sin conexión a internet</h2>
                    
                    <p class="text-muted mb-4">
                        Parece que no tienes conexión a internet. Algunas funciones pueden no estar disponibles.
                    </p>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Funciones disponibles offline:</strong>
                        <ul class="list-unstyled mt-2 mb-0">
                            <li>• Ver contenido previamente cargado</li>
                            <li>• Acceder a cursos descargados</li>
                            <li>• Revisar notas guardadas</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button class="btn btn-primary me-md-2" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>
                            Reintentar
                        </button>
                        <button class="btn btn-outline-secondary" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i>
                            Volver
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="fas fa-mobile-alt me-1"></i>
                            Esta aplicación funciona offline gracias a la tecnología PWA
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Verificar conexión cada 5 segundos
setInterval(() => {
    if (navigator.onLine) {
        // Si hay conexión, recargar la página
        window.location.reload();
    }
}, 5000);

// Escuchar eventos de conexión
window.addEventListener('online', () => {
    window.location.reload();
});
</script>
@endsection
