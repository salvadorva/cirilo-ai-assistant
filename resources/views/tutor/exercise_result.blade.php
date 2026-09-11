@extends('layout.app')

@section('title', 'Resultado de Ejercicio')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .badge-vocabulary { background-color: #3498db; }
    .badge-grammar { background-color: #e74c3c; }
    .badge-speaking { background-color: #2ecc71; }
    .badge-listening { background-color: #f39c12; }
    .result-section { border-left: 4px solid #009ef7; padding-left: 15px; }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold">
            <i class="fa-solid fa-file-alt me-2"></i>Resultado de Ejercicio
        </h2>
        <div>
            <a href="{{ route('tutor.exercise.history') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Volver al Historial
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Encabezado del resultado -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge badge-{{ $result->type }} me-2" style="font-size: 1rem; padding: 8px 12px;">
                        @if($result->type == 'vocabulary')
                            <i class="fas fa-book me-1"></i> Vocabulario
                        @elseif($result->type == 'grammar')
                            <i class="fas fa-pencil-alt me-1"></i> Gramática
                        @elseif($result->type == 'speaking')
                            <i class="fas fa-microphone me-1"></i> Speaking
                        @elseif($result->type == 'listening')
                            <i class="fas fa-headphones me-1"></i> Listening
                        @endif
                    </span>
                    <span class="badge bg-primary" style="font-size: 1rem; padding: 8px 12px;">Nivel {{ $result->level }}</span>
                </div>
                <p class="text-muted">
                    <i class="fas fa-calendar-alt me-2"></i>{{ $result->created_at->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="col-md-6 text-end">
                <div class="display-4 fw-bold text-primary">{{ $result->score }}/100</div>
                <div class="progress mt-2" style="height: 20px;">
                    <div class="progress-bar {{ $result->score >= 80 ? 'bg-success' : ($result->score >= 60 ? 'bg-warning' : 'bg-danger') }}" 
                         style="width: {{ $result->score }}%">{{ $result->score }}%</div>
                </div>
            </div>
        </div>
        
        <hr>
        
        <!-- Contenido del ejercicio -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h4 class="mb-3"><i class="fas fa-tasks me-2"></i>Ejercicio</h4>
                <div class="border p-3 rounded bg-light">
                    {{ $result->exercise_content }}
                </div>
            </div>
        </div>
        
        <!-- Respuesta del usuario -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h4 class="mb-3"><i class="fas fa-pen me-2"></i>Tu Respuesta</h4>
                <div class="border p-3 rounded">
                    {{ $result->user_answer }}
                </div>
            </div>
        </div>
        
        <!-- Feedback -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h4 class="mb-3"><i class="fas fa-comment-dots me-2"></i>Feedback</h4>
                <div class="result-section mb-3">
                    <p>{{ $result->feedback['text'] ?? 'No hay feedback disponible.' }}</p>
                </div>
                
                @if(isset($result->details['strengths']))
                <h5 class="text-success"><i class="fas fa-thumbs-up me-2"></i>Fortalezas</h5>
                <div class="result-section mb-3 border-success">
                    <p>{{ $result->details['strengths'] }}</p>
                </div>
                @endif
                
                @if(isset($result->details['improvements']))
                <h5 class="text-warning"><i class="fas fa-arrow-up me-2"></i>Áreas de Mejora</h5>
                <div class="result-section mb-3 border-warning">
                    <p>{{ $result->details['improvements'] }}</p>
                </div>
                @endif
                
                @if(isset($result->details['suggestions']))
                <h5 class="text-info"><i class="fas fa-lightbulb me-2"></i>Sugerencias</h5>
                <div class="result-section mb-3 border-info">
                    <p>{{ $result->details['suggestions'] }}</p>
                </div>
                @endif
                
                @if($result->type == 'speaking' && isset($result->details['word_analysis']))
                <h5><i class="fas fa-microphone-alt me-2"></i>Análisis de Pronunciación</h5>
                <div class="result-section mb-3">
                    <p>{{ $result->details['word_analysis'] }}</p>
                </div>
                @endif
                
                <!-- Audio feedback si está disponible -->
                @if($result->audio_url)
                <div class="mt-4">
                    <h5><i class="fas fa-volume-up me-2"></i>Escuchar Feedback</h5>
                    <audio controls class="w-100">
                        <source src="{{ $result->audio_url }}" type="audio/mpeg">
                        Tu navegador no soporta el elemento de audio.
                    </audio>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div class="row">
            <div class="col-md-12 text-center">
                <a href="{{ route('tutor.practice', ['type' => $result->type, 'level' => $result->level]) }}" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i>Practicar de Nuevo
                </a>
                <button id="deleteResult" class="btn btn-danger ms-2">
                    <i class="fas fa-trash me-2"></i>Eliminar Resultado
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Botón para eliminar resultado
        document.getElementById('deleteResult').addEventListener('click', function() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "No podrás revertir esta acción",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Enviar solicitud para eliminar
                    fetch('{{ route("tutor.exercise.destroy", $result->id) }}', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                '¡Eliminado!',
                                'El resultado ha sido eliminado.',
                                'success'
                            ).then(() => {
                                // Redirigir al historial
                                window.location.href = '{{ route("tutor.exercise.history") }}';
                            });
                        } else {
                            Swal.fire(
                                'Error',
                                data.message || 'No se pudo eliminar el resultado',
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire(
                            'Error',
                            'Ocurrió un error al procesar la solicitud',
                            'error'
                        );
                    });
                }
            });
        });
    });
</script>
@endpush
@endsection
