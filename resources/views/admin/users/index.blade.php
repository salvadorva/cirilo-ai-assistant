@extends('layout.app')
@section('title', 'Usuarios')
@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Usuarios</h2>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Nuevo Usuario</a>
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Proveedor IA</th>
                    <th>Prompt</th>
                    <th>Notificaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role->name ?? '-' }}</td>
                    <td>
                        @if($user->ai_provider === 'openai')
                            <span class="badge bg-success">
                                <i class="fas fa-brain"></i> OpenAI
                            </span>
                        @elseif($user->ai_provider === 'grok')
                            <span class="badge bg-info">
                                <i class="fas fa-bolt"></i> Grok
                            </span>
                        @else
                            <span class="badge bg-secondary">No definido</span>
                        @endif
                    </td>
                    <td class="text-truncate" style="max-width:200px">{{ $user->prompt }}</td>
                    <td>
                        @if($user->email_notifications_enabled)
                            <span class="badge bg-success">
                                <i class="fas fa-bell"></i> Activas
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                <i class="fas fa-bell-slash"></i> Inactivas
                            </span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-warning" title="Editar usuario">
                            <i class="fa fa-edit"></i>
                        </a>
                        
                        <form method="POST" action="{{ route('admin.users.toggle-notifications', $user) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $user->email_notifications_enabled ? 'btn-secondary' : 'btn-success' }}" 
                                    title="{{ $user->email_notifications_enabled ? 'Desactivar notificaciones' : 'Activar notificaciones' }}">
                                <i class="fas {{ $user->email_notifications_enabled ? 'fa-bell-slash' : 'fa-bell' }}"></i>
                            </button>
                        </form>
                        
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" title="Eliminar usuario">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $users->links() }}
    </div>
</div>
@endsection
