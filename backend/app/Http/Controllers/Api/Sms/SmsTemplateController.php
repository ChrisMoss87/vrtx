<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SmsTemplate::with('creator:id,name');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $templates = $query->orderBy('name')->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:1600',
            'category' => 'nullable|string|in:marketing,transactional,support',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();

        $template = SmsTemplate::create($validated);

        return response()->json(['data' => $template], 201);
    }

    public function show(SmsTemplate $smsTemplate): JsonResponse
    {
        $smsTemplate->load('creator:id,name');
        $smsTemplate->loadCount('messages');

        return response()->json(['data' => $smsTemplate]);
    }

    public function update(Request $request, SmsTemplate $smsTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:1600',
            'category' => 'nullable|string|in:marketing,transactional,support',
            'is_active' => 'sometimes|boolean',
        ]);

        $smsTemplate->update($validated);

        return response()->json(['data' => $smsTemplate]);
    }

    public function destroy(SmsTemplate $smsTemplate): JsonResponse
    {
        $smsTemplate->delete();

        return response()->json(null, 204);
    }

    public function preview(Request $request, SmsTemplate $smsTemplate): JsonResponse
    {
        $sampleData = $request->input('sample_data', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company' => 'Acme Inc',
            'email' => 'john@example.com',
        ]);

        $rendered = $smsTemplate->render($sampleData);

        return response()->json([
            'data' => [
                'original' => $smsTemplate->content,
                'rendered' => $rendered,
                'character_count' => strlen($rendered),
                'segment_count' => SmsTemplate::calculateSegments($rendered),
                'merge_fields' => $smsTemplate->merge_fields,
            ],
        ]);
    }

    public function duplicate(SmsTemplate $smsTemplate): JsonResponse
    {
        $newTemplate = $smsTemplate->replicate();
        $newTemplate->name = $smsTemplate->name . ' (Copy)';
        $newTemplate->created_by = auth()->id();
        $newTemplate->usage_count = 0;
        $newTemplate->last_used_at = null;
        $newTemplate->save();

        return response()->json(['data' => $newTemplate], 201);
    }
}
