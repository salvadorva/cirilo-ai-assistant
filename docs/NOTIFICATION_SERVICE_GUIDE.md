# 🔔 Guía de Uso del NotificationService

## Descripción
El `NotificationService` es un servicio centralizado para crear y gestionar notificaciones en el sistema. Proporciona métodos para los tipos más comunes de notificaciones y mantiene consistencia en el diseño.

---

## 📦 Uso Básico

### Importar el servicio
```php
use App\Services\NotificationService;
```

---

## 🎯 Métodos Disponibles

### 1. **Logro Desbloqueado**
```php
NotificationService::achievementUnlocked(
    $user,
    'Primera Lección Completada',
    500  // XP ganado (opcional)
);
```

**Cuándo usar:** Cuando el usuario desbloquea un logro o insignia.

---

### 2. **Subida de Nivel**
```php
NotificationService::levelUp(
    $user,
    5,   // Nivel anterior
    6    // Nivel nuevo
);
```

**Cuándo usar:** Automáticamente se llama desde `UserGameProgress::addXP()`.

---

### 3. **Lección Completada**
```php
NotificationService::lessonCompleted(
    $user,
    'Fila Central - ASDF JKLÑ',
    1  // ID de lección (opcional)
);
```

**Cuándo usar:** Al finalizar una lección exitosamente.

**Ejemplo en controlador:**
```php
public function completeLesson(Request $request, $lessonId)
{
    $lesson = TypingLesson::findOrFail($lessonId);
    
    // Marcar lección como completada...
    
    // Enviar notificación
    NotificationService::lessonCompleted(
        auth()->user(),
        $lesson->title,
        $lesson->id
    );
    
    return redirect()->back()->with('success', '¡Lección completada!');
}
```

---

### 4. **Nueva Lección Disponible**
```php
NotificationService::newLessonAvailable(
    $user,
    'Signos de puntuación',
    11  // ID de lección
);
```

**Cuándo usar:** Cuando se desbloquea una nueva lección.

---

### 5. **Recordatorio de Inactividad**
```php
NotificationService::inactivityReminder(
    $user,
    3  // Días inactivo
);
```

**Cuándo usar:** En comandos programados (cron) para re-engagement.

**Ejemplo en comando:**
```php
public function handle()
{
    $inactiveUsers = User::whereHas('gameProgress', function($q) {
        $q->where('days_inactive', '>=', 3);
    })->get();
    
    foreach ($inactiveUsers as $user) {
        NotificationService::inactivityReminder(
            $user,
            $user->gameProgress->days_inactive
        );
    }
}
```

---

### 6. **Hito de Racha**
```php
NotificationService::streakMilestone(
    $user,
    7  // Días consecutivos
);
```

**Cuándo usar:** Automáticamente se llama desde `UserGameProgress::updateActivity()` en hitos (7, 14, 30, 50, 100, 365 días).

---

### 7. **Recompensa Ganada**
```php
NotificationService::rewardEarned(
    $user,
    'xp_bonus',
    200,  // Cantidad
    'completar 5 lecciones'  // Razón
);
```

**Cuándo usar:** Al otorgar bonos o recompensas especiales.

---

### 8. **Récord Personal Batido**
```php
NotificationService::personalRecord(
    $user,
    'wpm',  // Métrica: wpm, accuracy, score
    45,     // Valor anterior
    52      // Valor nuevo
);
```

**Cuándo usar:** Cuando el usuario supera su mejor marca.

**Ejemplo:**
```php
$oldRecord = $user->gameProgress->best_wpm;
$newWpm = 52;

if ($newWpm > $oldRecord) {
    $user->gameProgress->update(['best_wpm' => $newWpm]);
    
    NotificationService::personalRecord(
        $user,
        'wpm',
        $oldRecord,
        $newWpm
    );
}
```

---

### 9. **Actualización del Sistema**
```php
NotificationService::systemUpdate(
    $user,
    'Nueva funcionalidad disponible',
    'Hemos agregado el Modo Zen para practicar sin presión.',
    ['Modo Zen', 'Audio ambiental', 'Textos inspiracionales']
);
```

**Cuándo usar:** Para anunciar nuevas features o actualizaciones.

---

### 10. **Notificación Personalizada**
```php
NotificationService::custom($user, [
    'type' => 'special',
    'title' => '🎂 ¡Feliz cumpleaños!',
    'message' => 'Te deseamos un día increíble. Aquí tienes 1000 XP de regalo.',
    'icon' => 'fas fa-birthday-cake',
    'color' => '#ff6b6b',
    'is_important' => true,
    'action_url' => '/typing',
    'data' => json_encode(['gift_xp' => 1000])
]);
```

