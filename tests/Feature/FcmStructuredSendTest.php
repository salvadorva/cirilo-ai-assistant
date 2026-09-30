<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Services\FcmService;
use App\Services\Reminders\Push\PushResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\SecurityTestCase;

/** RC2: FcmService::send con HTTP simulado; credenciales sustituidas, nunca leídas. */
class FcmStructuredSendTest extends SecurityTestCase
{
    private DeviceToken $device;

    private FcmService $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_05_22_101002_create_device_tokens_table.php',
            'database/migrations/2026_09_23_110000_add_reminder_dispatch.php', 'database/migrations/2026_09_23_120000_add_reminder_device_receipts.php'], '--force' => true]);
        $this->device = DeviceToken::create(['user_id' => $this->user()->id, 'token' => 'SECRET_FCM_TOKEN_VALUE_123456789']);
        $this->fcm = new class extends FcmService
        {
            protected function getAccessToken(): string
            {
                return 'fake-access-token';
            }

            protected function getProjectId(): string
            {
                return 'demo-project';
            }
        };
    }

    private function error(int $status, ?string $fcmCode, ?string $grpc = null, array $headers = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $details = $fcmCode ? [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $fcmCode]] : [];
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['code' => $status, 'status' => $grpc, 'message' => 'x', 'details' => $details]], $status, $headers)]);
    }

    public function test_accepted_message_uses_data_only_payload_with_ttl(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo-project/messages/0:123'])]);
        $result = $this->fcm->send($this->device, ['type' => 'contextual_reminder', 'version' => '1'], 5400);

        $this->assertSame(PushResult::ACCEPTED, $result->outcome);
        $this->assertSame('projects/demo-project/messages/0:123', $result->messageId);
        Http::assertSent(function (Request $request) {
            $message = $request->data()['message'];

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/demo-project/messages:send'
                && $message['android'] === ['priority' => 'high', 'ttl' => '5400s']
                && ! isset($message['notification'])
                && $message['data'] === ['type' => 'contextual_reminder', 'version' => '1'];
        });
    }

    public function test_success_without_message_id_is_uncertain(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response([])]);
        $this->assertSame(PushResult::UNCERTAIN, $this->fcm->send($this->device, [], 60)->outcome);
    }

    public function test_structured_error_classification(): void
    {
        $cases = [
            [404, 'UNREGISTERED', 'NOT_FOUND', PushResult::UNREGISTERED, 'unregistered'],
            [400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT', PushResult::INVALID_PAYLOAD, 'invalid_argument'],
            [400, null, 'INVALID_ARGUMENT', PushResult::INVALID_PAYLOAD, 'invalid_argument'],
            [403, 'SENDER_ID_MISMATCH', 'PERMISSION_DENIED', PushResult::REJECTED, 'sender_id_mismatch'],
            [401, 'THIRD_PARTY_AUTH_ERROR', 'UNAUTHENTICATED', PushResult::AUTH, 'auth'],
            [401, null, 'UNAUTHENTICATED', PushResult::AUTH, 'auth'],
            [503, 'UNAVAILABLE', 'UNAVAILABLE', PushResult::RETRYABLE, 'unavailable'],
            [500, 'INTERNAL', 'INTERNAL', PushResult::RETRYABLE, 'internal'],
            [409, null, 'ABORTED', PushResult::REJECTED, 'rejected'],
        ];
        foreach ($cases as [$status, $code, $grpc, $outcome, $category]) {
            $this->error($status, $code, $grpc);
            $result = $this->fcm->send($this->device, [], 60);
            $this->assertSame([$outcome, $category, $status], [$result->outcome, $result->category, $result->httpStatus], "{$status} {$code}");
        }
    }

    public function test_quota_exceeded_carries_retry_after(): void
    {
        $this->error(429, 'QUOTA_EXCEEDED', 'RESOURCE_EXHAUSTED', ['Retry-After' => '30']);
        $result = $this->fcm->send($this->device, [], 60);
        $this->assertSame([PushResult::RETRYABLE, 'quota_exceeded', 30], [$result->outcome, $result->category, $result->retryAfter]);
    }

    public function test_timeout_is_uncertain_and_token_is_not_deleted(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
        $this->assertSame(PushResult::UNCERTAIN, $this->fcm->send($this->device, [], 60)->outcome);

        $this->error(404, 'UNREGISTERED');
        $this->fcm->send($this->device, [], 60);
        $this->assertNotNull($this->device->fresh()); // decidir es del despachador, no del transporte
    }

    public function test_missing_credentials_are_a_configuration_failure_without_network(): void
    {
        Http::fake();
        $result = app(FcmService::class)->send($this->device, [], 60); // en testing las credenciales están bloqueadas
        $this->assertSame([PushResult::AUTH, 'credentials'], [$result->outcome, $result->category]);
        Http::assertNothingSent();
    }

    public function test_structured_send_never_logs_the_device_token(): void
    {
        $spy = new TestHandler;
        Log::extend('spy', fn () => new Logger('spy', [$spy]));
        config(['logging.channels.spy' => ['driver' => 'spy'], 'logging.default' => 'spy']);
        $this->error(503, 'UNAVAILABLE');
        $this->fcm->send($this->device, [], 60);
        $this->assertStringNotContainsString('SECRET_FCM', json_encode(array_map(fn ($r) => $r->toArray(), $spy->getRecords())));
    }
}
