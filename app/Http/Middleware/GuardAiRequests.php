<?php

namespace App\Http\Middleware;

use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Services\InteractionTracker;
use App\Support\ChatInput;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class GuardAiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $action = $request->route()?->getActionName() ?? '';
        [$class, $method] = array_pad(explode('@', $action), 2, '');
        $class = class_basename($class);
        $profile = $this->profile($class, $method);
        if ($profile === null) {
            return $next($request);
        }

        if (! $request->user()) {
            throw new AuthenticationException;
        }

        return app(InteractionTracker::class)->run($request, fn () => $this->guard($request, $next, $profile, $class, $method));
    }

    private function guard(Request $request, Closure $next, string $profile, string $class, string $method): Response
    {

        $limit = (int) config("ai_security.requests_per_minute.{$profile}", 10);
        $key = "ai:{$profile}:{$request->user()->id}";
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['error' => 'Demasiadas solicitudes. Intenta de nuevo en un momento.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $maxBytes = (int) config($profile === 'conversation' ? 'ai_security.conversation_bytes' : 'ai_security.input_bytes');
        // input() excludes uploaded files; their own limits are checked below.
        abort_if(strlen(json_encode($request->input()) ?: '') > $maxBytes, 413, 'Solicitud demasiado grande.');
        $this->checkInput($request->input(), $profile === 'conversation');
        foreach (Arr::flatten($request->allFiles()) as $file) {
            abort_if(! $file->isValid() || $file->getSize() > ($profile === 'stt' ? 25 : 5) * 1024 * 1024, 413);
        }

        if (($class === 'AIController' && $method === 'generateText') || ($class === 'MobileChatController' && $method === 'chat')) {
            ChatInput::validate($request);
        }
        if ($class === 'MobileChatController' && $method === 'chatWithImage') {
            $request->validate(['conversation_id' => 'nullable|integer|min:1']);
            ChatInput::authorizeConversation($request);
        }
        if ($class === 'ConversationController') {
            if ($id = $request->route('id')) {
                Conversation::where('user_id', $request->user()->id)->findOrFail($id);
            }
            if (in_array($method, ['store', 'update'], true)) {
                $request->merge(['content' => json_encode(ChatInput::conversationContent($request))]);
            }
        }
        if ($class === 'AgendaController') {
            if ($id = $request->route('id')) {
                CalendarEvent::where('user_id', $request->user()->id)->findOrFail($id);
            }
            if ($series = $request->route('seriesId')) {
                abort_unless(CalendarEvent::where('user_id', $request->user()->id)->where('series_id', $series)->exists(), 404);
            }
        }

        return $next($request);
    }

    private function checkInput(array $input, bool $conversation, int $depth = 0): void
    {
        abort_if($depth > 8 || count($input) > 2000, 413);
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $this->checkInput($value, false, $depth + 1);
            } elseif (is_string($value)) {
                $max = $conversation && $key === 'content'
                    ? config('ai_security.conversation_bytes') : config('ai_security.string_length');
                abort_if(mb_strlen($value) > $max, 413, 'Texto demasiado largo.');
            }
        }
    }

    private function profile(string $class, string $method): ?string
    {
        if ($class === 'AIController') {
            return match ($method) {
                'generateText' => 'chat', 'generateImage' => 'image',
                'textToSpeech' => 'tts', 'speechToText' => 'stt',
                default => 'generation',
            };
        }
        if ($class === 'MobileChatController') {
            return 'chat';
        }
        if ($class === 'MobileImageEditController') {
            return in_array($method, ['edit', 'refine']) ? 'image' : 'resource';
        }
        if (in_array($class, ['MobileImageController', 'ImageAnalysisController'])) {
            return str_starts_with($method, 'save') ? 'conversation' : 'image';
        }
        if ($class === 'ConversationController' || ($class === 'CreativeModeController' && str_starts_with($method, 'save'))) {
            return 'conversation';
        }
        if (in_array($class, ['AgendaController', 'MobileAgendaController'])) {
            return $method === 'processVoiceRequest' ? 'chat' : 'resource';
        }
        if ($class === 'MobileFocusSlotController') {
            return $method === 'previewAudio' ? 'tts' : 'resource';
        }
        if ($class === 'HomeController') {
            return in_array($method, ['home_index', 'refreshHomeMessage']) ? 'generation' : null;
        }
        if ($class === 'StaticAudioController') {
            return in_array($method, ['regenerate', 'addFunnyPhrase', 'updateText', 'approve']) ? 'tts' : null;
        }
        if (in_array($class, ['CreativeModeController', 'TutorController', 'CourseController', 'AIExerciseController', 'TypeMasterController', 'EngagementController', 'EnglishGamesController'])) {
            return 'generation';
        }

        return null;
    }
}
