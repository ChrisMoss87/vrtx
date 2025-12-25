<?php

namespace App\Http\Controllers\Api\Sms;

use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SmsOptOutController extends Controller
{
    public function __construct(
        protected SmsMessageRepositoryInterface $messageRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $connectionId = $request->filled('connection_id') ? (int) $request->connection_id : null;
        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);

        $result = $this->messageRepository->listOptOuts($connectionId, $perPage, $page);

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
            'phone_number' => 'required|string|max:20',
            'type' => 'required|in:all,marketing,transactional',
            'reason' => 'nullable|string|max:255',
            'connection_id' => 'nullable|exists:sms_connections,id',
        ]);

        $phoneNumber = SmsOptOut::normalizePhone($validated['phone_number']);

        $optOut = $this->messageRepository->recordOptOut(
            $phoneNumber,
            $validated['connection_id'] ?? null,
            $validated['reason'] ?? 'Manual opt-out'
        );

        return response()->json(['data' => $optOut], 201);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'type' => 'nullable|in:all,marketing,transactional',
            'connection_id' => 'nullable|exists:sms_connections,id',
        ]);

        $phoneNumber = SmsOptOut::normalizePhone($validated['phone_number']);

        $isOptedOut = $this->messageRepository->isOptedOut(
            $phoneNumber,
            $validated['connection_id'] ?? null
        );

        return response()->json([
            'data' => [
                'phone_number' => $validated['phone_number'],
                'is_opted_out' => $isOptedOut,
            ],
        ]);
    }

    public function optIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'type' => 'nullable|in:all,marketing,transactional',
            'connection_id' => 'nullable|exists:sms_connections,id',
        ]);

        $phoneNumber = SmsOptOut::normalizePhone($validated['phone_number']);

        $result = $this->messageRepository->removeOptOut(
            $phoneNumber,
            $validated['connection_id'] ?? null
        );

        return response()->json([
            'data' => [
                'phone_number' => $validated['phone_number'],
                'opted_in' => $result,
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        // Get the opt-out record to extract phone number
        $optOut = \DB::table('sms_opt_outs')->where('id', $id)->first();

        if (!$optOut) {
            return response()->json(['message' => 'Opt-out record not found'], 404);
        }

        $result = $this->messageRepository->removeOptOut(
            $optOut->phone_number,
            $optOut->connection_id
        );

        if (!$result) {
            return response()->json(['message' => 'Failed to remove opt-out'], 500);
        }

        return response()->json(null, 204);
    }

    public function bulkOptOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_numbers' => 'required|array|min:1',
            'phone_numbers.*' => 'string|max:20',
            'type' => 'required|in:all,marketing,transactional',
            'reason' => 'nullable|string|max:255',
            'connection_id' => 'nullable|exists:sms_connections,id',
        ]);

        $count = 0;
        foreach ($validated['phone_numbers'] as $phone) {
            $phoneNumber = SmsOptOut::normalizePhone($phone);

            $this->messageRepository->recordOptOut(
                $phoneNumber,
                $validated['connection_id'] ?? null,
                $validated['reason'] ?? 'Bulk opt-out'
            );
            $count++;
        }

        return response()->json([
            'data' => [
                'processed' => $count,
            ],
        ]);
    }
}
