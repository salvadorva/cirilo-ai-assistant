<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationSummaryService;
use App\Services\MemoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function __construct(private ConversationSummaryService $summaryService) {}

    /**
     * Mostrar la lista de conversaciones del usuario.
     */
    public function index()
    {
        return redirect()->route('conversations_history');
    }

    /**
     * Mostrar una conversación específica.
     */
    public function show($id)
    {
        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== Auth::id()) {
            return redirect()->route('conversations_history')
                ->with('error', 'No tienes permiso para ver esta conversación.');
        }

        return redirect()->route('conversations_history', ['open' => $id]);
    }

    /**
     * Guardar una nueva conversación.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|max:255',
            'type'    => 'required|in:chat,creative,image_analysis',
            'content' => 'required|json',
        ]);

        $conversation             = new Conversation;
        $conversation->user_id   = Auth::id();
        $conversation->title     = $request->title;
        $conversation->type      = $request->type;
        $conversation->content   = $request->content;
        $conversation->save();

        // Guardar mensajes individuales
        $contentData = json_decode($request->content, true);
        $messages    = $contentData['messages'] ?? $contentData;
        foreach ($messages as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $message                  = new Message;
                $message->conversation_id = $conversation->id;
                $message->role            = $msg['role'];
                $message->content         = $msg['content'];
                $message->image_path      = $msg['image_path'] ?? null;
                $message->save();
            }
        }

        $this->summaryService->maybeSummarize($conversation, $messages);
        $this->summaryService->summarizeOpenConversations(Auth::id(), $conversation->id);

        return response()->json([
            'success'         => true,
            'message'         => 'Conversación guardada correctamente',
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Actualizar el contenido de una conversación existente.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|json',
        ]);

        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
        }

        $conversation->content = $request->content;
        $conversation->save();

        $contentData = json_decode($request->content, true);
        $messages    = $contentData['messages'] ?? [];
        $this->summaryService->maybeSummarize($conversation, $messages);

        return response()->json([
            'success'         => true,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Eliminar una conversación.
     */
    public function destroy($id)
    {
        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== Auth::id()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
            }

            return redirect()->route('conversations_history')->with('error', 'No tienes permiso para eliminar esta conversación.');
        }

        $conversation->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('conversations_history')->with('success', 'Conversación eliminada.');
    }

    /**
     * Obtener todas las conversaciones del usuario (para API).
     */
    public function getAll()
    {
        $user          = Auth::user();
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success'       => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Obtener una conversación específica (para API).
     */
    public function get($id)
    {
        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para ver esta conversación.',
            ], 403);
        }

        return response()->json([
            'success'      => true,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Eliminar todas las conversaciones del usuario.
     */
    public function clearAll()
    {
        $user = Auth::user();
        Conversation::where('user_id', $user->id)->delete();
        session()->forget('pending_calendar_event_creation');

        return response()->json([
            'success' => true,
            'message' => 'Todas las conversaciones han sido eliminadas',
        ]);
    }

    /**
     * Continuar una conversación existente.
     */
    public function continueConversation($id)
    {
        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== Auth::id()) {
            return redirect()->route('conversations.index')
                ->with('error', 'No tienes permiso para continuar esta conversación.');
        }

        switch ($conversation->type) {
            case 'chat':
                return redirect()->route('preguntas', ['conversation_id' => $conversation->id]);
            case 'creative':
                return redirect()->route('creative_mode', ['conversation_id' => $conversation->id]);
            case 'image':
                return redirect()->route('image_analysis', ['conversation_id' => $conversation->id]);
            default:
                return redirect()->route('preguntas', ['conversation_id' => $conversation->id]);
        }
    }

}
