<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Signature;

use App\Application\Services\Document\DocumentApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Signature\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicSignatureController extends Controller
{
    public function __construct(
        protected SignatureService $service,
        protected DocumentApplicationService $appService
    ) {}

    public function show(string $uuid, Request $request): JsonResponse
    {
        $signatureRequest = DB::table('signature_requests')->where('uuid', $uuid)
            ->with(['signers', 'fields'])
            ->firstOrFail();

        // Validate token
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Access token required'], 401);
        }

        $signer = $this->service->getSignerByToken($token);

        if (!$signer || $signer->request_id !== $signatureRequest->id) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        // Mark as viewed
        $this->service->viewDocument($signer);

        return response()->json([
            'request' => $signatureRequest,
            'signer' => $signer->load('fields'),
            'can_sign' => $signer->canSign(),
        ]);
    }

    public function sign(string $uuid, Request $request): JsonResponse
    {
        $signatureRequest = DB::table('signature_requests')->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'token' => 'required|string',
            'fields' => 'required|array',
        ]);

        $signer = $this->service->getSignerByToken($validated['token']);

        if (!$signer || $signer->request_id !== $signatureRequest->id) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        try {
            $this->service->sign($signer, $validated['fields']);

            return response()->json([
                'message' => 'Document signed successfully',
                'status' => $signatureRequest->fresh()->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function decline(string $uuid, Request $request): JsonResponse
    {
        $signatureRequest = DB::table('signature_requests')->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'token' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $signer = $this->service->getSignerByToken($validated['token']);

        if (!$signer || $signer->request_id !== $signatureRequest->id) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        $this->service->decline($signer, $validated['reason']);

        return response()->json(['message' => 'Signature declined']);
    }

    public function downloadDocument(string $uuid, Request $request): JsonResponse
    {
        $signatureRequest = DB::table('signature_requests')->where('uuid', $uuid)->firstOrFail();

        $token = $request->query('token');
        $signer = $this->service->getSignerByToken($token);

        if (!$signer || $signer->request_id !== $signatureRequest->id) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        $fileUrl = $signatureRequest->status === SignatureRequest::STATUS_COMPLETED
            ? $signatureRequest->signed_file_url
            : $signatureRequest->file_url;

        return response()->json(['url' => $fileUrl]);
    }
}
