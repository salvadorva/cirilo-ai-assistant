<?php

namespace App\Http\Controllers;

use App\Models\AiInteraction;
use Illuminate\Http\Request;

class InteractionFeedbackController extends Controller
{
    public function store(Request $request, string $id)
    {
        $interaction = AiInteraction::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'useful' => 'required|boolean', 'task_achieved' => 'required|boolean',
            'corrections' => 'required|integer|min:0|max:100',
        ]);
        $interaction->update($data);

        return response()->json(['success' => true]);
    }
}
