@extends('layout.app')
@section('title', 'Crear Usuario')
@section('content')
<div class="container">
    <h2>Crear Usuario</h2>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="role_id" class="form-label">Rol</label>
            <select class="form-select" id="role_id" name="role_id" required>
                <option value="">Seleccione un rol</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label for="age" class="form-label">Edad</label>
            <input type="number" class="form-control" id="age" name="age" value="{{ old('age') }}" min="6" max="120" placeholder="Edad del usuario">
            <div class="form-text">
                La edad se utiliza para personalizar la velocidad objetivo de escritura y la dificultad del contenido.
            </div>
        </div>
        <div class="mb-3">
            <label for="prompt" class="form-label">Prompt</label>
            <textarea class="form-control" id="prompt" name="prompt" rows="3">{{ old('prompt') }}</textarea>
        </div>
        <div class="mb-3">
            <label for="ai_provider" class="form-label">Proveedor de IA</label>
            <select class="form-select" id="ai_provider" name="ai_provider" required>
                <option value="">Seleccione un proveedor</option>
                <option value="openai" {{ old('ai_provider') == 'openai' ? 'selected' : '' }}>OpenAI (GPT-4o, Imágenes, TTS)</option>
                <option value="grok" {{ old('ai_provider') == 'grok' ? 'selected' : '' }}>Grok (X.AI) - Datos en tiempo real</option>
            </select>
            <div class="form-text">
                <strong>OpenAI:</strong> Ideal para tareas generales, generación de imágenes y texto a voz.<br>
                <strong>Grok:</strong> Perfecto para consultas sobre actualidad, noticias y juegos con datos en tiempo real.
            </div>
        </div>
        <div class="mb-3">
            <label for="daily_image_limit" class="form-label">Límite Diario de Imágenes</label>
            <input type="number" class="form-control" id="daily_image_limit" name="daily_image_limit" value="{{ old('daily_image_limit', 4) }}" min="0" max="100">
            <div class="form-text">
                <i class="fa-solid fa-image text-primary me-1"></i>
                Número máximo de imágenes que el usuario puede generar por día. El valor por defecto es 4.
                <br>
                <strong>0 = Sin límite</strong> (solo recomendado para cuentas especiales)
            </div>
        </div>
        <button type="submit" class="btn btn-success">Crear</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
