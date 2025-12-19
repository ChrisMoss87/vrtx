<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsOptOut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmsOptOutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SmsOptOut::with('connection:id,name');

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('search')) {
            $query->where('phone_number', 'like', '%' . $request->search . '%');
        }

        $optOuts = $query->orderBy('opted_out_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($optOuts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'type' => 'required|in:all,marketing,transactional',
            'reason' => 'nullable|string|max:255',
            'connection_id' => 'nullable|exists:sms_connections,id',
        ]);

        $optOut = SmsOptOut::optOut(
            $validated['phone_number'],
            $validated['type'],
            $validated['reason'] ?? 'Manual opt-out',
            $validated['connection_id'] ?? null
        );

        return response()->json(['data' => $optOut], 201);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'type' => 'nullable|in:all,marketing,transactional',
        ]);

        $isOptedOut = SmsOptOut::isOptedOut(
            $validated['phone_number'],
            $validated['type'] ?? 'all'
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
        ]);

        $result = SmsOptOut::optIn(
            $validated['phone_number'],
            $validated['type'] ?? 'all'
        );

        return response()->json([
            'data' => [
                'phone_number' => $validated['phone_number'],
                'opted_in' => $result,
            ],
        ]);
    }

    public function destroy(SmsOptOut $smsOptOut): JsonResponse
    {
        $smsOptOut->delete();

        return response()->json(null, 204);
    }

    public function bulkOptOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_numbers' => 'required|array|min:1',
            'phone_numbers.*' => 'string|max:20',
            'type' => 'required|in:all,marketing,transactional',
            'reason' => 'nullable|string|max:255',
        ]);

        $count = 0;
        foreach ($validated['phone_numbers'] as $phone) {
            SmsOptOut::optOut(
                $phone,
                $validated['type'],
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
