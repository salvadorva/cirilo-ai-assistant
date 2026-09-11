@extends('layout.app')

@section('title', $session->title . ' - ' . $course->title)

@section('content')
    {{-- Incluir estilos y encabezado --}}
    @include('courses.partials.session_header')

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1>{{ $session->title }}</h1>
                <p class="text-muted">
                    <i class="fas fa-book me-1"></i>{{ $course->title }} - 
                    <i class="fas fa-clock me-1"></i>Sesión {{ $session->order }}
                </p>
                
                @if($isCompleted)
                    <div class="badge bg-success completion-badge mb-3">
                        <i class="fas fa-check-circle me-1"></i>Sesión completada
                    </div>
                @endif
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                {{-- Contenido de la sesión --}}
                <div class="content-section">
                    <h3 class="mb-3">
                        <i class="fas fa-book-open me-2 text-primary"></i>Contenido de la Sesión
                    </h3>
                    
                    {{-- Sección de imagen --}}
                    {{-- DEBUG: Variables disponibles --}}
                    @php
                        \Illuminate\Support\Facades\Log::info('Variables disponibles en session_modular', [
                            'hasImage' => $hasImage ?? 'no-definido',
                            'hasAudio' => $hasAudio ?? 'no-definido',
                            'audioScript_existe' => isset($audioScript) ? 'si' : 'no',
                            'audioScript_longitud' => isset($audioScript) ? strlen($audioScript) : 0,
                            'practiceActivity_existe' => isset($practiceActivity) ? 'si' : 'no',
                            'imageDescription_existe' => isset($imageDescription) ? 'si' : 'no',
                            'userId' => $userId ?? 'no-definido',
                        ]);
                    @endphp
                    
                    {{-- DEBUG: Incluyendo parcial de imagen --}}
                    @include('courses.partials.session_image')
                    
                    {{-- DEBUG: Incluyendo parcial de contenido --}}
                    @include('courses.partials.session_content')
                    
                    {{-- DEBUG: Incluyendo parcial de audio --}}
                    @include('courses.partials.session_audio')
                    
                </div>
                
                {{-- Actividad práctica --}}
                {{-- DEBUG: Incluyendo parcial de práctica --}}
                @include('courses.partials.session_practice')
                
                {{-- Botón para marcar como completada --}}
                <div class="mt-4 text-center">
                    @if(!$isCompleted)
                        <form action="{{ route('courses.complete.session', ['course' => $course->id, 'session' => $session->id]) }}" 
                              method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-lg btn-success">
                                <i class="fas fa-check-circle me-1"></i>Marcar como completada
                            </button>
                        </form>
                    @else
                        <form action="{{ route('courses.session', ['course' => $course->id, 'session' => $session->id]) }}" 
                              method="GET" class="d-inline">
                            <button type="submit" class="btn btn-lg btn-outline-secondary">
                                <i class="fas fa-times-circle me-1"></i>Volver a la sesión
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            
            <div class="col-md-4">
                {{-- Navegación entre sesiones --}}
                @include('courses.partials.session_navigation')
                
                {{-- Información adicional --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle me-2 text-primary"></i>Información
                        </h5>
                        <p class="mb-1">
                            <strong>Curso:</strong> {{ $course->title }}
                        </p>
                        <p class="mb-1">
                            <strong>Sesión:</strong> {{ $session->order }} de {{ $course->sessions->count() }}
                        </p>
                        <p class="mb-0">
                            <strong>Estado:</strong> 
                            @if($isCompleted)
                                <span class="text-success">Completada</span>
                            @else
                                <span class="text-warning">Pendiente</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Incluir scripts --}}
    @include('courses.partials.session_scripts')
@endpush
