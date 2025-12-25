<?php

namespace App\Http\Controllers\Api\Sms;

use App\Application\Services\Sms\SmsApplicationService;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsMessageController extends Controller
{
    public function __construct(
        protected SmsApplicationService $smsApplicationService,
        protected SmsService $smsService,
        protected SmsMessageRepositoryInterface $messageRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->filled('connection_id')) {
            $filters['connection_id'] = $request->connection_id;
        }

        if ($request->filled('direction')) {
            $filters['direction'] = $request->direction;
        }

        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }

        if ($request->filled('phone')) {
            $filters['phone'] = $request->phone;
        }

        if ($request->filled('module_api_name') && $request->filled('module_record_id')) {
            $filters['module_api_name'] = $request->module_api_name;
            $filters['module_record_id'] = $request->module_record_id;
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        if ($request->filled('from_date')) {
            $filters['from_date'] = $request->from_date;
        }

        if ($request->filled('to_date')) {
            $filters['to_date'] = $request->to_date;
        }

        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);

        $result = $this->messageRepository->listMessages($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'total' => $result->total(),
            'last_page' => $result->lastPage(),
        ]);
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

        $connection = $this->messageRepository->findConnectionById($validated['connection_id']);
        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        $template = null;
        $content = $validated['content'] ?? null;

        if (isset($validated['template_id'])) {
            $template = $this->messageRepository->findTemplateById($validated['template_id']);
            if ($template) {
                $content = $template['content'];
            }
        }

        $message = $this->smsService->sendMessage(
            connection: (object) $connection,
            to: $validated['to'],
            content: $content,
            template: $template ? (object) $template : null,
            mergeData: $validated['merge_data'] ?? null,
            recordId: $validated['module_record_id'] ?? null,
            moduleApiName: $validated['module_api_name'] ?? null
        );

        return response()->json(['data' => $message], 201);
    }

    public function show(int $id): JsonResponse
    {
        $message = $this->messageRepository->findByIdAsArray($id);

        if (!$message) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        return response()->json(['data' => $message]);
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

        $messages = $this->messageRepository->getRecordMessages(
            $validated['module_api_name'],
            $validated['module_record_id'],
            100
        );

        return response()->json(['data' => $messages]);
    }
}
