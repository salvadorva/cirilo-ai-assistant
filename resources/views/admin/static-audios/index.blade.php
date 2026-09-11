@extends('../layout.app')
@section('title','Audios Estáticos - Administración')
@section('css')
<style>
    .audio-card {
        transition: all 0.3s ease;
        border-radius: 12px;
    }
    .audio-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.35rem 0.75rem;
    }
    .audio-player-mini {
        max-width: 250px;
    }
    .stats-card {
        border-radius: 12px;
        border-left: 4px solid;
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-microphone me-2"></i>Gestión de Audios Estáticos</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFunnyPhraseModal">
                    <i class="fas fa-plus me-2"></i>Agregar Frase Graciosa
                </button>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card border-primary">
                <div class="card-body">
                    <h6 class="text-muted">Total Audios</h6>
                    <h3 class="mb-0">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-success">
                <div class="card-body">
                    <h6 class="text-muted">Aprobados</h6>
                    <h3 class="mb-0">{{ $stats['approved'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-info">
                <div class="card-body">
                    <h6 class="text-muted">Activos</h6>
                    <h3 class="mb-0">{{ $stats['active'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-warning">
                <div class="card-body">
                    <h6 class="text-muted">Pendientes</h6>
                    <h3 class="mb-0">{{ $stats['pending'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.audios.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label>Tipo</label>
                        <select name="type" class="form-select">
                            <option value="">Todos</option>
                            <option value="welcome" {{ request('type') == 'welcome' ? 'selected' : '' }}>Bienvenida</option>
                            <option value="funny_phrase" {{ request('type') == 'funny_phrase' ? 'selected' : '' }}>Frases Graciosas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Estado Aprobación</label>
                        <select name="approved" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" {{ request('approved') === '1' ? 'selected' : '' }}>Aprobados</option>
                            <option value="0" {{ request('approved') === '0' ? 'selected' : '' }}>Pendientes</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Estado Activo</label>
                        <select name="active" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Activos</option>
                            <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i>Filtrar
                            </button>
                            <a href="{{ route('admin.audios.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Audios -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Texto</th>
                            <th>Índice</th>
                            <th>Estado</th>
                            <th>Regenerado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($audios as $audio)
                        <tr>
                            <td>{{ $audio->id }}</td>
                            <td>
                                @if($audio->type == 'welcome')
                                    <span class="badge bg-primary">Bienvenida</span>
                                @else
                                    <span class="badge bg-info">Frase Graciosa</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($audio->text, 60) }}</td>
                            <td>{{ $audio->index ?? '-' }}</td>
                            <td>
                                @if($audio->is_approved && $audio->is_active)
                                    <span class="badge bg-success status-badge">Aprobado y Activo</span>
                                @elseif($audio->is_approved)
                                    <span class="badge bg-secondary status-badge">Aprobado</span>
                                @else
                                    <span class="badge bg-warning status-badge">Pendiente</span>
                                @endif
                            </td>
                            <td>{{ $audio->regeneration_count }} veces</td>
                            <td>
                                <a href="{{ route('admin.audios.show', $audio->id) }}" class="btn btn-sm btn-primary" title="Ver/Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No hay audios registrados</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-center">
                {{ $audios->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Modal Agregar Frase Graciosa -->
<div class="modal fade" id="addFunnyPhraseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Agregar Frase Graciosa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addFunnyPhraseForm">
                    @csrf
                    <div class="mb-3">
                        <label for="phrase_text" class="form-label">Texto de la Frase</label>
                        <textarea class="form-control" id="phrase_text" name="text" rows="3" required maxlength="1000"></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>
                    <div id="audioPreview" class="mb-3" style="display: none;">
                        <label class="form-label">Preview del Audio:</label>
                        <audio id="previewAudio" controls class="w-100"></audio>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="generatePreviewBtn">
                    <i class="fas fa-play me-1"></i>Generar y Previsualizar
                </button>
                <button type="button" class="btn btn-success" id="savePhraseBtn" style="display: none;">
                    <i class="fas fa-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let generatedAudioUrl = null;

    // Botón de generar preview
    document.getElementById('generatePreviewBtn').addEventListener('click', function() {
        const text = document.getElementById('phrase_text').value.trim();

        if (!text) {
            Swal.fire('Error', 'Por favor ingresa un texto', 'error');
            return;
        }

        Swal.fire({
            title: 'Generando audio...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("admin.audios.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ text: text })
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();

            if (data.success) {
                generatedAudioUrl = data.audio_url;
                document.getElementById('audioPreview').style.display = 'block';
                document.getElementById('previewAudio').src = generatedAudioUrl;
                document.getElementById('savePhraseBtn').style.display = 'inline-block';
                document.getElementById('generatePreviewBtn').disabled = true;

                Swal.fire('¡Éxito!', 'Audio generado. Escúchalo y apruébalo', 'success');
            } else {
                Swal.fire('Error', data.message || 'Error al generar audio', 'error');
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire('Error', 'Error de red: ' + error.message, 'error');
        });
    });

    // Botón de guardar frase
    document.getElementById('savePhraseBtn').addEventListener('click', function() {
        Swal.fire({
            title: '¡Frase guardada!',
            text: 'La frase ha sido agregada exitosamente',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then(() => {
            location.reload();
        });
    });

    // Resetear modal al cerrar
    document.getElementById('addFunnyPhraseModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('addFunnyPhraseForm').reset();
        document.getElementById('audioPreview').style.display = 'none';
        document.getElementById('savePhraseBtn').style.display = 'none';
        document.getElementById('generatePreviewBtn').disabled = false;
        generatedAudioUrl = null;
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
