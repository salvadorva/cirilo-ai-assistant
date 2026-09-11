@extends('layout.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        @if($user->avatar)
                            <img src="{{ Storage::url($user->avatar) }}" 
                                 alt="Avatar" 
                                 class="rounded-circle" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        @endif
                        <button class="btn btn-sm btn-primary rounded-circle position-absolute" 
                                style="bottom: 0; right: 0;"
                                data-bs-toggle="modal" 
                                data-bs-target="#avatarModal">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-3">{{ $user->email }}</p>
                    
                    <div class="d-flex justify-content-around mb-3">
                        <div>
                            <div class="h4 mb-0 text-primary">{{ $progress->level ?? 1 }}</div>
                            <small class="text-muted">Nivel</small>
                        </div>
                        <div>
                            <div class="h4 mb-0 text-success">{{ number_format($progress->total_xp ?? 0) }}</div>
                            <small class="text-muted">XP Total</small>
                        </div>
                    </div>
                    
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Racha Actual</span>
                            <strong>{{ $progress->current_streak ?? 0 }} días</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Mejor Racha</span>
                            <strong class="text-warning">{{ $progress->longest_streak ?? 0 }} días</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Estadísticas</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <small class="text-muted">Sesiones Totales</small>
                            <div class="fw-bold">{{ $stats['total_sessions'] }}</div>
                        </li>
                        <li class="mb-2">
                            <small class="text-muted">Miembro desde</small>
                            <div class="fw-bold">{{ $stats['member_since'] }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Success Message -->
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <!-- Errors -->
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            <!-- Información Personal -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Información Personal</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo</label>
                                <input type="text" 
                                       name="name" 
                                       class="form-control" 
                                       value="{{ old('name', $user->name) }}" 
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="{{ old('email', $user->email) }}" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Edad</label>
                                <input type="number" 
                                       name="age" 
                                       class="form-control" 
                                       value="{{ old('age', $user->age) }}" 
                                       min="5" 
                                       max="120"
                                       placeholder="Tu edad">
                                <small class="text-muted">Ayuda a personalizar la dificultad de los ejercicios</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Biografía</label>
                            <textarea name="bio" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="Cuéntanos un poco sobre ti...">{{ old('bio', $user->bio ?? '') }}</textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logros y Badges -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Logros Recientes</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @if($progress->current_streak >= 7)
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                <i class="fas fa-fire fa-3x text-warning mb-2"></i>
                                <h6>Racha de Fuego</h6>
                                <small class="text-muted">{{ $progress->current_streak }} días consecutivos</small>
                            </div>
                        </div>
                        @endif
                        
                        @if($progress->level >= 10)
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                                <i class="fas fa-star fa-3x text-primary mb-2"></i>
                                <h6>Aprendiz Avanzado</h6>
                                <small class="text-muted">Alcanzaste nivel {{ $progress->level }}</small>
                            </div>
                        </div>
                        @endif
                        
                        @if($progress->total_sessions >= 50)
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                                <i class="fas fa-medal fa-3x text-success mb-2"></i>
                                <h6>Dedicado</h6>
                                <small class="text-muted">{{ $progress->total_sessions }} sesiones completadas</small>
                            </div>
                        </div>
                        @endif
                        
                        @if($progress->current_streak < 7 && $progress->level < 10 && $progress->total_sessions < 50)
                        <div class="col-12 text-center py-4">
                            <i class="fas fa-award fa-3x text-muted mb-3"></i>
                            <p class="text-muted">¡Sigue practicando para desbloquear logros!</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambiar avatar -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-image me-2"></i>Cambiar Avatar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('profile.avatar') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Selecciona una imagen</label>
                        <input type="file" 
                               name="avatar" 
                               class="form-control" 
                               accept="image/*" 
                               required>
                        <small class="text-muted">Formatos: JPG, PNG, GIF (máx. 2MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Avatar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
