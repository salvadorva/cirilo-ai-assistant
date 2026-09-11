@extends('layout.app')

@section('content')
<div class="notifications-page">
    <div class="container">
        <!-- Header -->
        <div class="notifications-page-header">
            <div>
                <h1>
                    <i class="fas fa-bell"></i>
                    Mis Notificaciones
                </h1>
                <p>Mantente al día con todas tus actualizaciones y logros</p>
            </div>
            <div>
                @if(auth()->user()->notifications()->unread()->count() > 0)
                <button type="button" class="btn btn-success" id="markAllReadBtnPage" onclick="markAllAsReadPage()">
                    <i class="fas fa-check-double"></i>
                    Marcar todas como leídas
                </button>
                @endif
            </div>
        </div>

        <!-- Filtros de Estado -->
        <div class="notification-status-filters">
            <a href="{{ route('notifications.index', ['status' => 'unread']) }}" 
               class="status-filter-btn {{ ($status ?? 'unread') === 'unread' ? 'active' : '' }}">
                <i class="fas fa-envelope"></i>
                No leídas
                <span class="badge">{{ auth()->user()->notifications()->unread()->count() }}</span>
            </a>
            <a href="{{ route('notifications.index', ['status' => 'read']) }}" 
               class="status-filter-btn {{ ($status ?? 'unread') === 'read' ? 'active' : '' }}">
                <i class="fas fa-envelope-open"></i>
                Leídas
            </a>
            <a href="{{ route('notifications.index', ['status' => 'all']) }}" 
               class="status-filter-btn {{ ($status ?? 'unread') === 'all' ? 'active' : '' }}">
                <i class="fas fa-list"></i>
                Todas
                <span class="badge">{{ $notifications->total() }}</span>
            </a>
        </div>

        <!-- Filtros por Tipo -->
        <div class="notification-filters">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-filter"></i>
                Todos los tipos
            </button>
            <button class="filter-btn" data-filter="achievement">
                <i class="fas fa-trophy"></i>
                Logros
            </button>
            <button class="filter-btn" data-filter="lesson">
                <i class="fas fa-book"></i>
                Lecciones
            </button>
            <button class="filter-btn" data-filter="system">
                <i class="fas fa-cog"></i>
                Sistema
            </button>
            <button class="filter-btn" data-filter="reminder">
                <i class="fas fa-clock"></i>
                Recordatorios
            </button>
        </div>

        <!-- Lista de Notificaciones -->
        <div class="notifications-list" id="notificationsList">
            @forelse($notifications as $notification)
                <div class="notification-card {{ $notification->is_read ? '' : 'unread' }}" 
                     data-id="{{ $notification->id }}"
                     data-type="{{ $notification->type }}">
                    
                    <!-- Icono -->
                    <div class="notification-card-icon type-{{ $notification->type }}">
                        <i class="{{ $notification->icon ?? 'fas fa-bell' }}"></i>
                    </div>
                    
                    <!-- Contenido -->
                    <div class="notification-card-content">
                        <div class="notification-card-header">
                            <h3 class="notification-card-title">{{ $notification->title }}</h3>
                        </div>
                        
                        <p class="notification-card-message">{{ $notification->message }}</p>
                        
                        <!-- Footer con tiempo y acciones -->
                        <div class="notification-card-footer">
                            <div class="notification-card-time">
                                <i class="fas fa-clock"></i>
                                {{ $notification->created_at->diffForHumans() }}
                            </div>
                            
                            <div class="notification-card-actions">
                                @if(!$notification->is_read)
                                    <button class="btn btn-mark-read" 
                                            onclick="markAsRead({{ $notification->id }})">
                                        <i class="fas fa-check"></i>
                                        Marcar como leída
                                    </button>
                                @endif
                                
                                @if($notification->action_url)
                                    <a href="{{ $notification->action_url }}" 
                                       class="btn btn-primary">
                                        <i class="fas fa-arrow-right"></i>
                                        Ver más
                                    </a>
                                @endif
                                
                                <button class="btn btn-delete" 
                                        onclick="deleteNotification({{ $notification->id }})">
                                    <i class="fas fa-trash"></i>
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="notification-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No tienes notificaciones en este momento</p>
                </div>
            @endforelse
        </div>

        <!-- Paginación -->
        @if($notifications->hasPages())
            <div class="notifications-pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>

<script>
// Función para marcar como leída
function markAsRead(notificationId) {
    fetch(`/notifications/${notificationId}/mark-read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar visualmente la notificación
            const card = document.querySelector(`[data-id="${notificationId}"]`);
            card.classList.remove('unread');
            
            // Remover el botón de marcar como leída
            const btn = card.querySelector('.btn-mark-read');
            if (btn) btn.remove();
            
            // Actualizar contador de no leídas
            updateUnreadCount();
            
            // Toast de éxito
            Swal.fire({
                icon: 'success',
                title: 'Marcada como leída',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo marcar la notificación como leída'
        });
    });
}

// Función para eliminar notificación
function deleteNotification(notificationId) {
    Swal.fire({
        title: '¿Eliminar notificación?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/notifications/${notificationId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animar y remover la tarjeta
                    const card = document.querySelector(`[data-id="${notificationId}"]`);
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Verificar si quedan notificaciones
                        const remaining = document.querySelectorAll('.notification-card').length;
                        if (remaining === 0) {
                            document.getElementById('notificationsList').innerHTML = `
                                <div class="notification-empty">
                                    <i class="fas fa-inbox"></i>
                                    <p>No tienes notificaciones en este momento</p>
                                </div>
                            `;
                        }
                    }, 300);
                    
                    // Actualizar contador
                    updateUnreadCount();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo eliminar la notificación'
                });
            });
        }
    });
}

// Función para actualizar el contador de no leídas
function updateUnreadCount() {
    const unreadCards = document.querySelectorAll('.notification-card.unread').length;
    const unreadBadges = document.querySelectorAll('.notification-filters [data-filter="unread"] .badge');
    unreadBadges.forEach(badge => {
        badge.textContent = unreadCards;
    });
}

// Filtros
document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remover active de todos
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        
        // Agregar active al clickeado
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const cards = document.querySelectorAll('.notification-card');
        
        cards.forEach(card => {
            if (filter === 'all') {
                card.style.display = 'flex';
            } else if (filter === 'unread') {
                card.style.display = card.classList.contains('unread') ? 'flex' : 'none';
            } else {
                card.style.display = card.dataset.type === filter ? 'flex' : 'none';
            }
        });
    });
});

// Función para marcar todas como leídas desde el botón de la página
function markAllAsReadPage() {
    Swal.fire({
        title: '¿Marcar todas como leídas?',
        text: 'Esto marcará todas tus notificaciones no leídas como leídas',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, marcar todas',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar todas las tarjetas
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        const btn = card.querySelector('.btn-mark-read');
                        if (btn) btn.remove();
                    });
                    
                    // Ocultar botón de marcar todas
                    const markAllBtn = document.getElementById('markAllReadBtnPage');
                    if (markAllBtn) markAllBtn.style.display = 'none';
                    
                    updateUnreadCount();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Todas marcadas como leídas',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo marcar las notificaciones como leídas'
                });
            });
        }
    });
}

// Marcar todas como leídas (acción rápida desde teclado)
document.addEventListener('keydown', function(e) {
    // Ctrl+Shift+M para marcar todas como leídas
    if (e.ctrlKey && e.shiftKey && e.key === 'M') {
        e.preventDefault();
        markAllAsReadPage();
    }
});
</script>
@endsection
