# 🤖 PROMPT PARA CLAUDE - IMPLEMENTACIÓN DE SECURITY HEADERS

## CONTEXTO
Necesito implementar security headers críticos en un proyecto Laravel 11 para pasar pentests de seguridad. La implementación debe incluir middleware personalizado, configuración CORS y políticas de seguridad de contenido.

## ARCHIVOS DE REFERENCIA
En este repositorio encontrarás:
- `IMPLEMENTATION-GUIDE.md` - Guía técnica completa
- `SecurityHeaders-Middleware-Code.php` - Código del middleware
- `install-security-headers.sh` - Script de instalación base
- `SECURITY-HEADERS.md` - Documentación de headers implementados

## INSTRUCCIONES PARA CLAUDE

### PROMPT SUGERIDO:
```
Hola Claude, necesito implementar security headers críticos en mi proyecto Laravel 11. 

He encontrado una implementación completa en otro proyecto que incluye:

1. **Middleware SecurityHeaders** que aplica headers críticos:
   - Strict-Transport-Security (HSTS)
   - Content-Security-Policy (CSP)
   - X-Frame-Options
   - X-Content-Type-Options
   - X-XSS-Protection
   - Referrer-Policy
   - Permissions-Policy
   - Cross-Origin policies

2. **Configuración CORS** para múltiples dominios

3. **Comandos de testing** para verificar implementación

4. **Archivos de configuración** dinámicos

¿Puedes ayudarme a implementar esto siguiendo estos pasos?:

1. Crear el middleware `SecurityHeaders`
2. Crear configuración en `config/security.php`
3. Crear configuración CORS en `config/cors.php`
4. Registrar middleware en `bootstrap/app.php`
5. Crear comandos de testing
6. Actualizar `.htaccess` como respaldo
7. Verificar que funcione correctamente

Los headers deben:
- Funcionar tanto en desarrollo como producción
- Tener valores por defecto seguros
- Ser configurables desde archivos de config
- Aplicar CSP en modo Report-Only en desarrollo y enforced en producción
- Incluir dominios específicos: [AGREGAR TUS DOMINIOS AQUÍ]

¿Comenzamos con la implementación?
```

### ARCHIVOS QUE CLAUDE DEBE CREAR/MODIFICAR:
1. `app/Http/Middleware/SecurityHeaders.php`
2. `config/security.php`
3. `config/cors.php`
4. `bootstrap/app.php` (modificar)
5. `app/Console/Commands/TestSecurityHeaders.php`
6. `app/Console/Commands/TestSecurityProductionMode.php`
7. `public/.htaccess` (modificar)

### HEADERS CRÍTICOS REQUERIDOS:
- ✅ `Strict-Transport-Security` 
- ✅ `Content-Security-Policy`
- ✅ `X-Frame-Options`
- ✅ `X-Content-Type-Options`
- ✅ `X-XSS-Protection`

### DOMINIOS A CONFIGURAR:
Actualizar estos arrays en la configuración:
```php
// En config/security.php - connect_src
// En config/cors.php - allowed_origins
[
    'http://tu-dominio-local.com',
    'https://tu-dominio-local.com', 
    'https://tu-dominio-produccion.com',
    'https://tu-dominio-secundario.com',
]
```

### VERIFICACIÓN FINAL:
```bash
# Comandos para probar
php artisan security:test-headers http://tu-dominio.com
php artisan security:test-production http://tu-dominio.com

# Verificación manual
curl -I http://tu-dominio.com/test-security
```

### RESULTADO ESPERADO:
- Todos los headers críticos presentes
- CORS funcionando para dominios configurados
- CSP permitiendo recursos necesarios sin comprometer seguridad
- Comando de testing reportando ✅ en todos los headers críticos

## PERSONALIZACIÓN ADICIONAL

### Si tienes recursos específicos:
Agregar a CSP en `config/security.php`:
```php
'script_src' => [
    // ... existing sources
    'https://tu-cdn-especifico.com',
],
'style_src' => [
    // ... existing sources  
    'https://tu-css-cdn.com',
],
```

### Si usas APIs externas:
```php
'connect_src' => [
    // ... existing sources
    'https://api.tu-servicio.com',
],
```

## NOTES IMPORTANTES
- Configurar `APP_DEBUG=false` en producción
- HSTS solo se aplica con HTTPS
- CSP en modo Report-Only en desarrollo
- Backup en .htaccess por compatibilidad
- Testing automatizado incluido
