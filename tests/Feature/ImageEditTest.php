<?php

namespace Tests\Feature;

use App\Models\ApiUsageLog;
use App\Models\ImageEdit;
use App\Models\Message;
use App\Models\User;
use App\Services\ImageQuotaService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\SecurityTestCase;

/** IE1: editar una imagen con el modelo que sabe editar, con cuota compartida, privacidad y retención de 7 días. */
class ImageEditTest extends SecurityTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00', 'America/Guatemala'));
        Storage::fake('image_edits');
        config(['services.openai.api_key' => 'fake-test-key', 'ai.image_edit.enabled' => true]);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeProvider(): void
    {
        $png = base64_encode($this->pngBytes(64, 64));
        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['created' => 1, 'data' => [['b64_json' => $png]],
            'usage' => ['input_tokens' => 600, 'input_tokens_details' => ['text_tokens' => 50, 'image_tokens' => 550], 'output_tokens' => 1056, 'total_tokens' => 1656]])]);
    }

    private function pngBytes(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function edit(array $data = [], ?string $key = 'edit-1', ?User $user = null)
    {
        $headers = ['Authorization' => 'Bearer '.($user ?? $this->owner)->createToken('android')->plainTextToken, 'Accept' => 'application/json'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        $response = $this->flushHeaders()->withHeaders($headers)->post('/api/mobile/images/edit', $data + [
            'image' => UploadedFile::fake()->image('foto.jpg', 3000, 2000),
            'instruction' => 'Ponle un sombrero de pirata al perro',
        ]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function fetchResult(string $url, ?User $user = null)
    {
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.($user ?? $this->owner)->createToken('android')->plainTextToken])->get($url);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    public function test_the_feature_is_off_by_default(): void
    {
        config(['ai.image_edit.enabled' => false]);
        Http::fake();

        $this->edit()->assertStatus(503)->assertJsonPath('code', 'image_edit_disabled');
        Http::assertNothingSent();
    }

    public function test_an_image_is_edited_stored_privately_and_served_only_to_its_owner(): void
    {
        $this->fakeProvider();

        $response = $this->edit()->assertCreated();

        $id = $response->json('id');
        $response->assertJsonPath('round', 1)->assertJsonPath('remaining', 2);
        $this->assertSame('/api/mobile/images/edits/'.$id, $response->json('url'));
        $this->assertSame('2026-10-07', Carbon::parse($response->json('expires_at'))->setTimezone('America/Guatemala')->toDateString());
        Http::assertSent(function (HttpRequest $request) {
            $parts = collect($request->data())->keyBy('name');
            $image = imagecreatefromstring($parts['image']['contents']);

            return $request->isMultipart() && $parts['model']['contents'] === 'gpt-image-1' && $parts['quality']['contents'] === 'medium'
                && $parts['size']['contents'] === 'auto' && str_starts_with($parts['image']['contents'], "\x89PNG")
                && max(imagesx($image), imagesy($image)) <= 2048 && str_contains($parts['prompt']['contents'], 'sombrero');
        });
        // Solo se guarda el resultado; el original nunca toca el disco.
        $this->assertCount(1, Storage::disk('image_edits')->allFiles());

        $this->fetchResult($response->json('url'))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->fetchResult($response->json('url'), $this->user())->assertNotFound();

        $usage = ApiUsageLog::where('api_type', 'image_generation')->sole();
        $this->assertSame(['success', 'gpt-image-1'], [$usage->status, $usage->model]);
        $this->assertGreaterThan(0, (float) $usage->estimated_cost, 'El costo sale del usage de la respuesta multipart.');
        // En la conversación solo queda texto.
        $this->assertSame(['[Editar imagen] Ponle un sombrero de pirata al perro', '[Imagen editada: disponible 7 días en la app] ¿Quedó como querías o cambio algo? Puedo hacerte 2 ajustes más.'],
            Message::where('conversation_id', $response->json('conversation_id'))->orderBy('id')->pluck('content')->all());
    }

    public function test_a_retry_with_the_same_key_returns_the_same_result_and_another_request_conflicts(): void
    {
        $this->fakeProvider();

        $first = $this->edit()->assertCreated();
        $again = $this->edit()->assertOk();

        $this->assertSame($first->json('id'), $again->json('id'));
        Http::assertSentCount(1);
        $this->edit(['instruction' => 'Otra cosa distinta'])->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->edit([], null)->assertStatus(422);
    }

    public function test_a_content_rejection_does_not_consume_quota(): void
    {
        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['error' => ['code' => 'moderation_blocked', 'message' => 'Your request was rejected by the safety system.']], 400)]);

        $this->edit()->assertStatus(422)->assertJsonPath('code', 'content_rejected')->assertJsonMissing(['message' => 'Your request was rejected by the safety system.']);

        $this->assertSame(0, app(ImageQuotaService::class)->used($this->owner));
        $this->assertSame('rejected', ImageEdit::sole()->status);
    }

    public function test_a_timeout_is_uncertain_and_keeps_the_reservation(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('timeout');
        });

        $this->edit()->assertStatus(504)->assertJsonPath('code', 'provider_timeout');

        $this->assertSame(1, app(ImageQuotaService::class)->used($this->owner));
        $this->assertSame('uncertain', ImageEdit::sole()->status);
        $this->assertSame(1, $calls, 'Sin reintento automático.');
    }

    public function test_quota_is_shared_with_image_generation(): void
    {
        $this->owner->forceFill(['daily_image_limit' => 1])->save();
        ApiUsageLog::create(['user_id' => $this->owner->id, 'api_provider' => 'openai', 'api_type' => 'image_generation', 'model' => 'gpt-image-1', 'status' => 'success']);
        Http::fake();

        $this->edit()->assertStatus(429)->assertJsonPath('code', 'image_quota_exceeded');
        Http::assertNothingSent();
        $this->assertSame('failed', ImageEdit::sole()->status);
    }

    public function test_input_is_validated(): void
    {
        Http::fake();

        $this->edit(['instruction' => 'no'])->assertStatus(422);
        $this->edit(['image' => UploadedFile::fake()->create('nota.txt', 5, 'text/plain')], 'edit-2')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_results_are_purged_after_seven_days(): void
    {
        $this->fakeProvider();
        $url = $this->edit()->assertCreated()->json('url');

        Carbon::setTestNow(now()->addDays(8));
        $this->artisan('images:purge-edits')->assertExitCode(0);

        $this->assertCount(0, Storage::disk('image_edits')->allFiles());
        $this->assertSame('expired', ImageEdit::sole()->status);
        $this->fetchResult($url)->assertStatus(410);
    }

    private function refine(string $id, string $instruction, string $key, ?User $user = null)
    {
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.($user ?? $this->owner)->createToken('android')->plainTextToken,
            'Accept' => 'application/json', 'Idempotency-Key' => $key])->postJson("/api/mobile/images/edits/{$id}/refine", ['instruction' => $instruction]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    public function test_cirilo_guides_two_refinements_on_the_latest_result_and_then_stops(): void
    {
        config(['ai_security.requests_per_minute.image' => 20]);
        $this->fakeProvider();
        $first = $this->edit()->assertCreated();

        $second = $this->refine($first->json('id'), 'Que el sombrero sea rojo', 'r-1')->assertCreated()
            ->assertJsonPath('round', 2)->assertJsonPath('remaining', 1)
            ->assertJsonPath('assistant_message', '¿Así está bien o cambio algo más? Me queda 1 ajuste.')
            ->assertJsonPath('conversation_id', $first->json('conversation_id'));
        $third = $this->refine($second->json('id'), 'Agrega un loro', 'r-2')->assertCreated()
            ->assertJsonPath('round', 3)->assertJsonPath('remaining', 0)
            ->assertJsonPath('assistant_message', 'Esta es la última modificación que pude trabajarte.');

        $this->refine($third->json('id'), 'Otra más', 'r-3')->assertStatus(422)->assertJsonPath('code', 'edit_limit_reached');
        // Ajustar una imagen ya ajustada abriría otra rama y saltaría el límite.
        $this->refine($first->json('id'), 'Desde la primera', 'r-4')->assertStatus(409)->assertJsonPath('code', 'already_refined');

        Http::assertSentCount(3);
        Http::assertSent(fn (HttpRequest $request) => str_contains((string) collect($request->data())->firstWhere('name', 'prompt')['contents'],
            'Mantén la imagen igual y cambia solo lo siguiente: Agrega un loro'));
        $this->assertSame(3, app(ImageQuotaService::class)->used($this->owner), 'Cada ajuste cuenta en la cuota.');
        $this->assertSame([
            '[Editar imagen] Ponle un sombrero de pirata al perro',
            '[Imagen editada: disponible 7 días en la app] ¿Quedó como querías o cambio algo? Puedo hacerte 2 ajustes más.',
            '[Ajustar imagen] Que el sombrero sea rojo',
            '[Imagen ajustada: disponible 7 días en la app] ¿Así está bien o cambio algo más? Me queda 1 ajuste.',
            '[Ajustar imagen] Agrega un loro',
            '[Imagen ajustada: disponible 7 días en la app] Esta es la última modificación que pude trabajarte.',
        ], Message::where('conversation_id', $first->json('conversation_id'))->orderBy('id')->pluck('content')->all());
    }

    public function test_a_refinement_retry_is_idempotent_and_others_cannot_refine_my_image(): void
    {
        $this->fakeProvider();
        $first = $this->edit()->assertCreated();

        $a = $this->refine($first->json('id'), 'Que el sombrero sea rojo', 'r-1')->assertCreated();
        $b = $this->refine($first->json('id'), 'Que el sombrero sea rojo', 'r-1')->assertOk();
        $this->assertSame($a->json('id'), $b->json('id'));
        $this->refine($first->json('id'), 'Otra cosa', 'r-1')->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');

        $this->refine($a->json('id'), 'Hackear', 'x-1', $this->user())->assertNotFound();
        Http::assertSentCount(2);
    }

    public function test_a_failed_refinement_can_be_retried_and_an_expired_image_cannot_be_refined(): void
    {
        $this->fakeProvider();
        $first = $this->edit()->assertCreated();
        Http::swap(new Factory);
        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['error' => ['code' => 'moderation_blocked', 'message' => 'safety']], 400)]);

        $this->refine($first->json('id'), 'Algo no permitido', 'r-1')->assertStatus(422)->assertJsonPath('code', 'content_rejected');
        Http::swap(new Factory);
        $this->fakeProvider();
        $this->refine($first->json('id'), 'Que el sombrero sea rojo', 'r-2')->assertCreated()->assertJsonPath('round', 2);

        Carbon::setTestNow(now()->addDays(8));
        $this->refine($first->json('id'), 'Tarde', 'r-3')->assertStatus(410)->assertJsonPath('code', 'image_expired');
    }

    public function test_refining_is_off_with_the_feature_flag(): void
    {
        $this->fakeProvider();
        $first = $this->edit()->assertCreated();
        config(['ai.image_edit.enabled' => false]);

        $this->refine($first->json('id'), 'Que el sombrero sea rojo', 'r-1')->assertStatus(503);
    }
}
