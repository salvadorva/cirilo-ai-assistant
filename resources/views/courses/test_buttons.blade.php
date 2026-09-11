    @extends('layout.app')

@section('content')
<div class="container">
    <h1>Prueba de Botones</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Botón de Generación de Imagen</h5>
            
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fas fa-clock text-warning me-1"></i>
                    <span id="image-status-test">Imagen pendiente de generar</span>
                </small>
                
                <button type="button" class="btn btn-sm btn-outline-primary" 
                        onclick="testGenerateImage()" 
                        id="generate-image-btn-test">
                    <i class="fas fa-magic me-1"></i>Generar Imagen
                </button>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Botón de Generación de Audio</h5>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="alert('Reproducir audio')">
                        <i class="fas fa-play me-1"></i>Reproducir Audio
                    </button>
                    
                    <small class="text-muted">
                        <i class="fas fa-clock text-warning me-1"></i>
                        <span id="audio-status-test">Audio pendiente de generar</span>
                    </small>
                </div>
                
                <button type="button" class="btn btn-sm btn-outline-success" 
                        onclick="testGenerateAudio()" 
                        id="generate-audio-btn-test">
                    <i class="fas fa-microphone me-1"></i>Generar Audio
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function testGenerateImage() {
    alert('Botón de generación de imagen funciona correctamente');
}

function testGenerateAudio() {
    alert('Botón de generación de audio funciona correctamente');
}
</script>
@endsection
