<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSecurityProductionMode extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'security:test-production {url?}';

    /**
     * The console command description.
     */
    protected $description = 'Test security headers in production mode (simulated)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url') ?? config('app.url').'/test-security';

        $this->info("Testing security headers in production mode for: {$url}");
        $this->newLine();

        try {
            // Simular producción temporalmente
            config(['security.development_mode' => false]);

            $response = Http::get($url);
            $headers = $response->headers();

            $this->info('🔍 Security Headers Analysis:');
            $this->newLine();

            // Verificar headers críticos
            $criticalHeaders = [
                'Content-Security-Policy' => 'Blocks XSS and injection attacks',
                'Strict-Transport-Security' => 'Forces HTTPS connections',
                'X-Frame-Options' => 'Prevents clickjacking',
                'X-Content-Type-Options' => 'Prevents MIME sniffing',
                'X-XSS-Protection' => 'Enables browser XSS protection',
            ];

            $this->table(
                ['Header', 'Status', 'Description'],
                $this->analyzeHeaders($headers, $criticalHeaders)
            );

            $this->newLine();

            // Mostrar CSP completo
            $csp = $headers['Content-Security-Policy'][0] ?? $headers['Content-Security-Policy-Report-Only'][0] ?? 'Not found';

            if ($csp !== 'Not found') {
                $this->info('📋 Content Security Policy:');
                $this->line($this->formatCSP($csp));
            }

        } catch (\Exception $e) {
            $this->error('Error testing headers: '.$e->getMessage());
        }
    }

    private function analyzeHeaders($headers, $criticalHeaders)
    {
        $results = [];

        foreach ($criticalHeaders as $header => $description) {
            $value = $headers[$header][0] ?? null;

            if ($value) {
                $status = '<fg=green>✅ Present</>';
                $truncatedValue = strlen($value) > 50 ? substr($value, 0, 50).'...' : $value;
            } else {
                $status = '<fg=red>❌ Missing</>';
                $truncatedValue = 'Not set';
            }

            $results[] = [
                $header,
                $status,
                $description,
            ];
        }

        return $results;
    }

    private function formatCSP($csp)
    {
        $directives = explode('; ', $csp);
        $formatted = [];

        foreach ($directives as $directive) {
            $formatted[] = '  • '.$directive;
        }

        return implode("\n", $formatted);
    }
}
