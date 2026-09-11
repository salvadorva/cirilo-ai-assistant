<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileConversationController extends Controller
{
    /**
     * GET /api/mobile/conversations?page=1
     * Lista paginada (20 por página) con preview del último mensaje.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->paginate(20)
            ->through(function (Conversation $c) {
                $lastMessage = $c->messages()->orderByDesc('id')->first(['role', 'content']);

                return [
                    'id'                   => $c->id,
                    'title'                => $c->title,
                    'summary'              => $c->summary,
                    'topics'               => $c->topics,
                    'last_message_preview' => $lastMessage
                        ? mb_substr($lastMessage->content, 0, 120)
                        : null,
                    'last_message_role'    => $lastMessage?->role,
                    'messages_count'       => $c->messages()->count(),
                    'updated_at'           => $c->updated_at?->toIso8601String(),
                ];
            });

        return response()->json($conversations);
    }

    /**
     * GET /api/mobile/conversations/{id}
     * Detalle con todos los mensajes.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'image_path', 'created_at']);

        return response()->json([
            'id'             => $conversation->id,
            'title'          => $conversation->title,
            'summary'        => $conversation->summary,
            'topics'         => $conversation->topics,
            'decisions'      => $conversation->decisions,
            'pending_items'  => $conversation->pending_items,
            'created_at'     => $conversation->created_at?->toIso8601String(),
            'updated_at'     => $conversation->updated_at?->toIso8601String(),
            'messages'       => $messages->map(fn ($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'content'    => $m->content,
                'image_path' => $m->image_path,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * DELETE /api/mobile/conversations/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/mobile/conversations
     * Borra todas las conversaciones del usuario.
     */
    public function clearAll(Request $request): JsonResponse
    {
        Conversation::where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
