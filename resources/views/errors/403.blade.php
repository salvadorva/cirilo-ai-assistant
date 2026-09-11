@extends('layout.app')

@section('title', 'Acceso Denegado')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-shield-alt text-danger" style="font-size: 5rem;"></i>
                    </div>
                    
                    <h1 class="display-4 text-danger mb-3">403</h1>
                    <h2 class="mb-3">Acceso Denegado</h2>
                    
                    <p class="text-muted mb-4">
                        @if(isset($exception) && $exception->getMessage())
                            {{ $exception->getMessage() }}
                        @else
                            No tienes permisos para acceder a esta sección.
                        @endif
                    </p>
                    
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Esta sección está restringida solo para <strong>administradores</strong>.
                    </div>
                    
                    <div class="mt-4">
                        <a href="{{ route('idex_home') }}" class="btn btn-primary btn-lg">
                            <i class="fas fa-home me-2"></i>
                            Volver al Inicio
                        </a>
                        
                        @if(Auth::check())
                        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-arrow-left me-2"></i>
                            Regresar
                        </a>
                        @endif
                    </div>
                    
                    @if(!Auth::check())
                    <div class="mt-4">
                        <p class="text-muted">
                            ¿Eres administrador? 
                            <a href="{{ route('login') }}" class="text-primary">Inicia sesión aquí</a>
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            
            @if(Auth::check() && Auth::user()->role)
            <div class="card mt-3 border-0 bg-light">
                <div class="card-body text-center">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Usuario actual: <strong>{{ Auth::user()->name }}</strong> 
                        (Rol: <strong>{{ Auth::user()->role->name }}</strong>)
                    </small>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
    .card {
        border-radius: 15px;
    }
    
    .btn-lg {
        padding: 12px 30px;
        border-radius: 8px;
    }
    
    @media (max-width: 576px) {
        .btn-lg {
            display: block;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-lg.ms-2 {
            margin-left: 0 !important;
        }
    }
</style>
@endsection
