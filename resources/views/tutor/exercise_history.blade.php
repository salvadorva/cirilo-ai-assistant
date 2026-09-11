@extends('layout.app')

@section('title', 'Historial de Ejercicios')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .exercise-item { transition: transform 0.2s; }
    .exercise-item:hover { transform: translateY(-3px); }
    .badge-vocabulary { background-color: #3498db; }
    .badge-grammar { background-color: #e74c3c; }
    .badge-speaking { background-color: #2ecc71; }
    .badge-listening { background-color: #f39c12; }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-history me-2"></i>Historial de Ejercicios</h2>
        <div>
            <a href="{{ route('tutor.exercises') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Volver a Ejercicios
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form action="{{ route('tutor.exercise.history') }}" method="GET" class="d-flex gap-3">
                    <div class="form-group">
                        <label for="type">Tipo de Ejercicio</label>
                        <select name="type" id="type" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="vocabulary" {{ request('type') == 'vocabulary' ? 'selected' : '' }}>Vocabulario</option>
                            <option value="grammar" {{ request('type') == 'grammar' ? 'selected' : '' }}>Gramática</option>
                            <option value="speaking" {{ request('type') == 'speaking' ? 'selected' : '' }}>Speaking</option>
                            <option value="listening" {{ request('type') == 'listening' ? 'selected' : '' }}>Listening</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level">Nivel</label>
                        <select name="level" id="level" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="A1" {{ request('level') == 'A1' ? 'selected' : '' }}>A1</option>
                            <option value="A2" {{ request('level') == 'A2' ? 'selected' : '' }}>A2</option>
                            <option value="B1" {{ request('level') == 'B1' ? 'selected' : '' }}>B1</option>
                            <option value="B2" {{ request('level') == 'B2' ? 'selected' : '' }}>B2</option>
                            <option value="C1" {{ request('level') == 'C1' ? 'selected' : '' }}>C1</option>
                            <option value="C2" {{ request('level') == 'C2' ? 'selected' : '' }}>C2</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Resultados -->
        <div class="row">
            <div class="col-md-12">
                @if($results->isEmpty())
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No se encontraron resultados de ejercicios. Completa algunos ejercicios para ver tu historial.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Nivel</th>
                                    <th>Puntuación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $result)
                                    <tr class="exercise-item">
                                        <td>{{ $result->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $result->type }}">
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
                                        </td>
                                        <td><span class="badge bg-primary">{{ $result->level }}</span></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar {{ $result->score >= 80 ? 'bg-success' : ($result->score >= 60 ? 'bg-warning' : 'bg-danger') }}" 
                                                     style="width: {{ $result->score }}%">{{ $result->score }}%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('tutor.exercise.show', $result->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-result" data-id="{{ $result->id }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $results->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Eliminar resultado
        document.querySelectorAll('.delete-result').forEach(button => {
            button.addEventListener('click', function() {
                const resultId = this.getAttribute('data-id');
                
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
                        fetch(`{{ url('tutor/exercise-result') }}/${resultId}`, {
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
                                    // Recargar la página
                                    window.location.reload();
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
    });
</script>
@endpush
@endsection
