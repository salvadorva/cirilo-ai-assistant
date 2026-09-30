<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use App\Services\MemoryService;
use Carbon\Carbon;
use Tests\SecurityTestCase;

/** F4-04: recuperar conversaciones y hechos por tema, fecha y acuerdos, más allá de las tres recientes. */
class MemoryRetrievalTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function conversation(int $userId, string $title, string $summary, Carbon $at, array $extra = []): Conversation
    {
        $conversation = Conversation::create($extra + ['user_id' => $userId, 'title' => $title, 'summary' => $summary, 'type' => 'chat',
            'content' => json_encode(['messages' => [['role' => 'user', 'content' => $title]]])]);
        $conversation->forceFill(['updated_at' => $at, 'created_at' => $at])->saveQuietly();

        return $conversation;
    }

    private function recent(int $userId): void
    {
        foreach (range(1, 3) as $i) {
            $this->conversation($userId, "Reciente {$i}", "Charla reciente {$i}", now()->subMinutes($i));
        }
    }

    public function test_an_older_conversation_about_the_same_topic_is_retrieved(): void
    {
        $user = $this->user();
        $this->recent($user->id);
        $this->conversation($user->id, 'Presupuesto de la boda', 'El salón cuesta Q15,000 y falta la música.', now()->subDays(40));
        $this->conversation($user->id, 'Receta de pan', 'Harina, agua y levadura.', now()->subDays(20));

        $block = MemoryService::buildContextBlock($user->id, null, '¿Cuánto era el presupuesto de la BODA?');

        $this->assertStringContainsString('Q15,000', $block);
        $this->assertStringNotContainsString('levadura', $block);
        $this->assertStringContainsString('relacionadas con la pregunta', $block);
    }

    public function test_asking_about_an_agreement_retrieves_older_conversations_with_decisions(): void
    {
        $user = $this->user();
        $this->recent($user->id);
        $this->conversation($user->id, 'Reunión con Ana', 'Hablamos del proyecto.', now()->subDays(10), ['decisions' => ['Entregar el informe el viernes']]);
        $this->conversation($user->id, 'Clima', 'Llovió mucho.', now()->subDays(5));

        $block = MemoryService::buildContextBlock($user->id, null, '¿Qué acordamos la otra vez?');

        $this->assertStringContainsString('Entregar el informe el viernes', $block);
        $this->assertStringNotContainsString('Llovió', $block);
    }

    public function test_date_references_retrieve_that_day(): void
    {
        $user = $this->user();
        $this->recent($user->id);
        $this->conversation($user->id, 'Plan del jardín', 'Sembrar tomates.', now()->subDay()->setTime(9, 0));
        $this->conversation($user->id, 'Viaje', 'Ir a Antigua.', now()->subDays(9));

        $block = MemoryService::buildContextBlock($user->id, null, '¿De qué hablamos ayer?');

        $this->assertStringContainsString('Sembrar tomates', $block);
        $this->assertStringNotContainsString('Antigua', $block);
    }

    public function test_at_most_two_related_conversations_and_never_from_other_users(): void
    {
        $user = $this->user();
        $this->recent($user->id);
        foreach (range(1, 4) as $i) {
            $this->conversation($user->id, "Guitarra {$i}", "Practicar guitarra MARCA{$i}", now()->subDays(10 + $i));
        }
        $this->conversation($this->user()->id, 'Guitarra ajena', 'Guitarra de OTRO_USUARIO', now()->subDays(2));

        $block = MemoryService::buildContextBlock($user->id, null, 'Consejos para la guitarra');

        $this->assertSame(2, preg_match_all('/MARCA\d/', $block));
        $this->assertStringNotContainsString('OTRO_USUARIO', $block);
    }

    public function test_old_interest_facts_come_back_when_the_question_is_about_them(): void
    {
        $user = $this->user();
        UserProfileFact::create(['user_id' => $user->id, 'category' => 'sports', 'key' => 'weekly_sport', 'value' => 'Juega fútbol los sábados',
            'confidence' => 0.7, 'source_type' => 'extracted', 'last_mentioned_at' => now()->subDays(90)]);

        $this->assertStringNotContainsString('fútbol', MemoryService::buildContextBlock($user->id, null, '¿Qué hago hoy?'));
        $this->assertStringContainsString('Juega fútbol los sábados', MemoryService::buildContextBlock($user->id, null, 'Quiero mejorar en el futbol'));
    }

    public function test_without_a_question_the_block_is_unchanged(): void
    {
        $user = $this->user();
        $this->recent($user->id);
        $this->conversation($user->id, 'Presupuesto de la boda', 'Q15,000', now()->subDays(40));

        $this->assertStringNotContainsString('Q15,000', MemoryService::buildContextBlock($user->id));
    }
}
