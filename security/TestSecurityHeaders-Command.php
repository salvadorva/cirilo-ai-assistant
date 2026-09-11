<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSecurityHeaders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:test-headers {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test security headers on the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url') ?? config('app.url');

        $this->info("Testing security headers for: {$url}");
        $this->newLine();

        try {
            $response = Http::get($url);
            $headers = $response->headers();

            $securityHeaders = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => ['DENY', 'SAMEORIGIN'],
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Strict-Transport-Security' => null, // Variable content
                'Content-Security-Policy' => null, // Variable content
                'Permissions-Policy' => null, // Variable content
                'Cross-Origin-Embedder-Policy' => 'require-corp',
                'Cross-Origin-Opener-Policy' => 'same-origin',
                'Cross-Origin-Resource-Policy' => 'same-origin',
            ];

            $this->table(
                ['Header', 'Status', 'Value'],
                $this->checkHeaders($headers, $securityHeaders)
            );

            $this->newLine();
            $this->checkRemovedHeaders($headers);

        } catch (\Exception $e) {
            $this->error('Error testing headers: '.$e->getMessage());
        }
    }

    private function checkHeaders($headers, $securityHeaders)
    {
        $results = [];

        foreach ($securityHeaders as $header => $expectedValue) {
            $headerValue = $headers[$header][0] ?? null;

            if ($headerValue) {
                if ($expectedValue === null ||
                    $headerValue === $expectedValue ||
                    (is_array($expectedValue) && in_array($headerValue, $expectedValue))) {
                    $status = '<fg=green>✓ Present</>';
                } else {
                    $status = '<fg=yellow>⚠ Different</>';
                }
            } else {
                $status = '<fg=red>✗ Missing</>';
                $headerValue = 'Not set';
            }

            $results[] = [
                $header,
                $status,
                $headerValue ?: 'Not set',
            ];
        }

        return $results;
    }

    private function checkRemovedHeaders($headers)
    {
        $sensitiveHeaders = ['Server', 'X-Powered-By'];
        $found = [];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $found[] = $header.': '.$headers[$header][0];
            }
        }

        if (! empty($found)) {
            $this->warn('Sensitive headers still present:');
            foreach ($found as $header) {
                $this->line("  - {$header}");
            }
        } else {
            $this->info('✓ Sensitive headers properly removed');
        }
    }
}
