<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserProfileFact;
use App\Services\ConversationSummaryService;
use App\Services\ExplicitMemoryService;
use App\Services\MemoryService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Tests\SecurityTestCase;

/** F4-06: consultar, editar, olvidar y desactivar la extracción; lo olvidado no reaparece. */
class MemoryControlsTest extends SecurityTestCase
{
    private const MEETING = ['category' => 'preferences', 'key' => 'meeting_time', 'value' => 'Después de las 10', 'confidence' => 0.9];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mobile(string $method, string $uri, User $user, array $data = [])
    {
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])
            ->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function fact(User $user, array $overrides = []): UserProfileFact
    {
        MemoryService::upsertFact($user->id, $overrides + self::MEETING);

        return UserProfileFact::where('user_id', $user->id)->where('key', $overrides['key'] ?? self::MEETING['key'])->sole();
    }

    private function summarizeWith(User $user, array $facts): void
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        $this->provider->append(new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => 'R', 'extracted_facts' => $facts])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])));
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'T', 'content' => '{}']);
        app(ConversationSummaryService::class)->maybeSummarize($conversation, array_fill(0, 4, ['role' => 'user', 'content' => 'Mensaje']), true);
    }

    public function test_a_fact_forgotten_by_the_user_is_not_reinserted_by_a_later_extraction(): void
    {
        $user = $this->user();
        $fact = $this->fact($user);

        $this->mobile('DELETE', "/api/mobile/memory/{$fact->id}", $user)->assertOk();
        $this->summarizeWith($user, [self::MEETING]);
        Carbon::setTestNow(now()->addYear());
        MemoryService::saveFacts($user->id, [self::MEETING]);

        $this->assertSame(0, UserProfileFact::count());
        // El extractor recibe la lista de claves olvidadas para no proponerlas.
        $this->assertStringContainsString('preferences.meeting_time', (string) $this->provider->getLastRequest()->getBody());
    }

    public function test_clear_all_forgets_every_key_from_web_and_mobile(): void
    {
        $user = $this->user();
        $this->fact($user);
        $this->fact($user, ['category' => 'sports', 'key' => 'team', 'value' => 'Municipal']);

        $this->mobile('DELETE', '/api/mobile/memory', $user)->assertOk();
        MemoryService::saveFacts($user->id, [self::MEETING, ['category' => 'sports', 'key' => 'team', 'value' => 'Municipal']]);
        $this->assertSame(0, UserProfileFact::count());

        $other = $this->user();
        $this->fact($other);
        $this->actingAs($other)->deleteJson('/settings/memory')->assertOk();
        MemoryService::saveFacts($other->id, [self::MEETING]);
        $this->assertSame(0, UserProfileFact::count());
    }

    public function test_an_extraction_delete_blocks_stale_reinsertion_only_for_a_while(): void
    {
        $user = $this->user();
        $this->fact($user);
        MemoryService::upsertFact($user->id, self::MEETING + ['action' => 'delete']);

        MemoryService::saveFacts($user->id, [self::MEETING]);
        $this->assertSame(0, UserProfileFact::count(), 'Una extracción atrasada no revive lo que ya no aplica.');

        Carbon::setTestNow(now()->addDays(31));
        MemoryService::saveFacts($user->id, [self::MEETING]);
        $this->assertSame(1, UserProfileFact::count(), 'Pasada la ventana, un dato nuevo del mismo tipo se puede aprender.');
    }

    public function test_user_can_edit_a_fact_and_the_edit_is_protected(): void
    {
        $user = $this->user();
        $fact = $this->fact($user);

        $this->mobile('PATCH', "/api/mobile/memory/{$fact->id}", $user, ['value' => 'Después de las 11'])->assertOk()
            ->assertJsonPath('fact.value', 'Después de las 11')->assertJsonPath('fact.source_type', ExplicitMemoryService::SOURCE);
        MemoryService::saveFacts($user->id, [self::MEETING]);

        $this->assertSame('Después de las 11', $fact->fresh()->value);
        $this->mobile('PATCH', "/api/mobile/memory/{$fact->id}", $user, ['value' => ''])->assertStatus(422);
        $this->mobile('PATCH', "/api/mobile/memory/{$fact->id}", $user, ['value' => str_repeat('a', 501)])->assertStatus(422);

        $this->actingAs($user)->patchJson("/settings/memory/{$fact->id}", ['value' => 'A las 9'])->assertOk();
        $this->assertSame('A las 9', $fact->fresh()->value);
    }

    public function test_users_cannot_edit_or_delete_facts_of_others(): void
    {
        $owner = $this->user();
        $fact = $this->fact($owner);
        $intruder = $this->user();

        $this->mobile('PATCH', "/api/mobile/memory/{$fact->id}", $intruder, ['value' => 'x'])->assertNotFound();
        $this->mobile('DELETE', "/api/mobile/memory/{$fact->id}", $intruder)->assertNotFound();
        $this->actingAs($intruder)->patchJson("/settings/memory/{$fact->id}", ['value' => 'x'])->assertNotFound();
        $this->assertSame(self::MEETING['value'], $fact->fresh()->value);
    }

    public function test_disabling_extraction_stops_automatic_learning_but_keeps_explicit_requests(): void
    {
        $user = $this->user();
        $this->mobile('GET', '/api/mobile/memory', $user)->assertJsonPath('extraction_enabled', true);

        $this->mobile('PUT', '/api/mobile/memory/settings', $user, ['extraction_enabled' => false])->assertOk()->assertJsonPath('extraction_enabled', false);
        $this->summarizeWith($user, [self::MEETING]);
        $this->assertSame(0, UserProfileFact::count());
        $this->assertNotNull(Conversation::sole()->summary, 'El resumen de la conversación sigue funcionando.');

        ExplicitMemoryService::capture($user->id, 'Llámame Salva');
        $this->assertSame('Salva', UserProfileFact::sole()->value);

        $this->actingAs($user)->putJson('/settings/memory/extraction', ['enabled' => true])->assertOk();
        $this->assertTrue((bool) $user->fresh()->memory_extraction_enabled);
        $this->actingAs($user)->getJson('/settings/memory')->assertJsonPath('extraction_enabled', true);
    }

    public function test_explicit_restatement_lifts_a_previous_forget(): void
    {
        $user = $this->user();
        ExplicitMemoryService::capture($user->id, 'Llámame Salva');
        $this->mobile('DELETE', '/api/mobile/memory/'.UserProfileFact::sole()->id, $user)->assertOk();

        ExplicitMemoryService::capture($user->id, 'Llámame Mariana');

        $this->assertSame('Mariana', UserProfileFact::sole()->value);
    }
}
