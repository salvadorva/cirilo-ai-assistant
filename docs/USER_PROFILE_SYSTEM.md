# Sistema de Perfil y Configuración de Usuario

## Descripción General

El sistema de perfil y configuración permite a los usuarios gestionar su información personal, preferencias y configuración de privacidad desde una interfaz centralizada.

---

## Rutas Disponibles

### Perfil de Usuario
- **GET** `/profile` - Ver perfil del usuario
- **POST** `/profile/update` - Actualizar información personal
- **POST** `/profile/avatar` - Actualizar foto de perfil

### Configuración
- **GET** `/settings` - Ver configuración del usuario
- **POST** `/settings/update` - Actualizar preferencias generales
- **POST** `/settings/password` - Cambiar contraseña

---

## Campos de Usuario

### Base de Datos (tabla `users`)

| Campo | Tipo | Descripción | Nullable | Default |
|-------|------|-------------|----------|---------|
| `name` | string(255) | Nombre completo | No | - |
| `email` | string(255) | Correo electrónico | No | - |
| `age` | integer | Edad del usuario | Sí | null |
| `email_notifications_enabled` | boolean | Habilitar notificaciones por email | No | true |
| `bio` | text | Biografía del usuario | Sí | null |
| `avatar` | string(255) | Ruta al avatar del usuario | Sí | null |

---

## Funcionalidades

### 1. Perfil de Usuario (`/profile`)

**Información visible:**
- Avatar (con opción de cambiar)
- Nombre completo
- Email
- Edad (personaliza dificultad de ejercicios)
- Biografía
- Nivel y XP total
- Racha actual y mejor racha
- Estadísticas de juego
- Logros desbloqueados

**Validaciones:**
- Nombre: requerido, máx. 255 caracteres
- Email: requerido, único, formato válido
- Edad: opcional, entre 5 y 120 años
- Biografía: opcional, máx. 500 caracteres
- Avatar: imagen (jpg, png, gif), máx. 2MB

### 2. Configuración (`/settings`)

**Secciones disponibles:**

#### General
- Idioma (Español/English)
- Tema (Claro/Oscuro/Automático)

#### Notificaciones
- **Notificaciones por Email**: activar/desactivar (guardado en BD)
- Efectos de Sonido: on/off (guardado en sesión)
- Música de Fondo: on/off (guardado en sesión)

#### Seguridad
- Cambiar contraseña
- Verificación de contraseña actual
- Confirmación de nueva contraseña

#### Preferencias
- Enlaces rápidos a configuración de juegos

---

## Uso del Sistema de Notificaciones por Email

### Verificar si un usuario acepta emails

```php
use App\Services\NotificationService;

$user = Auth::user();

if (NotificationService::canSendEmail($user)) {
    // Enviar email
    Mail::to($user->email)->send(new WelcomeMail());
}
```

### Integración en servicios

```php
// En cualquier servicio que envíe emails
public function sendNotification(User $user, $message)
{
    // Crear notificación en sistema
    NotificationService::achievementUnlocked($user, 'Nuevo logro');
    
    // Enviar email solo si está habilitado
    NotificationService::sendEmailIfEnabled($user, 'Nuevo logro', $message);
}
```

---

## Migración y Despliegue

### Migraciones creadas:
1. `2025_09_05_084145_add_age_to_users_table.php` - Campo de edad
2. `2025_10_15_131954_add_email_notifications_to_users_table.php` - Toggle de emails
3. `2025_10_15_132431_add_bio_avatar_to_users_table.php` - Biografía y avatar

### Comandos necesarios:
```bash
# Ejecutar migraciones
php artisan migrate

# Crear enlace simbólico de storage (si no existe)
php artisan storage:link

# Crear directorio de avatars
mkdir -p storage/app/public/avatars
chmod 775 storage/app/public/avatars
```

---

## Estructura de Archivos

```
app/
├── Http/
│   └── Controllers/
│       ├── UserProfileController.php
│       └── UserSettingsController.php
├── Models/
│   └── User.php (actualizado con campos fillable)
└── Services/
    └── NotificationService.php (métodos para emails)

resources/
└── views/
    ├── profile/
    │   └── show.blade.php
    └── settings/
        └── show.blade.php

storage/
└── app/
    └── public/
        └── avatars/ (almacena imágenes de usuarios)
```

---

## Personalización por Edad

El campo `age` se utiliza para:
- Ajustar la dificultad de ejercicios
- Personalizar el contenido educativo
- Adaptar el nivel de los juegos

**Ejemplo de uso:**
```php
$user = Auth::user();
$difficulty = match (true) {
    $user->age < 12 => 'easy',
    $user->age >= 12 && $user->age < 18 => 'medium',
    default => 'hard'
};
```

---

## Mejoras Futuras

- [ ] Integración completa con sistema de emails (Laravel Mail)
- [ ] Avatar por defecto basado en iniciales
- [ ] Más opciones de personalización visual
- [ ] Integración con redes sociales
- [ ] Exportar datos del usuario (GDPR)
- [ ] Two-Factor Authentication (2FA)

---

## Soporte

Para reportar problemas o solicitar funcionalidades, contacta al equipo de desarrollo.
