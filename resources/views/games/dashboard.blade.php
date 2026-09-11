@extends('layout.app')

@section('title', 'Dashboard de Juegos')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- AI Notification Area -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <div class="me-3">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center shadow" style="width: 60px; height: 60px;">
                                <i class="fas fa-robot fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-2">Nexus (Tu Coach IA) dice:</h5>
                            <div id="ai-content">
                                @if(isset($aiSummary))
                                    <p class="lead mb-3" id="ai-message-text">{{ $aiSummary['message'] }}</p>
                                    
                                    <div class="d-flex align-items-center gap-3">
                                        @if(isset($aiSummary['audioUrl']))
                                            <audio id="ai-audio" controls autoplay class="d-none">
                                                <source src="{{ $aiSummary['audioUrl'] }}" type="audio/mpeg">
                                            </audio>
                                            <button class="btn btn-light btn-sm rounded-pill px-3 shadow-sm" onclick="playAudio()">
                                                <i class="fas fa-volume-up me-1"></i> Escuchar de nuevo
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <p class="lead mb-3">Analizando tu progreso para darte recomendaciones...</p>
                                @endif
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-light btn-sm rounded-pill px-3" id="btn-refresh-summary" onclick="refreshSummary()">
                                    <i class="fas fa-sync-alt me-1"></i> Actualizar mi avance
                                </button>
                                <span class="ms-2 small opacity-75" id="last-updated">
                                    @if(isset($aiSummary['generated_at']))
                                        Actualizado {{ \Carbon\Carbon::parse($aiSummary['generated_at'])->diffForHumans() }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Cards -->
        <div class="col-12 mb-4">
            <h4 class="mb-3 fw-bold text-secondary">Tus Juegos</h4>
            <div class="row g-4">
                <!-- English Games Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 transition-hover">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-language fa-3x"></i>
                            </div>
                            <h4 class="card-title fw-bold">English Games</h4>
                            <p class="text-muted">Aprende inglés divirtiéndote con retos de vocabulario y gramática.</p>
                            
                            <div class="d-flex justify-content-center gap-3 mb-4">
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold">{{ $englishStats['total_sessions'] }}</h5>
                                    <small class="text-muted">Sesiones</small>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold">{{ number_format($englishStats['total_xp']) }}</h5>
                                    <small class="text-muted">XP</small>
                                </div>
                            </div>
                            
                            <a href="{{ route('games.english') }}" class="btn btn-success w-100 rounded-pill">
                                <i class="fas fa-play me-2"></i> Jugar Ahora
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Typing Master Card -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 transition-hover">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-keyboard fa-3x"></i>
                            </div>
                            <h4 class="card-title fw-bold">TypeMaster AI</h4>
                            <p class="text-muted">Mejora tu velocidad de escritura con ejercicios potenciados por IA.</p>
                            
                            <div class="d-flex justify-content-center gap-3 mb-4">
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold">{{ $typingStats['total_sessions'] }}</h5>
                                    <small class="text-muted">Sesiones</small>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold">{{ number_format($typingStats['total_xp']) }}</h5>
                                    <small class="text-muted">XP</small>
                                </div>
                            </div>
                            
                            <a href="{{ route('typing.index') }}" class="btn btn-primary w-100 rounded-pill">
                                <i class="fas fa-play me-2"></i> Jugar Ahora
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Math Games Card (Coming Soon) -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 bg-light" style="opacity: 0.8;">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-calculator fa-3x"></i>
                            </div>
                            <h4 class="card-title fw-bold text-muted">Matemáticas</h4>
                            <p class="text-muted">Desafíos matemáticos para ejercitar tu mente.</p>
                            
                            <div class="alert alert-secondary d-inline-block py-1 px-3 rounded-pill mb-4">
                                <small class="fw-bold">PRÓXIMAMENTE</small>
                            </div>
                            
                            <button class="btn btn-secondary w-100 rounded-pill" disabled>
                                <i class="fas fa-lock me-2"></i> Bloqueado
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Section (Simplified from original dashboard) -->
        <div class="col-12">
            <h4 class="mb-3 fw-bold text-secondary">Tu Progreso General</h4>
        </div>

        <!-- XP Summary Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Nivel {{ $xpSummary['level'] }}</h5>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">XP: {{ number_format($xpSummary['current_level_xp']) }} / {{ number_format($xpSummary['required_level_xp']) }}</small>
                            <small class="fw-bold text-primary">{{ $xpSummary['level_progress_percent'] }}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $xpSummary['level_progress_percent'] }}%;" aria-valuenow="{{ $xpSummary['level_progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                        <span><i class="fas fa-trophy text-warning me-2"></i> XP Total</span>
                        <strong>{{ number_format($xpSummary['total_xp']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                        <span><i class="fas fa-fire text-danger me-2"></i> Racha Actual</span>
                        <strong>{{ $progress->current_streak }} días</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Chart -->
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Actividad Semanal</h5>
                    <div style="height: 200px;">
                        <canvas id="weeklyProgressChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboard Section -->
        <div class="col-12 mb-4">
            <div class="row">
                <!-- Top 3 Users -->
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-trophy text-warning me-2"></i>Top 3 de la Semana
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                @forelse($leaderboard as $index => $player)
                                    <div class="list-group-item border-0 d-flex align-items-center px-0 py-3">
                                        <div class="me-3 position-relative">
                                            @if($index === 0)
                                                <i class="fas fa-crown text-warning position-absolute top-0 start-50 translate-middle-x" style="margin-top: -12px;"></i>
                                            @endif
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($player->user->name) }}&background={{ $index === 0 ? 'ffc107' : ($index === 1 ? 'adb5bd' : 'cd7f32') }}&color=fff" 
                                                 class="rounded-circle" width="48" height="48" alt="{{ $player->user->name }}">
                                            <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-dark border border-white small">
                                                #{{ $index + 1 }}
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 fw-bold">{{ $player->user->name }}</h6>
                                            <small class="text-muted">Nivel {{ $player->user->gameProgress->level ?? 1 }}</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-bold text-primary">{{ number_format($player->total_xp) }} XP</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-ghost fa-2x mb-2"></i>
                                        <p>Aún no hay competidores esta semana.</p>
                                    </div>
                                @endforelse
                            </div>
                            
                            @if($userRank > 3)
                                <div class="mt-3 pt-3 border-top text-center">
                                    <p class="mb-0 text-muted small">Tu posición actual:</p>
                                    <h5 class="fw-bold text-secondary">#{{ $userRank }}</h5>
                                    <small class="text-muted">¡Sigue jugando para subir!</small>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Comparative Graph -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-chart-bar text-primary me-2"></i>Tú vs. El Mejor
                            </h5>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <div style="height: 200px;">
                                <canvas id="comparisonChart"></canvas>
                            </div>
                            <div class="mt-3 text-center">
                                @if($comparisonData['gap'] > 0)
                                    <p class="mb-0 text-muted">
                                        Te faltan <strong class="text-primary">{{ number_format($comparisonData['gap']) }} XP</strong> para alcanzar al líder.
                                    </p>
                                @else
                                    <p class="mb-0 text-success fw-bold">
                                        ¡Eres el líder de la semana! 🏆
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Audio Playback
    function playAudio() {
        const audio = document.getElementById('ai-audio');
        if (audio) {
            audio.currentTime = 0;
            audio.play();
        }
    }

    // Refresh Summary Logic
    async function refreshSummary() {
        const btn = document.getElementById('btn-refresh-summary');
        const originalContent = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Analizando...';
        
        try {
            const response = await fetch('/games/refresh-summary', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update UI
                const summary = data.summary;
                const contentDiv = document.getElementById('ai-content');
                
                let html = `<p class="lead mb-3" id="ai-message-text">${summary.message}</p>`;
                
                if (summary.audioUrl) {
                    html += `
                        <div class="d-flex align-items-center gap-3">
                            <audio id="ai-audio" controls autoplay class="d-none">
                                <source src="${summary.audioUrl}" type="audio/mpeg">
                            </audio>
                            <button class="btn btn-light btn-sm rounded-pill px-3 shadow-sm" onclick="playAudio()">
                                <i class="fas fa-volume-up me-1"></i> Escuchar de nuevo
                            </button>
                        </div>
                    `;
                }
                
                contentDiv.innerHTML = html;
                document.getElementById('last-updated').innerText = 'Actualizado hace un momento';
                
                // Play audio automatically
                setTimeout(() => playAudio(), 500);
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Análisis actualizado',
                    showConfirmButton: false,
                    timer: 3000
                });
            } else {
                throw new Error(data.error || 'Error desconocido');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo actualizar el análisis. Intenta de nuevo.'
            });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    // Weekly Chart
    const weeklyProgressCtx = document.getElementById('weeklyProgressChart').getContext('2d');
    new Chart(weeklyProgressCtx, {
        type: 'bar',
        data: {
            labels: @json(array_column($dailyProgress, 'day')),
            datasets: [{
                label: 'XP Ganado',
                data: @json(array_column($dailyProgress, 'xp')),
                backgroundColor: 'rgba(118, 75, 162, 0.7)',
                borderColor: 'rgba(118, 75, 162, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 2] }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Comparison Chart
    const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
    new Chart(comparisonCtx, {
        type: 'bar',
        data: {
            labels: ['Tú', 'Líder'],
            datasets: [{
                label: 'XP Semanal',
                data: [{{ $comparisonData['user_xp'] }}, {{ $comparisonData['top_xp'] }}],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)', // Azul para el usuario
                    'rgba(255, 193, 7, 0.7)'   // Amarillo para el líder
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 2] }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>

<style>
    .transition-hover {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .transition-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
</style>
@endpush
@endsection
