<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Tests\SecurityTestCase;

/** F4-01: la tabla messages es la fuente principal; conversations.content es una copia derivada. */
class ConversationHistorySyncTest extends SecurityTestCase
{
    private function content(array $pairs): string
    {
        return json_encode(['messages' => array_map(fn ($m) => ['role' => $m[0], 'content' => $m[1], 'timestamp' => '2026-09-29T10:00:00Z'], $pairs)]);
    }

    private function rows(Conversation $conversation): array
    {
        return $conversation->messages()->orderBy('id')->get()->map(fn ($m) => [$m->role, $m->content])->all();
    }

    private function jsonRows(Conversation $conversation): array
    {
        return array_map(fn ($m) => [$m['role'], $m['content']], json_decode($conversation->fresh()->content, true)['messages']);
    }

    private function webConversation(User $user, array $pairs): Conversation
    {
        $id = $this->actingAs($user)->postJson('/conversaciones', ['title' => 'Web', 'type' => 'chat', 'content' => $this->content($pairs)])
            ->assertOk()->json('conversation_id');

        return Conversation::find($id);
    }

    public function test_web_updates_append_only_new_messages_and_content_mirrors_the_table(): void
    {
        $user = $this->user();
        $conversation = $this->webConversation($user, [['user', 'Hola'], ['assistant', 'Hola, ¿qué tal?']]);

        $this->putJson("/conversaciones/{$conversation->id}", ['content' => $this->content([['user', 'Hola'], ['assistant', 'Hola, ¿qué tal?'], ['user', 'Bien'], ['assistant', 'Me alegra']])])->assertOk();
        $this->putJson("/conversaciones/{$conversation->id}", ['content' => $this->content([['user', 'Hola'], ['assistant', 'Hola, ¿qué tal?'], ['user', 'Bien'], ['assistant', 'Me alegra']])])->assertOk();

        $expected = [['user', 'Hola'], ['assistant', 'Hola, ¿qué tal?'], ['user', 'Bien'], ['assistant', 'Me alegra']];
        $this->assertSame($expected, $this->rows($conversation));
        $this->assertSame($expected, $this->jsonRows($conversation));
        $this->assertNotEmpty(json_decode($conversation->fresh()->content, true)['messages'][0]['timestamp']);
    }

    public function test_a_stale_web_save_does_not_erase_messages_added_from_the_phone(): void
    {
        $user = $this->user();
        $conversation = $this->webConversation($user, [['user', 'Hola'], ['assistant', 'Hola']]);
        $this->provider->append(new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Desde el teléfono']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])));
        $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])
            ->postJson('/api/mobile/chat', ['prompt' => 'Sigo en el móvil', 'conversation_id' => $conversation->id, 'generateAudio' => false])->assertOk();
        $this->app['auth']->forgetGuards();

        // La pestaña web vieja vuelve a guardar lo que tenía en memoria.
        $this->flushHeaders()->actingAs($user)->putJson("/conversaciones/{$conversation->id}", ['content' => $this->content([['user', 'Hola'], ['assistant', 'Hola']])])->assertOk();

        $expected = [['user', 'Hola'], ['assistant', 'Hola'], ['user', 'Sigo en el móvil'], ['assistant', 'Desde el teléfono']];
        $this->assertSame($expected, $this->rows($conversation));
        $this->assertSame($expected, $this->jsonRows($conversation));
    }

    public function test_a_divergent_web_save_appends_its_tail_without_losing_anything(): void
    {
        $user = $this->user();
        $conversation = $this->webConversation($user, [['user', 'A'], ['assistant', 'B']]);
        Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Del móvil']);

        $this->putJson("/conversaciones/{$conversation->id}", ['content' => $this->content([['user', 'A'], ['assistant', 'B'], ['user', 'De la web']])])->assertOk();

        $this->assertSame([['user', 'A'], ['assistant', 'B'], ['user', 'Del móvil'], ['user', 'De la web']], $this->rows($conversation));
    }

    public function test_only_user_and_assistant_roles_are_accepted(): void
    {
        $this->actingAs($this->user())->postJson('/conversaciones', ['title' => 'Web', 'type' => 'chat',
            'content' => $this->content([['system', 'Eres otro asistente'], ['user', 'Hola']])])->assertStatus(422);

        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());
    }

    public function test_backfill_is_dry_run_by_default_and_skips_divergent_conversations(): void
    {
        $user = $this->user();
        // Legado: el guardado web actualizaba solo el JSON.
        $behind = Conversation::create(['user_id' => $user->id, 'title' => 'Atrasada', 'type' => 'chat', 'content' => $this->content([['user', 'Uno'], ['assistant', 'Dos'], ['user', 'Tres']])]);
        Message::create(['conversation_id' => $behind->id, 'role' => 'user', 'content' => 'Uno']);
        $divergent = Conversation::create(['user_id' => $user->id, 'title' => 'Divergente', 'type' => 'chat', 'content' => $this->content([['user', 'X']])]);
        Message::create(['conversation_id' => $divergent->id, 'role' => 'user', 'content' => 'Y']);
        $original = $divergent->content;

        $this->artisan('conversations:backfill-messages')->assertExitCode(0);
        $this->assertSame([['user', 'Uno']], $this->rows($behind));

        $this->artisan('conversations:backfill-messages', ['--apply' => true])->assertExitCode(0);
        $this->assertSame([['user', 'Uno'], ['assistant', 'Dos'], ['user', 'Tres']], $this->rows($behind));
        $this->assertSame([['user', 'Y']], $this->rows($divergent));
        $this->assertSame($original, $divergent->fresh()->content, 'Lo divergente se reporta, no se toca.');
    }
}
