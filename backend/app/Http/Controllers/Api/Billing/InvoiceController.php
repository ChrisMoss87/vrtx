<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected PdfGeneratorService $pdfService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['lineItems', 'payments', 'createdBy', 'quote']);

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

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        $invoices = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'payment_terms' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'template_id' => 'nullable|exists:quote_templates,id',
            'line_items' => 'nullable|array',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $invoice = $this->invoiceService->create($validated, auth()->id());

        return response()->json([
            'data' => $invoice,
            'message' => 'Invoice created successfully',
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['lineItems.product', 'payments.createdBy', 'template', 'createdBy', 'quote']);

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$invoice->isEditable()) {
            return response()->json([
                'message' => 'This invoice can no longer be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'payment_terms' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'template_id' => 'nullable|exists:quote_templates,id',
            'line_items' => 'nullable|array',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $invoice = $this->invoiceService->update($invoice, $validated);

        return response()->json([
            'data' => $invoice,
            'message' => 'Invoice updated successfully',
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isEditable()) {
            return response()->json([
                'message' => 'Cannot delete an invoice that has been sent or has payments.',
            ], 422);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email',
            'message' => 'nullable|string',
        ]);

        if (!$invoice->canBeSent()) {
            return response()->json([
                'message' => 'This invoice cannot be sent.',
            ], 422);
        }

        $this->invoiceService->send($invoice, $validated['to_email'], $validated['message'] ?? null);

        return response()->json([
            'data' => $invoice->fresh(),
            'message' => 'Invoice sent successfully',
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if (!$invoice->canRecordPayment()) {
            return response()->json([
                'message' => 'Cannot record payment for this invoice.',
            ], 422);
        }

        $payment = $this->invoiceService->recordPayment($invoice, $validated, auth()->id());

        return response()->json([
            'data' => $payment,
            'invoice' => $invoice->fresh(['payments']),
            'message' => 'Payment recorded successfully',
        ], 201);
    }

    public function deletePayment(Invoice $invoice, int $paymentId): JsonResponse
    {
        $payment = $invoice->payments()->findOrFail($paymentId);
        $payment->delete();

        return response()->json([
            'invoice' => $invoice->fresh(['payments']),
            'message' => 'Payment deleted successfully',
        ]);
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        try {
            $this->invoiceService->cancel($invoice);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $invoice->fresh(),
            'message' => 'Invoice cancelled successfully',
        ]);
    }

    public function pdf(Invoice $invoice): JsonResponse
    {
        $data = $this->pdfService->generateInvoicePdf($invoice);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        return $this->pdfService->streamInvoicePdf($invoice);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->invoiceService->getStats();

        return response()->json([
            'stats' => $stats,
        ]);
    }
}