**Cuándo usar:** Para casos especiales no cubiertos por otros métodos.

---

### 11. **Broadcast (Notificar a Todos)**
```php
$notificationsCreated = NotificationService::broadcast(
    '🎉 ¡Nueva actualización!',
    'Hemos agregado 10 nuevas lecciones y 3 modos de juego.',
    'system',
    '/typing'
);

echo "Se crearon {$notificationsCreated} notificaciones";
```

**Cuándo usar:** Anuncios importantes para todos los usuarios.

---

## 🎨 Tipos de Notificaciones

| Tipo | Color | Icono Predeterminado | Uso |
|------|-------|----------------------|-----|
| `achievement` | Morado/Rosa | `fas fa-trophy` | Logros y hitos |
| `lesson` | Azul | `fas fa-book` | Lecciones |
| `system` | Verde | `fas fa-cog` | Actualizaciones |
| `reminder` | Rosa | `fas fa-clock` | Recordatorios |
| `reward` | Naranja | `fas fa-gift` | Recompensas |

---

## 🔧 Mantenimiento

### Limpiar Notificaciones Antiguas

```php
// Eliminar notificaciones leídas de más de 30 días
$deleted = NotificationService::cleanupOld(30);
echo "Se eliminaron {$deleted} notificaciones antiguas";
```

**Agregar a un comando programado:**
```php
// app/Console/Commands/CleanupNotifications.php
public function handle()
{
    $deleted = NotificationService::cleanupOld(30);
    $this->info("Eliminadas {$deleted} notificaciones antiguas");
}
```

**Programar en `routes/console.php`:**
```php
Schedule::command('notifications:cleanup')->weekly();
```

---

## 📊 Ejemplos de Integración

### En un Controlador de Juego
```php
public function saveGameSession(Request $request)
{
    $user = auth()->user();
    $score = $request->input('score');
    $wpm = $request->input('wpm');
    
    // Guardar sesión...
    
    // Verificar récord de WPM
    if ($wpm > $user->gameProgress->best_wpm) {
        NotificationService::personalRecord(
            $user,
            'wpm',
            $user->gameProgress->best_wpm,
            $wpm
        );
    }
    
    // Agregar XP (esto automáticamente crea notificación si sube de nivel)
    $leveledUp = $user->gameProgress->addXP($score);
    
    return response()->json([
        'success' => true,
        'leveled_up' => $leveledUp
    ]);
}
```

### En un Observer de Logros
```php
// app/Observers/AchievementObserver.php
public function created(Achievement $achievement)
{
    if ($achievement->user) {
        NotificationService::achievementUnlocked(
            $achievement->user,
            $achievement->name,
            $achievement->xp_reward
        );
    }
}
```

### En un Comando Programado
```php
// app/Console/Commands/SendDailyReminders.php
public function handle()
{
    $users = User::whereDoesntHave('gameProgress', function($q) {
        $q->where('last_activity_date', today());
    })->get();
    
    foreach ($users as $user) {
        NotificationService::inactivityReminder($user, 1);
    }
    
    $this->info("Enviados {$users->count()} recordatorios");
}
```

---

## 🧪 Testing

### Crear Notificaciones de Prueba
```bash
php create_test_notifications.php
```

### Verificar en la Interfaz
- **Dropdown**: Campanita en el header
- **Vista completa**: `/notifications`
- **API**: `/notifications/unread`

---

## 📝 Notas Importantes

1. **Auto-llamadas:** `levelUp()` y `streakMilestone()` se llaman automáticamente desde el modelo. No necesitas llamarlos manualmente.

2. **Usuario requerido:** Todos los métodos (excepto `broadcast`) requieren un objeto `User`.

3. **Data personalizada:** El campo `data` se guarda como JSON y puede contener cualquier información adicional.

4. **Importancia:** Usa `is_important: true` para notificaciones críticas (aparecen destacadas).

5. **Action URL:** Siempre proporciona una URL para que el usuario pueda tomar acción.

---

## 🚀 Próximas Mejoras

- [ ] Notificaciones por email (ya existe InactivityReminderMail)
- [ ] Push notifications para PWA
- [ ] Preferencias de usuario (qué notificaciones recibir)
- [ ] Agrupación de notificaciones similares
- [ ] Notificaciones in-app con toast
- [ ] Template system para notificaciones personalizadas

---

**Última actualización:** 2 de octubre de 2025  
**Sprint:** 9 - Sistema de Notificaciones Mejorado
