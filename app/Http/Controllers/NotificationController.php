<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Obtener todas las notificaciones del usuario autenticado
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->notifications()->active();

        // Filtro por estado de lectura (default: no leídas)
        $status = $request->get('status', 'unread');

        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->where('is_read', true);
        }
        // Si es 'all', no aplicamos filtro

        $notifications = $query
            ->orderBy('is_important', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->items(),
                'unread_count' => $user->getUnreadNotificationsCount(),
                'has_more' => $notifications->hasMorePages(),
            ]);
        }

        return view('notifications.index', compact('notifications', 'status'));
    }

    /**
     * Obtener notificaciones no leídas para la campanita
     */
    public function getUnread()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->unread()
            ->active()
            ->orderBy('is_important', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->notifications()
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas',
            'unread_count' => 0,
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada',
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Limpiar notificaciones antiguas
     */
    public function cleanup()
    {
        $user = Auth::user();

        // Eliminar notificaciones leídas de más de 30 días
        $deleted = $user->notifications()
            ->where('is_read', true)
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Se eliminaron {$deleted} notificaciones antiguas",
        ]);
    }

    /**
     * Crear notificación de prueba (solo para desarrollo)
     */
    public function createTest()
    {
        if (! app()->environment('local')) {
            abort(403, 'Solo disponible en entorno de desarrollo');
        }

        $user = Auth::user();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => '🧪 Notificación de Prueba',
            'message' => 'Esta es una notificación de prueba para verificar el sistema',
            'data' => ['test' => true],
            'icon' => 'fas fa-flask',
            'color' => '#17a2b8',
            'action_url' => '/notifications',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notificación de prueba creada',
            'notification' => $notification,
        ]);
    }

    /**
     * Obtener estadísticas de notificaciones para el admin
     */
    public function stats()
    {
        // Solo accesible para admins
        if (! Auth::user()->role || Auth::user()->role->name !== 'admin') {
            abort(403, 'Acceso denegado');
        }

        $stats = [
            'total_notifications' => Notification::count(),
            'unread_notifications' => Notification::unread()->count(),
            'notifications_by_type' => Notification::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get(),
            'notifications_last_week' => Notification::where('created_at', '>=', now()->subWeek())
                ->count(),
            'most_active_users' => Notification::selectRaw('user_id, COUNT(*) as notification_count')
                ->groupBy('user_id')
                ->orderBy('notification_count', 'desc')
                ->limit(10)
                ->with('user:id,name')
                ->get(),
        ];

        return response()->json($stats);
    }
}
