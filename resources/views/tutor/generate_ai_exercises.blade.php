@extends('layout.app')

@section('title', 'Generar Ejercicios con IA')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-warning { background-color: #ffc107; border-color: #ffc107; }
    .btn-warning:hover { background-color: #ffca2c; border-color: #ffca2c; }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-robot me-2"></i>Recomendación de Ejercicios con IA</h2>
        <div>
            <span class="badge bg-primary p-2">Nivel {{ $level }}</span>
            <a href="{{ route('tutor.exercises', ['level' => $level]) }}" class="btn btn-sm btn-outline-primary ms-2">
                <i class="fas fa-arrow-left me-2"></i>Volver a Ejercicios
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div>
                            <h5>¿Cómo funciona la recomendación personalizada?</h5>
                            <p class="mb-0">
                                Nuestro sistema de inteligencia artificial analiza tu progreso en las diferentes áreas de aprendizaje 
                                (vocabulario, gramática, conversación y comprensión auditiva) para recomendarte el tipo de ejercicio 
                                que más te ayudará a mejorar tus habilidades en inglés. La recomendación se basa en tus puntuaciones 
                                anteriores y en las áreas donde necesitas más práctica.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recomendaciones personalizadas -->
        <div class="card bg-light">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-lightbulb text-warning" style="font-size: 5rem;"></i>
                    <h3 class="mt-3">Obtén una Recomendación Personalizada</h3>
                    <p class="text-muted">
                        Deja que nuestra IA analice tu progreso y te recomiende el tipo de ejercicio 
                        más adecuado para mejorar tus habilidades en inglés.
                    </p>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    <button id="getRecommendationBtn" class="btn btn-warning btn-lg">
                        <i class="fas fa-star me-2"></i>Obtener Recomendación
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Botón para obtener recomendación personalizada
        const recommendationBtn = document.getElementById('getRecommendationBtn');
        if (recommendationBtn) {
            recommendationBtn.addEventListener('click', function() {
                // Mostrar indicador de carga
                Swal.fire({
                    title: 'Analizando tu progreso...',
                    html: 'Estamos evaluando tus puntuaciones para ofrecerte la mejor recomendación.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Realizar solicitud para obtener recomendación
                fetch('{{ route("tutor.recommendations") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        level: '{{ $level }}'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mostrar recomendación
                        Swal.fire({
                            icon: 'success',
                            title: '¡Recomendación lista!',
                            html: `
                                <p>${data.message}</p>
                                <p class="mt-3">Tipo de ejercicio recomendado: <strong>${data.type_name}</strong></p>
                            `,
                            confirmButtonText: 'Ir al ejercicio',
                            showCancelButton: true,
                            cancelButtonText: 'Más tarde'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = `{{ url('tutor/practice') }}/${data.type}/{{ $level }}`;
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo obtener una recomendación en este momento.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo más tarde.'
                    });
                });
            });
        }
    });
</script>
@endpush
@endsection
