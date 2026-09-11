/**
 * Sistema de Notificaciones Mejorado - Sprint 9
 * Maneja dropdown, actualización en tiempo real y acciones de notificaciones
 */

(function() {
    'use strict';

    // Variables globales
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationOverlay = document.getElementById('notificationOverlay');
    const notificationsList = document.getElementById('notificationsList');
    let notificationsLoaded = false;
    let autoRefreshInterval = null;

    // Inicializar si los elementos existen
    if (!notificationBell || !notificationDropdown) {
        console.log('Notification elements not found - user may not be authenticated');
        return;
    }

    /**
     * Toggle del dropdown
     */
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });

    /**
     * Abrir/cerrar dropdown
     */
    function toggleDropdown() {
        const isVisible = notificationDropdown.classList.contains('show');
        
        if (isVisible) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    /**
     * Abrir dropdown
     */
    function openDropdown() {
        // Posicionar el dropdown correctamente respecto al botón
        const bellRect = notificationBell.getBoundingClientRect();
        notificationDropdown.style.top = (bellRect.bottom + 10) + 'px';
        notificationDropdown.style.right = (window.innerWidth - bellRect.right) + 'px';
        
        notificationDropdown.classList.add('show');
        if (notificationOverlay) {
            notificationOverlay.classList.add('show');
        }
        
        // Cargar notificaciones si es la primera vez
        if (!notificationsLoaded) {
            loadNotifications();
            notificationsLoaded = true;
        }
        
        // Iniciar auto-refresh cada 30 segundos
        if (!autoRefreshInterval) {
            autoRefreshInterval = setInterval(loadNotifications, 30000);
        }
    }

    /**
     * Cerrar dropdown
     */
    function closeDropdown() {
        notificationDropdown.classList.remove('show');
        if (notificationOverlay) {
            notificationOverlay.classList.remove('show');
        }
        
        // Restaurar scroll del body
        document.body.style.overflow = '';
    }

    /**
     * Función global para cerrar desde el botón X
     */
    window.closeNotifications = function() {
        closeDropdown();
    };

    /**
     * Cerrar con la tecla Escape
     */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && notificationDropdown.classList.contains('show')) {
            closeDropdown();
        }
    });

    /**
     * Prevenir cierre al hacer click dentro del dropdown
     */
    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    /**
     * Cargar notificaciones desde el servidor
     */
    function loadNotifications() {
        // Mostrar loading
        if (!notificationsLoaded) {
            notificationsList.innerHTML = `
                <div class="notification-loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando notificaciones...</p>
                </div>
            `;
        }

        fetch('/notifications/unread')
            .then(response => response.json())
            .then(data => {
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationsList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error al cargar notificaciones</p>
                    </div>
                `;
            });
    }

    /**
     * Renderizar notificaciones en el dropdown
     */
    function renderNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No tienes notificaciones nuevas</p>
                </div>
            `;
            return;
        }

        let html = '';
        
        notifications.forEach(notification => {
            const icon = getNotificationIcon(notification.type);
            const iconClass = `notification-icon type-${notification.type}`;
            
            html += `
                <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                     data-id="${notification.id}"
                     onclick="handleNotificationClick(${notification.id}, '${notification.action_url || ''}')">
                    <div class="${iconClass}">
                        <i class="${icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i>
                            ${formatTime(notification.created_at)}
                        </div>
                        <div class="notification-actions">
                            ${!notification.is_read ? `
                                <button class="btn-mark-read" 
                                        onclick="event.stopPropagation(); markAsRead(${notification.id})">
                                    <i class="fas fa-check"></i> Leída
                                </button>
                            ` : ''}
                            <button class="btn-delete" 
                                    onclick="event.stopPropagation(); deleteNotification(${notification.id})">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        notificationsList.innerHTML = html;
    }

    /**
     * Obtener icono según tipo de notificación
     */
    function getNotificationIcon(type) {
        const icons = {
            'achievement': 'fas fa-trophy',
            'lesson': 'fas fa-book',
            'system': 'fas fa-cog',
            'reminder': 'fas fa-clock',
            'level_up': 'fas fa-arrow-up',
            'reward': 'fas fa-gift',
            'engagement': 'fas fa-heart',
            'default': 'fas fa-bell'
        };
        
        return icons[type] || icons['default'];
    }

    /**
     * Formatear tiempo relativo
     */
    function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000); // segundos

        if (diff < 60) return 'Ahora mismo';
        if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} horas`;
        if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} días`;
        
        return date.toLocaleDateString('es-ES', { 
            day: 'numeric', 
            month: 'short' 
        });
    }

    /**
     * Actualizar badge del contador
     */
    function updateBadge(count) {
        const badge = notificationBell.querySelector('.notification-badge');
        
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
            } else {
                // Crear badge si no existe
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge';
                newBadge.textContent = count > 99 ? '99+' : count;
                notificationBell.appendChild(newBadge);
            }
        } else {
            if (badge) {
                badge.remove();
            }
        }
        
        // Actualizar también en el footer
        const footerBadge = notificationDropdown.querySelector('.btn-view-all .badge');
        if (footerBadge) {
            const totalCount = document.querySelectorAll('.notification-item').length;
            footerBadge.textContent = totalCount;
        }
    }

    /**
     * Manejar click en notificación
     */
    window.handleNotificationClick = function(notificationId, actionUrl) {
        // Marcar como leída
        markAsRead(notificationId, false);
        
        // Navegar si tiene URL
        if (actionUrl && actionUrl !== '' && actionUrl !== 'null') {
            setTimeout(() => {
                window.location.href = actionUrl;
            }, 200);
        }
    };

    /**
     * Marcar notificación como leída
     */
    window.markAsRead = function(notificationId, showToast = true) {
        fetch(`/notifications/${notificationId}/mark-read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar visualmente
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    const btn = item.querySelector('.btn-mark-read');
                    if (btn) btn.remove();
                }
                
                // Actualizar badge
                updateBadge(data.unread_count);
                
                if (showToast) {
                    showSuccessToast('Marcada como leída');
                }
            }
        })
        .catch(error => {
            console.error('Error marking as read:', error);
            if (showToast) {
                showErrorToast('Error al marcar como leída');
            }
        });
    };

    /**
     * Eliminar notificación
     */
    window.deleteNotification = function(notificationId) {
        if (!confirm('¿Eliminar esta notificación?')) return;
        
        fetch(`/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animar y remover
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        item.remove();
                        
                        // Recargar si no quedan notificaciones
                        const remaining = document.querySelectorAll('.notification-item').length;
                        if (remaining === 0) {
                            loadNotifications();
                        }
                    }, 300);
                }
                
                // Actualizar badge
                updateBadge(data.unread_count);
                
                showSuccessToast('Notificación eliminada');
            }
        })
        .catch(error => {
            console.error('Error deleting notification:', error);
            showErrorToast('Error al eliminar');
        });
    };

    /**
     * Marcar todas como leídas
     */
    window.markAllNotificationsAsRead = function() {
        fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recargar notificaciones
                loadNotifications();
                showSuccessToast('Todas marcadas como leídas');
            }
        })
        .catch(error => {
            console.error('Error marking all as read:', error);
            showErrorToast('Error al marcar todas como leídas');
        });
    };

    /**
     * Mostrar toast de éxito
     */
    function showSuccessToast(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        }
    }

    /**
     * Mostrar toast de error
     */
    function showErrorToast(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }

    /**
     * Atajo de teclado: N para abrir notificaciones
     */
    document.addEventListener('keydown', function(e) {
        // Alt+N para toggle notificaciones
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            toggleDropdown();
        }
    });

    /**
     * Limpiar interval al salir
     */
    window.addEventListener('beforeunload', function() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });

    // Log para debugging
    console.log('✅ Sistema de notificaciones mejorado cargado correctamente');
})();
