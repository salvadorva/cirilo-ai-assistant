@extends('layout.app')

@section('title', $pageTitle ?? 'TypeMaster AI - Lecciones')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-success text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-book-open me-3"></i>Lecciones de Mecanografía
                            </h1>
                            <p class="lead mb-3">
                                Progresa paso a paso desde lo básico hasta nivel avanzado. Cada lección está diseñada para mejorar gradualmente tu velocidad y precisión.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-route me-2"></i>
                                    <span>Progresión estructurada</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-target me-2"></i>
                                    <span>Objetivos claros</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-medal me-2"></i>
                                    <span>Recompensas por logros</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="bg-white bg-opacity-20 rounded-3 p-3">
                                <h3 class="mb-1">Nivel {{ $gameProgress->level }}</h3>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: {{ $gameProgress->xp_needed > 0 ? (($gameProgress->current_xp / $gameProgress->xp_needed) * 100) : 0 }}%"></div>
                                </div>
                                <small>{{ $gameProgress->current_xp }} / {{ $gameProgress->xp_needed }} XP</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="text-primary me-3">
                                    <i class="fas fa-graduation-cap fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 text-primary">{{ count($userProgress) }}</h4>
                                    <small class="text-muted">Lecciones Completadas</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="text-success me-3">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 text-success">{{ $groupedLessons->flatten()->count() }}</h4>
                                    <small class="text-muted">Total Lecciones</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="text-warning me-3">
                                    <i class="fas fa-percentage fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 text-warning">{{ $groupedLessons->flatten()->count() > 0 ? round((count($userProgress) / $groupedLessons->flatten()->count()) * 100) : 0 }}%</h4>
                                    <small class="text-muted">Progreso Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="text-info me-3">
                                    <i class="fas fa-star fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 text-info">{{ $gameProgress->total_xp }}</h4>
                                    <small class="text-muted">XP Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lessons by Level -->
    @foreach($groupedLessons as $level => $lessons)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h3 class="mb-0">
                            <i class="fas {{ $level === 'beginner' ? 'fa-seedling' : ($level === 'intermediate' ? 'fa-tree' : 'fa-mountain') }} me-2 
                               text-{{ $level === 'beginner' ? 'success' : ($level === 'intermediate' ? 'warning' : 'danger') }}"></i>
                            @switch($level)
                                @case('beginner')
                                    Principiante
                                    @break
                                @case('intermediate')
                                    Intermedio
                                    @break
                                @case('advanced')
                                    Avanzado
                                    @break
                            @endswitch
                        </h3>
                        <span class="badge bg-{{ $level === 'beginner' ? 'success' : ($level === 'intermediate' ? 'warning' : 'danger') }}">
                            {{ $lessons->whereIn('id', $userProgress)->count() }} / {{ $lessons->count() }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($lessons as $lesson)
                        <div class="col-lg-6 col-xl-4 mb-3">
                            <div class="card h-100 border lesson-card {{ in_array($lesson->id, $userProgress) ? 'border-success' : ($lesson->canUserAccess($user->id) ? 'border-primary' : 'border-secondary') }}"
                                 style="cursor: {{ $lesson->canUserAccess($user->id) ? 'pointer' : 'not-allowed' }};"
                                 onclick="{{ $lesson->canUserAccess($user->id) ? "window.location.href='" . route('typing.lesson', $lesson->id) . "'" : '' }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0 {{ $lesson->canUserAccess($user->id) ? 'text-primary' : 'text-muted' }}">
                                            <span class="badge bg-secondary me-2">{{ $lesson->lesson_number }}</span>
                                            {{ $lesson->title }}
                                        </h6>
                                        @if(in_array($lesson->id, $userProgress))
                                            <i class="fas fa-check-circle text-success fa-lg"></i>
                                        @elseif($lesson->canUserAccess($user->id))
                                            <i class="fas fa-play-circle text-primary fa-lg"></i>
                                        @else
                                            <i class="fas fa-lock text-muted fa-lg"></i>
                                        @endif
                                    </div>
                                    
                                    <p class="card-text small text-muted mb-2">{{ $lesson->description }}</p>
                                    
                                    <div class="row text-center mb-2">
                                        <div class="col-6">
                                            <div class="small">
                                                <i class="fas fa-tachometer-alt text-primary"></i>
                                                <strong>{{ $user->getAdjustedTargetWpm($lesson->target_wpm) }}</strong> WPM
                                                @if($user->age && $user->getSpeedMultiplier() < 1.0)
                                                    <small class="text-muted d-block">({{ $lesson->target_wpm }} base)</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small">
                                                <i class="fas fa-bullseye text-success"></i>
                                                <strong>{{ $lesson->target_accuracy }}%</strong> Precisión
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @if(count($lesson->focus_keys) > 0)
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Teclas de enfoque:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach(array_slice($lesson->focus_keys, 0, 8) as $key)
                                            <span class="badge bg-light text-dark border">{{ strtoupper($key) }}</span>
                                            @endforeach
                                            @if(count($lesson->focus_keys) > 8)
                                            <span class="badge bg-light text-muted border">+{{ count($lesson->focus_keys) - 8 }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <div class="small text-muted">
                                        <i class="fas fa-clock"></i>
                                        ~{{ round($lesson->estimated_duration / 60) }} min
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Back to Dashboard -->
    <div class="row">
        <div class="col-12 text-center">
            <a href="{{ route('typing.index') }}" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
            </a>
        </div>
    </div>
</div>

<style>
.lesson-card {
    transition: all 0.3s ease;
}

.lesson-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.lesson-card:not(.border-secondary):hover {
    border-color: var(--bs-primary) !important;
}

.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}
</style>

<script>
// Auto-refresh progress if needed
document.addEventListener('DOMContentLoaded', function() {
    // Add any interactive features here
});
</script>

@endsection
