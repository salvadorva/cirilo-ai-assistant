<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PWACommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pwa:install {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install or update PWA files and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Installing PWA files...');

        // Verificar archivos PWA
        $this->checkPWAFiles();

        // Generar iconos si no existen
        $this->generateIcons();

        // Verificar configuración
        $this->checkConfiguration();

        // Mostrar estado
        $this->showStatus();

        $this->info('✅ PWA installation completed!');
        $this->info('🌐 Your application is now a Progressive Web App!');

        return 0;
    }

    /**
     * Verificar archivos PWA esenciales
     */
    private function checkPWAFiles()
    {
        $files = [
            'public/sw.js' => 'Service Worker',
            'public/manifest.json' => 'Web App Manifest',
            'resources/views/offline.blade.php' => 'Offline Page',
            'app/Http/Controllers/PWAController.php' => 'PWA Controller',
            'config/pwa.php' => 'PWA Configuration',
        ];

        $this->info('📋 Checking PWA files...');

        foreach ($files as $file => $description) {
            if (File::exists(base_path($file))) {
                $this->line("✅ {$description}: {$file}");
            } else {
                $this->error("❌ Missing {$description}: {$file}");
            }
        }
    }

    /**
     * Generar iconos PWA
     */
    private function generateIcons()
    {
        $this->info('🎨 Checking PWA icons...');

        $iconDir = public_path('icons');
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

        if (! File::exists($iconDir)) {
            File::makeDirectory($iconDir, 0755, true);
            $this->info('📁 Created icons directory');
        }

        $missingIcons = [];
        foreach ($sizes as $size) {
            $iconPath = "{$iconDir}/icon-{$size}x{$size}.png";
            if (! File::exists($iconPath)) {
                $missingIcons[] = $size;
            }
        }

        if (! empty($missingIcons)) {
            $this->warn('⚠️  Missing icons for sizes: '.implode(', ', $missingIcons));
            $this->info('💡 Run: ./generate-icons.sh or visit https://realfavicongenerator.net/');
        } else {
            $this->info('✅ All PWA icons are present');
        }
    }

    /**
     * Verificar configuración PWA
     */
    private function checkConfiguration()
    {
        $this->info('⚙️  Checking PWA configuration...');

        // Verificar HTTPS
        if (config('app.env') === 'production' && ! request()->isSecure()) {
            $this->warn('⚠️  HTTPS is required for PWA in production');
        }

        // Verificar configuración PWA
        $pwaConfig = config('pwa');
        if (empty($pwaConfig)) {
            $this->error('❌ PWA configuration not found');
        } else {
            $this->info('✅ PWA configuration loaded');
        }

        // Verificar rutas PWA
        $routes = [
            '/manifest.json',
            '/sw.js',
            '/offline',
            '/pwa/install',
        ];

        foreach ($routes as $route) {
            try {
                $response = $this->call('route:list', ['--path' => $route]);
                $this->info("✅ Route registered: {$route}");
            } catch (\Exception $e) {
                $this->warn("⚠️  Route may not be registered: {$route}");
            }
        }
    }

    /**
     * Mostrar estado de la PWA
     */
    private function showStatus()
    {
        $this->info('📊 PWA Status:');

        $checks = [
            'Service Worker' => File::exists(public_path('sw.js')),
            'Web App Manifest' => File::exists(public_path('manifest.json')),
            'PWA Controller' => File::exists(app_path('Http/Controllers/PWAController.php')),
            'Offline Page' => File::exists(resource_path('views/offline.blade.php')),
            'PWA Configuration' => File::exists(config_path('pwa.php')),
            'Icons Directory' => File::exists(public_path('icons')),
            'Basic Icons' => File::exists(public_path('icons/icon-192x192.png')),
        ];

        foreach ($checks as $check => $status) {
            $icon = $status ? '✅' : '❌';
            $this->line("{$icon} {$check}");
        }

        $this->newLine();

        // Mostrar próximos pasos
        $this->info('🔄 Next steps:');
        $this->line('1. Ensure HTTPS is enabled in production');
        $this->line('2. Test PWA installation in Chrome/Edge');
        $this->line('3. Generate high-quality icons if needed');
        $this->line('4. Test offline functionality');
        $this->line('5. Configure push notifications (optional)');

        $this->newLine();
        $this->info('📖 Documentation: PWA_README.md');
        $this->info('🔧 Test installation: /pwa/install');
    }
}
