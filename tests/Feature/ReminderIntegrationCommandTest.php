<?php

namespace Tests\Feature;

use App\Models\ReminderIntegration;
use Illuminate\Support\Facades\Artisan;
use Tests\SecurityTestCase;

/** RC4: provisión de la credencial Hermes sin mostrarla (probada solo con SQLite en memoria). */
class ReminderIntegrationCommandTest extends SecurityTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_09_23_100000_create_contextual_reminders_tables.php'], '--force' => true]);
        $this->dir = sys_get_temp_dir().'/rc4-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_issue_writes_token_only_to_new_0600_file_and_never_to_output(): void
    {
        $user = $this->user();
        $path = $this->dir.'/hermes.env';
        $this->assertSame(0, Artisan::call('reminders:integration', ['action' => 'issue', '--user' => $user->id, '--output' => $path, '--expires-days' => 30]));
        $output = Artisan::output();

        $line = trim(file_get_contents($path));
        $this->assertMatchesRegularExpression('/^CIRILO_HERMES_REMINDERS_TOKEN=cirilo_hrm_[0-9a-f]{64}$/', $line);
        $token = substr($line, strlen('CIRILO_HERMES_REMINDERS_TOKEN='));
        $this->assertSame('600', decoct(fileperms($path) & 0777));
        $this->assertStringNotContainsString($token, $output);
        $integration = ReminderIntegration::sole();
        $this->assertSame($integration->id, ReminderIntegration::findActiveByToken($token)?->id);
        $this->assertTrue($integration->expires_at->isFuture());
        $this->assertSame(ReminderIntegration::SCOPES, $integration->scopes);

        // No sobrescribe ni escribe en stdout.
        $this->assertSame(1, Artisan::call('reminders:integration', ['action' => 'issue', '--user' => $user->id, '--output' => $path]));
        $this->assertSame(1, Artisan::call('reminders:integration', ['action' => 'issue', '--user' => $user->id, '--output' => '-']));
        $this->assertSame(1, Artisan::call('reminders:integration', ['action' => 'issue', '--user' => 999, '--output' => $this->dir.'/x.env']));
        $this->assertSame(1, ReminderIntegration::count());
    }

    public function test_rotate_keeps_identity_and_revoke_disables(): void
    {
        [$integration, $old] = ReminderIntegration::issue($this->user(), 'hermes');
        $this->assertSame(0, Artisan::call('reminders:integration', ['action' => 'rotate', '--integration' => $integration->id, '--output' => $this->dir.'/new.env']));
        $new = substr(trim(file_get_contents($this->dir.'/new.env')), strlen('CIRILO_HERMES_REMINDERS_TOKEN='));
        $this->assertNull(ReminderIntegration::findActiveByToken($old));
        $this->assertSame($integration->id, ReminderIntegration::findActiveByToken($new)?->id);

        $this->assertSame(0, Artisan::call('reminders:integration', ['action' => 'revoke', '--integration' => $integration->id]));
        $this->assertNull(ReminderIntegration::findActiveByToken($new));
    }
}
