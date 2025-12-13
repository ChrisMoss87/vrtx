<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsConnection;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsMessageController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = SmsMessage::with(['connection:id,name,phone_number', 'template:id,name', 'sender:id,name']);

        if ($request->filled('connection_id')) {
            $query->where('connection_id', $request->connection_id);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('phone')) {
            $query->forPhone($request->phone);
        }

        if ($request->filled('module_api_name') && $request->filled('module_record_id')) {
            $query->forRecord($request->module_api_name, $request->module_record_id);
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|exists:sms_connections,id',
            'to' => 'required|string|max:20',
            'content' => 'required_without:template_id|string|max:1600',
            'template_id' => 'nullable|exists:sms_templates,id',
            'merge_data' => 'nullable|array',
            'module_record_id' => 'nullable|integer',
            'module_api_name' => 'nullable|string',
        ]);

        $connection = SmsConnection::findOrFail($validated['connection_id']);
        $template = isset($validated['template_id'])
            ? SmsTemplate::find($validated['template_id'])
            : null;

        $content = $template
            ? $template->content
            : $validated['content'];

        $message = $this->smsService->sendMessage(
            connection: $connection,
            to: $validated['to'],
            content: $content,
            template: $template,
            mergeData: $validated['merge_data'] ?? null,
            recordId: $validated['module_record_id'] ?? null,
            moduleApiName: $validated['module_api_name'] ?? null
        );

        return response()->json(['data' => $message], 201);
    }

    public function show(SmsMessage $smsMessage): JsonResponse
    {
        $smsMessage->load(['connection:id,name,phone_number', 'template:id,name', 'sender:id,name']);

        return response()->json(['data' => $smsMessage]);
    }

    public function conversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'connection_id' => 'required|exists:sms_connections,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $messages = $this->smsService->getConversation(
            $validated['phone'],
            $validated['connection_id'],
            $validated['limit'] ?? 50
        );

        return response()->json(['data' => $messages]);
    }

    public function forRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_api_name' => 'required|string',
            'module_record_id' => 'required|integer',
        ]);

        $messages = SmsMessage::forRecord($validated['module_api_name'], $validated['module_record_id'])
            ->with(['connection:id,name,phone_number', 'sender:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['data' => $messages]);
    }
}
