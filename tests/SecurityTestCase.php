<?php

namespace Tests;

use App\Http\Middleware\GameProgressMiddleware;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

abstract class SecurityTestCase extends TestCase
{
    protected MockHandler $provider;

    protected TestHandler $aiLogs;

    protected function setUp(): void
    {
        parent::setUp();
        // Refuse to migrate if someone has enabled a production config cache.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        // Las pruebas guionan secuencias exactas del proveedor: el reintento se activa solo donde se prueba.
        config(['ai.retry.max' => 0]);
        config(['services.openai.api_key' => '', 'services.grok.api_key' => '',
            'features.agenda_enabled' => true, 'app.debug' => false]);

        $migrations = [
            '0001_01_01_000000_create_users_table.php',
            '2024_05_29_220537_create_roles_table.php',
            '2024_05_29_221320_add_role_id_to_users_table.php',
            '2025_05_16_173628_add_prompt_to_users_table.php',
            '2025_05_26_224254_add_ai_provider_to_users_table.php',
            '2025_11_25_135022_add_daily_image_limit_to_users_table.php',
            '2025_06_12_220448_create_conversations_table.php',
            '2025_06_12_220502_create_messages_table.php',
            '2026_03_17_100211_add_summary_to_conversations_table.php',
            '2026_04_13_065407_add_memory_fields_to_conversations_table.php',
            '2026_04_13_065407_create_user_profile_facts_table.php',
            '2023_07_03_000000_create_calendar_events_table.php',
            '2026_04_13_102918_add_series_id_to_calendar_events_table.php',
            '2025_11_17_141855_create_api_usage_logs_table.php',
            '2026_05_22_100926_create_personal_access_tokens_table.php',
            '2025_10_17_092552_create_static_audios_table.php',
            '2026_09_22_100000_add_ai_observability.php',
            '2026_09_29_100000_add_memory_controls.php',
            '2026_09_29_110000_create_agenda_drafts_and_operations.php',
            '2026_09_29_120000_create_event_notification_deliveries_table.php',
            '2026_09_30_100000_create_tasks_and_daily_summary.php',
            '2026_09_30_110000_create_image_edits_table.php',
            '2026_10_01_120000_add_refine_chain_to_image_edits.php',
        ];
        $this->artisan('migrate', ['--path' => array_map(fn ($file) => 'database/migrations/'.$file, $migrations), '--force' => true])
            ->assertExitCode(0);
        $this->withoutMiddleware(GameProgressMiddleware::class);
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->provider = new MockHandler;
        $this->app->bind(Client::class, fn ($app, $parameters) => new Client(array_merge(
            $parameters['config'] ?? [], ['handler' => HandlerStack::create($this->provider)]
        )));
        Storage::fake('public');
        $this->aiLogs = new TestHandler;
        $handler = $this->aiLogs;
        Log::extend('security-test', fn () => new Logger('ai-test', [$handler]));
        config(['logging.channels.ai' => ['driver' => 'security-test']]);
        Log::forgetChannel('ai');
    }

    protected function user(string $role = 'user', int $imageLimit = 4): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $role]);

        $user = User::factory()->create();
        $user->forceFill(['role_id' => $roleId, 'daily_image_limit' => $imageLimit])->save();

        return $user;
    }
}
