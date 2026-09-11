<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Obtener configuración de security headers
        $config = config('security.headers', []);
        $isDevelopment = config('security.development_mode', false);

        // X-Content-Type-Options (CRÍTICO - Siempre aplicar)
        $response->headers->set('X-Content-Type-Options',
            $config['x_content_type_options'] ?? 'nosniff'
        );

        // X-Frame-Options (CRÍTICO - Siempre aplicar)
        $response->headers->set('X-Frame-Options',
            $config['x_frame_options'] ?? 'DENY'
        );

        // X-XSS-Protection (CRÍTICO - Siempre aplicar)
        $response->headers->set('X-XSS-Protection',
            $config['x_xss_protection'] ?? '1; mode=block'
        );

        // Referrer-Policy (Recomendado - Siempre aplicar)
        $response->headers->set('Referrer-Policy',
            $config['referrer_policy'] ?? 'strict-origin-when-cross-origin'
        );

        // Strict-Transport-Security (CRÍTICO - Aplicar siempre, incluso sin HTTPS en desarrollo)
        if ($request->isSecure() || ! $isDevelopment) {
            $hsts = $config['hsts'] ?? [
                'max_age' => 31536000,
                'include_subdomains' => true,
                'preload' => true,
            ];

            $hstsValue = 'max-age='.$hsts['max_age'];

            if ($hsts['include_subdomains']) {
                $hstsValue .= '; includeSubDomains';
            }

            if ($hsts['preload']) {
                $hstsValue .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hstsValue);
        }

        // Content-Security-Policy (CRÍTICO - Siempre aplicar)
        $cspConfig = $config['csp'] ?? [
            'default_src' => ["'self'"],
            'script_src' => [
                "'self'",
                "'unsafe-inline'",
                "'unsafe-eval'",
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://code.jquery.com',
                'https://unpkg.com',
                'https://www.google.com',
                'https://www.gstatic.com',
            ],
            'style_src' => [
                "'self'",
                "'unsafe-inline'",
                'https://fonts.googleapis.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://unpkg.com',
            ],
            'font_src' => [
                "'self'",
                'https://fonts.gstatic.com',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'data:',
            ],
            'img_src' => [
                "'self'",
                'data:',
                'blob:',
                'https:',
                'http:',
            ],
            'connect_src' => [
                "'self'",
                'http://backups.its',
                'https://backups.its',
                'https://backups.example.com',
                'https://app.example.com',
                'https://api.github.com',
                'wss:',
                'ws:',
            ],
            'frame_src' => [
                "'self'",
                'https://www.google.com',
                'https://www.youtube.com',
            ],
            'object_src' => ["'none'"],
            'base_uri' => ["'self'"],
            'form_action' => ["'self'"],
            'upgrade_insecure_requests' => true,
        ];

        $csp = $this->buildCspHeader($cspConfig);
        if ($isDevelopment) {
            // En desarrollo, usar Report-Only para no bloquear contenido
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        } else {
            // En producción, aplicar la política completamente
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Permissions-Policy (Recomendado - Siempre aplicar)
        $permissionsPolicyConfig = $config['permissions_policy'] ?? [
            'camera' => [],
            'microphone' => [],
            'geolocation' => [],
            'interest-cohort' => [],
        ];
        $permissionsPolicy = $this->buildPermissionsPolicyHeader($permissionsPolicyConfig);
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        // Cross-Origin policies (Recomendados - Siempre aplicar)
        $response->headers->set('Cross-Origin-Embedder-Policy',
            $config['cross_origin_embedder_policy'] ?? 'require-corp'
        );

        $response->headers->set('Cross-Origin-Opener-Policy',
            $config['cross_origin_opener_policy'] ?? 'same-origin'
        );

        $response->headers->set('Cross-Origin-Resource-Policy',
            $config['cross_origin_resource_policy'] ?? 'same-origin'
        );

        // Remover headers sensibles
        $removeHeaders = config('security.remove_headers', []);
        foreach ($removeHeaders as $header) {
            $response->headers->remove($header);
        }

        return $response;
    }

    /**
     * Build Content-Security-Policy header
     */
    private function buildCspHeader(array $csp): string
    {
        $policies = [];

        foreach ($csp as $directive => $sources) {
            if ($directive === 'upgrade_insecure_requests' && $sources) {
                $policies[] = 'upgrade-insecure-requests';

                continue;
            }

            if (is_array($sources) && ! empty($sources)) {
                $directive = str_replace('_', '-', $directive);
                $policies[] = $directive.' '.implode(' ', $sources);
            }
        }

        return implode('; ', $policies);
    }

    /**
     * Build Permissions-Policy header
     */
    private function buildPermissionsPolicyHeader(array $permissions): string
    {
        $policies = [];

        foreach ($permissions as $feature => $allowlist) {
            $feature = str_replace('_', '-', $feature);

            if (empty($allowlist)) {
                $policies[] = $feature.'=()';
            } else {
                $allowedOrigins = implode(' ', array_map(function ($origin) {
                    return '"'.$origin.'"';
                }, $allowlist));
                $policies[] = $feature.'=('.$allowedOrigins.')';
            }
        }

        return implode(', ', $policies);
    }
}
