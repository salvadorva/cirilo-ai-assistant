{{-- Sección de imagen educativa --}}
@php
    \Illuminate\Support\Facades\Log::info('Verificando condiciones de imagen', [
        'imageDescription_isset' => isset($imageDescription) ? 'si' : 'no',
        'imageDescription_empty' => isset($imageDescription) ? (empty($imageDescription) ? 'si' : 'no') : 'N/A',
        'imageDescription_value' => $imageDescription ?? 'no-definido',
        'hasImage' => $hasImage ?? 'no-definido'
    ]);
@endphp

{{-- Siempre mostrar la sección de imagen, independientemente de la descripción --}}
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">
                <i class="fas fa-image me-2 text-info"></i>Material Visual de Apoyo
            </h5>
            
            @php
                $imagePath = "courses/{$userId}/{$course->id}/img/session_{$session->id}.png";
                $imageUrl = $hasImage ? Storage::disk('public')->url($imagePath) : null;
            @endphp
            
            <div class="alert alert-light border">
                <div class="d-flex align-items-start">
                    @if($hasImage)
                        <img src="{{ $imageUrl }}" alt="Imagen educativa" class="img-fluid rounded mb-3" 
                             style="max-width: 100%; height: auto;">
                    @else
                        <i class="fas fa-camera fa-2x text-muted me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2">
                                Descripción de la imagen educativa:
                            </h6>
                            <p class="mb-2">{{ $imageDescription }}</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock text-warning me-1"></i>
                                    <span id="image-status-{{ $session->id }}">
                                        Imagen pendiente de generar
                                    </span>
                                </small>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="generateSessionResources({{ $course->id }}, {{ $session->id }}, 'image')" 
                                        id="generate-image-btn-{{ $session->id }}">
                                    <i class="fas fa-magic me-1"></i>Generar Imagen
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
