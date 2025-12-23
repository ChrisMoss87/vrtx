<?php

declare(strict_types=1);

namespace Plugins\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\SMS\Application\Services\SMSApplicationService;

class SMSMessageController extends Controller
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
    ) {}

    /**
     * List SMS messages.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'connection_id',
            'direction',
            'status',
            'search',
        ]);

        $perPage = $request->input('per_page', 20);

        $messages = $this->smsService->listMessages($filters, $perPage);

        return response()->json($messages);
    }

    /**
     * Send an SMS message.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|string|max:50',
            'content' => 'required_without:template_id|nullable|string|max:1600',
            'template_id' => 'required_without:content|nullable|integer',
            'merge_data' => 'nullable|array',
            'connection_id' => 'required|integer|exists:sms_connections,id',
            'module_api_name' => 'nullable|string|max:100',
            'record_id' => 'nullable|integer',
        ]);

        if (!empty($validated['template_id'])) {
            $message = $this->smsService->sendTemplateMessage(
                $validated['to'],
                $validated['template_id'],
                $validated['merge_data'] ?? [],
                $validated['connection_id'],
                $validated['module_api_name'] ?? null,
                $validated['record_id'] ?? null
            );
        } else {
            $message = $this->smsService->sendSms(
                $validated['to'],
                $validated['content'],
                $validated['connection_id'],
                $validated['module_api_name'] ?? null,
                $validated['record_id'] ?? null
            );
        }

        return response()->json(['data' => $message], 201);
    }

    /**
     * Get conversation history for a phone number.
     */
    public function conversation(string $phoneNumber, Request $request): JsonResponse
    {
        $limit = $request->input('limit', 100);

        $messages = $this->smsService->getConversation($phoneNumber, $limit);

        return response()->json(['data' => $messages]);
    }

    /**
     * Get a specific message.
     */
    public function show(int $message): JsonResponse
    {
        $data = $this->smsService->getMessage($message);

        if (!$data) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get SMS statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $connectionId = $request->input('connection_id');
        $period = $request->input('period', 'today');

        $stats = $this->smsService->getStats($connectionId, $period);

        return response()->json(['data' => $stats]);
    }

    /**
     * List opt-outs.
     */
    public function listOptOuts(Request $request): JsonResponse
    {
        $connectionId = $request->input('connection_id');
        $perPage = $request->input('per_page', 50);

        $optOuts = $this->smsService->listOptOuts($connectionId, $perPage);

        return response()->json($optOuts);
    }

    /**
     * Record an opt-out.
     */
    public function recordOptOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:50',
            'connection_id' => 'nullable|integer|exists:sms_connections,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $optOut = $this->smsService->recordOptOut(
            $validated['phone_number'],
            $validated['connection_id'] ?? null,
            $validated['reason'] ?? null
        );

        return response()->json(['data' => $optOut], 201);
    }

    /**
     * Remove an opt-out.
     */
    public function removeOptOut(Request $request, string $phoneNumber): JsonResponse
    {
        $connectionId = $request->input('connection_id');

        $removed = $this->smsService->removeOptOut($phoneNumber, $connectionId);

        if (!$removed) {
            return response()->json(['message' => 'Opt-out not found'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * Check opt-out status.
     */
    public function checkOptOut(Request $request, string $phoneNumber): JsonResponse
    {
        $connectionId = $request->input('connection_id');

        $isOptedOut = $this->smsService->isOptedOut($phoneNumber, $connectionId);

        return response()->json([
            'data' => [
                'phone_number' => $phoneNumber,
                'opted_out' => $isOptedOut,
            ],
        ]);
    }
}
