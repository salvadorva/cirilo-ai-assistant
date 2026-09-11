@extends('../layout.app')
@section('title','Editar Audio - Administración')
@section('css')
<style>
    .audio-detail-card {
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    .action-btn {
        min-width: 150px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="{{ route('admin.audios.index') }}" class="btn btn-secondary mb-3">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
            <h2><i class="fas fa-microphone me-2"></i>Detalle del Audio #{{ $audio->id }}</h2>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Información del Audio -->
            <div class="card audio-detail-card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Tipo:</strong>
                        </div>
                        <div class="col-md-8">
                            @if($audio->type == 'welcome')
                                <span class="badge bg-primary">Mensaje de Bienvenida</span>
                            @else
                                <span class="badge bg-info">Frase Graciosa #{{ $audio->index }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Estado:</strong>
                        </div>
                        <div class="col-md-8">
                            @if($audio->is_approved && $audio->is_active)
                                <span class="badge bg-success">Aprobado y Activo</span>
                            @elseif($audio->is_approved)
                                <span class="badge bg-secondary">Aprobado (Inactivo)</span>
                            @else
                                <span class="badge bg-warning">Pendiente de Aprobación</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Regeneraciones:</strong>
                        </div>
                        <div class="col-md-8">
                            {{ $audio->regeneration_count }} veces
                            @if($audio->last_regenerated_at)
                                <br><small class="text-muted">Última: {{ $audio->last_regenerated_at->diffForHumans() }}</small>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Creado:</strong>
                        </div>
                        <div class="col-md-8">
                            {{ $audio->created_at->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Texto y Edición -->
            <div class="card audio-detail-card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Texto del Audio</h5>
                </div>
                <div class="card-body">
                    <form id="editTextForm" action="{{ route('admin.audios.update', $audio->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="text" class="form-label">Texto (editar regenerará el audio):</label>
                            <textarea class="form-control" id="text" name="text" rows="5" maxlength="1000">{{ $audio->text }}</textarea>
                            <small class="text-muted">Máximo 1000 caracteres</small>
                        </div>
                        <button type="submit" class="btn btn-warning action-btn">
                            <i class="fas fa-edit me-1"></i>Actualizar y Regenerar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Reproductor de Audio -->
            <div class="card audio-detail-card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-volume-up me-2"></i>Reproductor de Audio</h5>
                </div>
                <div class="card-body text-center">
                    @if($audio->audio_url)
                        <audio id="audioPlayer" controls class="w-100 mb-3">
                            <source src="{{ $audio->audio_url }}" type="audio/mpeg">
                            Tu navegador no soporta el elemento audio.
                        </audio>
                        <p class="text-muted mb-0">
                            <small>
                                <i class="fas fa-check-circle text-success me-1"></i>
                                Archivo: {{ basename($audio->file_path) }}
                            </small>
                        </p>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay archivo de audio disponible
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Acciones -->
            <div class="card audio-detail-card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Acciones</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <!-- Aprobar y Activar -->
                        @if(!$audio->is_approved || !$audio->is_active)
                        <button type="button" class="btn btn-success action-btn" id="approveBtn">
                            <i class="fas fa-check me-1"></i>Aprobar y Activar
                        </button>
                        @endif

                        <!-- Regenerar Audio -->
                        <button type="button" class="btn btn-primary action-btn" id="regenerateBtn">
                            <i class="fas fa-sync me-1"></i>Regenerar Audio
                        </button>

                        <!-- Toggle Activo/Inactivo -->
                        @if($audio->is_approved)
                        <button type="button" class="btn btn-{{ $audio->is_active ? 'warning' : 'info' }} action-btn" id="toggleActiveBtn">
                            <i class="fas fa-{{ $audio->is_active ? 'pause' : 'play' }} me-1"></i>
                            {{ $audio->is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                        @endif

                        <!-- Eliminar (solo si no está aprobado) -->
                        @if(!$audio->is_approved)
                        <button type="button" class="btn btn-danger action-btn" id="deleteBtn">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Ayuda -->
            <div class="card audio-detail-card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Ayuda</h5>
                </div>
                <div class="card-body">
                    <p><strong>Aprobar y Activar:</strong> Marca este audio como correcto y lo pone en uso.</p>
                    <p><strong>Regenerar:</strong> Genera un nuevo audio con el mismo texto (útil si el acento no es claro).</p>
                    <p><strong>Editar Texto:</strong> Cambia el texto y genera nuevo audio automáticamente.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '{{ csrf_token() }}';

    // Aprobar y activar
    @if(!$audio->is_approved || !$audio->is_active)
    document.getElementById('approveBtn').addEventListener('click', function() {
        Swal.fire({
            title: '¿Aprobar y activar?',
            text: 'Este audio se marcará como aprobado y se activará para uso',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ route("admin.audios.approve", $audio->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error de red: ' + error.message, 'error');
                });
            }
        });
    });
    @endif

    // Regenerar audio
    document.getElementById('regenerateBtn').addEventListener('click', function() {
        Swal.fire({
            title: '¿Regenerar audio?',
            text: 'Se generará un nuevo audio con el mismo texto',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, regenerar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Regenerando...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch('{{ route("admin.audios.regenerate", $audio->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        document.getElementById('audioPlayer').src = data.audio_url + '?t=' + new Date().getTime();
                        Swal.fire('¡Éxito!', data.message, 'success');
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire('Error', 'Error de red: ' + error.message, 'error');
                });
            }
        });
    });

    // Mostrar mensajes de sesión
    @if(session('success'))
        Swal.fire('¡Éxito!', '{{ session("success") }}', 'success');
    @endif

    @if(session('error'))
        Swal.fire('Error', '{{ session("error") }}', 'error');
    @endif
});
</script>
@endpush
