<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Application\Services\Billing\BillingApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\Billing\PdfGeneratorService;
use App\Services\Billing\QuoteService; // @deprecated - use BillingApplicationService
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQuoteController extends Controller
{
    public function __construct(
        protected BillingApplicationService $billingService,
        protected QuoteService $quoteService, // @deprecated - use BillingApplicationService
        protected PdfGeneratorService $pdfService
    ) {}

    /**
     * View a quote by its public token.
     */
    public function show(string $token): JsonResponse
    {
        $quote = Quote::where('view_token', $token)
            ->with(['lineItems.product', 'createdBy:id,name,email', 'assignedTo:id,name,email'])
            ->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found',
            ], 404);
        }

        // Mark as viewed
        $quote->markAsViewed();

        // Check if expired
        if ($quote->isExpired() && !in_array($quote->status, [Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED, Quote::STATUS_EXPIRED])) {
            $quote->update(['status' => Quote::STATUS_EXPIRED]);
        }

        return response()->json([
            'data' => [
                'quote_number' => $quote->quote_number,
                'title' => $quote->title,
                'status' => $quote->status,
                'valid_until' => $quote->valid_until?->toDateString(),
                'is_expired' => $quote->isExpired(),
                'can_accept' => $quote->canBeAccepted(),
                'currency' => $quote->currency,
                'line_items' => $quote->lineItems->map(fn($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate' => $item->tax_rate,
                    'line_total' => $item->line_total,
                ]),
                'subtotal' => $quote->subtotal,
                'discount_amount' => $quote->discount_amount,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
                'terms' => $quote->terms,
                'notes' => $quote->notes,
                'created_by' => $quote->createdBy ? [
                    'name' => $quote->createdBy->name,
                    'email' => $quote->createdBy->email,
                ] : null,
                'created_at' => $quote->created_at->toIso8601String(),
                'accepted_at' => $quote->accepted_at?->toIso8601String(),
                'accepted_by' => $quote->accepted_by,
            ],
        ]);
    }

    /**
     * Accept a quote.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $quote = Quote::where('view_token', $token)->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found',
            ], 404);
        }

        if (!$quote->canBeAccepted()) {
            return response()->json([
                'message' => $quote->isExpired()
                    ? 'This quote has expired and can no longer be accepted.'
                    : 'This quote cannot be accepted.',
            ], 422);
        }

        $validated = $request->validate([
            'accepted_by' => 'required|string|max:255',
            'signature' => 'nullable|string', // Base64 image
        ]);

        $this->quoteService->accept(
            $quote,
            $validated['accepted_by'],
            $validated['signature'] ?? null,
            $request->ip()
        );

        return response()->json([
            'message' => 'Quote accepted successfully',
            'data' => [
                'status' => $quote->status,
                'accepted_at' => $quote->accepted_at->toIso8601String(),
                'accepted_by' => $quote->accepted_by,
            ],
        ]);
    }

    /**
     * Reject a quote.
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $quote = Quote::where('view_token', $token)->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found',
            ], 404);
        }

        if (!$quote->canBeAccepted()) {
            return response()->json([
                'message' => 'This quote cannot be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'rejected_by' => 'required|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        $this->quoteService->reject(
            $quote,
            $validated['rejected_by'],
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Quote rejected',
            'data' => [
                'status' => $quote->status,
                'rejected_at' => $quote->rejected_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get PDF data for a quote.
     */
    public function pdf(string $token): JsonResponse
    {
        $quote = Quote::where('view_token', $token)->first();

        if (!$quote) {
            return response()->json([
                'message' => 'Quote not found',
            ], 404);
        }

        $data = $this->pdfService->generateQuotePdf($quote);

        return response()->json([
            'data' => $data,
        ]);
    }
}
