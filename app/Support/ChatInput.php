<?php

namespace App\Support;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatInput
{
    public static function validate(Request $request): void
    {
        abort_unless($request->user(), 401);
        $request->validate([
            'prompt' => 'required|string|max:'.config('ai_security.prompt_length'),
            'history' => 'nullable|array|max:'.config('ai_security.history_messages'),
            'history.*' => 'required|array:role,content',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:'.config('ai_security.string_length'),
            'conversation_id' => 'nullable|integer|min:1',
            'generateAudio' => 'nullable|boolean',
            'voice' => 'nullable|in:alloy,echo,fable,nova,onyx,shimmer',
        ]);
        if ($request->input('history') === null) {
            $request->merge(['history' => []]);
        }
        self::authorizeConversation($request);
    }

    public static function authorizeConversation(Request $request): void
    {
        if ($request->filled('conversation_id')) {
            Conversation::where('user_id', $request->user()->id)
                ->findOrFail($request->input('conversation_id'));
        }
    }

    public static function conversationContent(Request $request): array
    {
        $request->validate(['content' => 'required|json|max:'.config('ai_security.conversation_bytes')]);
        $content = json_decode($request->input('content'), true);
        $messages = is_array($content) ? ($content['messages'] ?? $content) : null;
        Validator::make(['messages' => $messages], [
            'messages' => 'required|array|max:'.config('ai_security.history_messages'),
            'messages.*' => 'required|array:role,content,timestamp,image_path',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:'.config('ai_security.string_length'),
            'messages.*.timestamp' => 'sometimes|string|max:64',
            'messages.*.image_path' => 'nullable|string|max:2048',
        ])->validate();

        return ['messages' => $messages];
    }
}
