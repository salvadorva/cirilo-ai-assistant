#!/bin/bash

# 🔒 SCRIPT DE INSTALACIÓN DE SECURITY HEADERS
# Para Laravel 11 - Automatiza la implementación completa

echo "🔒 Iniciando instalación de Security Headers..."

# 1. Crear middleware
echo "📝 Creando middleware SecurityHeaders..."
php artisan make:middleware SecurityHeaders

# 2. Crear comandos de testing
echo "🧪 Creando comandos de testing..."
php artisan make:command TestSecurityHeaders --command=security:test-headers
php artisan make:command TestSecurityProductionMode --command=security:test-production

# 3. Crear archivos de configuración
echo "⚙️ Creando archivos de configuración..."

# Crear config/security.php
cat > config/security.php << 'EOF'
<?php
return [
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'DENY',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'hsts' => [
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true,
        ],
        'csp' => [
            'default_src' => ["'self'"],
            'script_src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
            'style_src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
            'font_src' => ["'self'", 'https://fonts.gstatic.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'data:'],
            'img_src' => ["'self'", 'data:', 'blob:', 'https:', 'http:'],
            'connect_src' => ["'self'", 'https://api.github.com', 'wss:', 'ws:'],
            'frame_src' => ["'self'"],
            'object_src' => ["'none'"],
            'base_uri' => ["'self'"],
            'form_action' => ["'self'"],
            'upgrade_insecure_requests' => true,
        ],
        'permissions_policy' => [
            'camera' => [],
            'microphone' => [],
            'geolocation' => [],
            'interest-cohort' => [],
        ],
        'cross_origin_embedder_policy' => 'require-corp',
        'cross_origin_opener_policy' => 'same-origin',
        'cross_origin_resource_policy' => 'same-origin',
    ],
    'remove_headers' => ['Server', 'X-Powered-By'],
    'development_mode' => env('APP_DEBUG', false),
];
EOF

# Crear config/cors.php
cat > config/cors.php << 'EOF'
<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
EOF

echo "✅ Archivos base creados."
echo ""
echo "🔧 PASOS MANUALES REQUERIDOS:"
echo "1. Copiar código del middleware desde SecurityHeaders-Middleware-Code.php"
echo "2. Copiar código de comandos de testing"
echo "3. Actualizar bootstrap/app.php para registrar middleware"
echo "4. Actualizar dominios en config/security.php y config/cors.php"
echo "5. Ejecutar: php artisan config:clear"
echo ""
echo "📚 Ver IMPLEMENTATION-GUIDE.md para instrucciones completas"

# 4. Limpiar cachés
echo "🧹 Limpiando cachés..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "🎉 Instalación base completada!"
echo "📖 Consulta IMPLEMENTATION-GUIDE.md para completar la configuración"
