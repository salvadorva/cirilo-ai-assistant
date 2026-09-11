<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PWAController extends Controller
{
    /**
     * Mostrar la página offline
     */
    public function offline()
    {
        return view('offline');
    }

    /**
     * Servir el manifest.json
     */
    public function manifest()
    {
        $manifest = [
            // Identidad estable de la PWA (recomendado 2024+)
            'id' => config('pwa.start_url', '/'),
            'name' => config('pwa.name', config('app.name', 'Asistente Personal IA')),
            'short_name' => config('pwa.short_name', 'Asistente IA'),
            'description' => config('pwa.description', 'Tu asistente personal con IA para gestión de agenda, cursos de inglés y más'),
            'start_url' => config('pwa.start_url', '/'),
            // display_override permite window-controls-overlay en desktop (barra de título integrada)
            'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui', 'browser'],
            'display' => config('pwa.display', 'standalone'),
            'background_color' => config('pwa.background_color', '#232946'),
            'theme_color' => config('pwa.theme_color', '#232946'),
            'orientation' => config('pwa.orientation', 'portrait-primary'),
            'scope' => config('pwa.scope', '/'),
            'lang' => config('pwa.lang', 'es'),
            'dir' => config('pwa.dir', 'ltr'),
            'categories' => config('pwa.categories', ['productivity', 'education', 'lifestyle']),
            'icons' => config('pwa.icons', []),
            'shortcuts' => config('pwa.shortcuts', []),
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Servir el service worker con la versión del caché inyectada.
     * El CACHE_NAME incluye el hash del commit actual para invalidar
     * automáticamente el caché en cada deploy.
     */
    public function serviceWorker()
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Obtener hash corto del commit actual para versionado automático
        $commitHash = trim(shell_exec('git -C '.base_path().' rev-parse --short HEAD 2>/dev/null') ?? '');
        $version = $commitHash ?: config('app.version', '1.0.0');
        $sw = str_replace('__CACHE_VERSION__', "asistente-pwa-{$version}", $sw);

        return response($sw)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Instalar PWA (mostrar instrucciones)
     */
    public function install()
    {
        return view('pwa.install');
    }

    /**
     * Verificar si es una instalación PWA
     */
    public function checkInstallation(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        $isPWA = $request->header('X-Requested-With') === 'PWA' ||
                 strpos($userAgent, 'wv') !== false ||
                 $request->query('source') === 'pwa';

        return response()->json([
            'is_pwa' => $isPWA,
            'user_agent' => $userAgent,
            'display_mode' => $isPWA ? 'standalone' : 'browser',
        ]);
    }

    /**
     * Manejar notificaciones push
     */
    public function sendNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'url' => 'nullable|url',
        ]);

        // Aquí puedes implementar la lógica para enviar notificaciones push
        // Por ejemplo, usando Firebase Cloud Messaging o Web Push Protocol

        return response()->json([
            'success' => true,
            'message' => 'Notificación enviada correctamente',
        ]);
    }

    public function icon($filename)
    {
        $iconPath = public_path('icons/'.$filename);

        if (! file_exists($iconPath)) {
            abort(404, 'Icon not found');
        }

        $mimeType = 'image/png';
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'svg') {
            $mimeType = 'image/svg+xml';
        }

        return response()->file($iconPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function screenshot($filename)
    {
        $screenshotPath = public_path('screenshots/'.$filename);

        if (! file_exists($screenshotPath)) {
            abort(404, 'Screenshot not found');
        }

        return response()->file($screenshotPath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
