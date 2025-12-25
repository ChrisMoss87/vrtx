<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Application\Services\Billing\BillingApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Billing\PdfGeneratorService;
use App\Services\Billing\QuoteService; // @deprecated - use BillingApplicationService
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function __construct(
        protected BillingApplicationService $billingService,
        protected QuoteService $quoteService, // @deprecated - use BillingApplicationService
        protected PdfGeneratorService $pdfService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Quote::with(['lineItems', 'createdBy', 'assignedTo']);

        if ($request->has('status')) {
            $query->status($request->status);
        }

        if ($request->has('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        $quotes = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($quotes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'valid_until' => 'nullable|date|after:today',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'template_id' => 'nullable|exists:quote_templates,id',
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'assigned_to' => 'nullable|exists:users,id',
            'line_items' => 'nullable|array',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $quote = $this->quoteService->create($validated, auth()->id());

        return response()->json([
            'data' => $quote,
            'message' => 'Quote created successfully',
        ], 201);
    }

    public function show(Quote $quote): JsonResponse
    {
        $quote->load(['lineItems.product', 'template', 'createdBy', 'assignedTo', 'versions', 'invoice']);

        return response()->json([
            'data' => $quote,
        ]);
    }

    public function update(Request $request, Quote $quote): JsonResponse
    {
        $validated = $request->validate([
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'valid_until' => 'nullable|date',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'template_id' => 'nullable|exists:quote_templates,id',
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'assigned_to' => 'nullable|exists:users,id',
            'line_items' => 'nullable|array',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $quote = $this->quoteService->update($quote, $validated);

        return response()->json([
            'data' => $quote,
            'message' => 'Quote updated successfully',
        ]);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        if (!$quote->isEditable()) {
            return response()->json([
                'message' => 'Cannot delete a quote that has been sent.',
            ], 422);
        }

        $quote->delete();

        return response()->json([
            'message' => 'Quote deleted successfully',
        ]);
    }

    public function send(Request $request, Quote $quote): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email',
            'message' => 'nullable|string',
        ]);

        if (!$quote->canBeSent()) {
            return response()->json([
                'message' => 'This quote cannot be sent.',
            ], 422);
        }

        $this->quoteService->send($quote, $validated['to_email'], $validated['message'] ?? null);

        return response()->json([
            'data' => $quote->fresh(),
            'message' => 'Quote sent successfully',
        ]);
    }

    public function duplicate(Quote $quote): JsonResponse
    {
        $newQuote = $this->quoteService->duplicate($quote, auth()->id());

        return response()->json([
            'data' => $newQuote,
            'message' => 'Quote duplicated successfully',
        ], 201);
    }

    public function pdf(Quote $quote): JsonResponse
    {
        $data = $this->pdfService->generateQuotePdf($quote);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function downloadPdf(Quote $quote)
    {
        return $this->pdfService->streamQuotePdf($quote);
    }

    public function convertToInvoice(Quote $quote): JsonResponse
    {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            return response()->json([
                'message' => 'Only accepted quotes can be converted to invoices.',
            ], 422);
        }

        if ($quote->invoice) {
            return response()->json([
                'message' => 'This quote has already been converted to an invoice.',
                'invoice' => $quote->invoice,
            ], 422);
        }

        $invoiceService = app(\App\Services\Billing\InvoiceService::class);
        $invoice = $invoiceService->createFromQuote($quote, auth()->id());

        return response()->json([
            'data' => $invoice,
            'message' => 'Quote converted to invoice successfully',
        ], 201);
    }

    // Templates
    public function templates(): JsonResponse
    {
        $templates = QuoteTemplate::orderBy('name')->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_default' => 'nullable|boolean',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'styling' => 'nullable|array',
            'company_info' => 'nullable|array',
        ]);

        $template = DB::table('quote_templates')->insertGetId($validated);

        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return response()->json([
            'data' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }

    public function updateTemplate(Request $request, QuoteTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_default' => 'nullable|boolean',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'styling' => 'nullable|array',
            'company_info' => 'nullable|array',
        ]);

        $template->update($validated);

        if ($request->boolean('is_default')) {
            $template->setAsDefault();
        }

        return response()->json([
            'data' => $template,
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroyTemplate(QuoteTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    // Stats
    public function stats(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'total' => DB::table('quotes')->count(),
                'draft' => Quote::draft()->count(),
                'sent' => Quote::sent()->count(),
                'accepted' => Quote::accepted()->count(),
                'rejected' => Quote::status(Quote::STATUS_REJECTED)->count(),
                'expired' => Quote::expired()->count(),
                'total_value' => Quote::accepted()->sum('total'),
                'pending_value' => Quote::whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])->sum('total'),
            ],
        ]);
    }
}
