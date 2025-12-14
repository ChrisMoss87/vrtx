<?php

namespace App\Http\Controllers\Api\Portal;

use App\Application\Services\Portal\PortalApplicationService;
use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Models\PortalNotification;
use App\Models\PortalDocumentShare;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller
{
    public function __construct(
        private PortalService $portalService,
        private PortalApplicationService $appService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');

        $stats = $this->portalService->getDashboardStats($user);
        $announcements = $this->portalService->getActiveAnnouncements($user->account_id);
        $recentActivity = $this->portalService->getActivityLog($user, 10);
        $unreadNotifications = $this->portalService->getUnreadNotifications($user);

        return response()->json([
            'stats' => $stats,
            'announcements' => $announcements,
            'recent_activity' => $recentActivity,
            'unread_notifications_count' => $unreadNotifications->count(),
        ]);
    }

    public function deals(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $deals = $this->portalService->getDealsForUser($user);

        return response()->json([
            'deals' => $deals->map(fn($deal) => [
                'id' => $deal->id,
                'name' => $deal->data['name'] ?? 'Unnamed Deal',
                'stage' => $deal->data['stage'] ?? null,
                'amount' => $deal->data['amount'] ?? 0,
                'expected_close_date' => $deal->data['expected_close_date'] ?? null,
                'owner' => $deal->owner?->name,
                'created_at' => $deal->created_at,
                'updated_at' => $deal->updated_at,
            ]),
        ]);
    }

    public function deal(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $deals = $this->portalService->getDealsForUser($user);
        $deal = $deals->firstWhere('id', $id);

        if (!$deal) {
            return response()->json(['message' => 'Deal not found'], 404);
        }

        $this->portalService->logActivity(
            $user,
            'view_deal',
            'deal',
            $id,
            [],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'deal' => [
                'id' => $deal->id,
                'name' => $deal->data['name'] ?? 'Unnamed Deal',
                'stage' => $deal->data['stage'] ?? null,
                'amount' => $deal->data['amount'] ?? 0,
                'expected_close_date' => $deal->data['expected_close_date'] ?? null,
                'description' => $deal->data['description'] ?? null,
                'owner' => $deal->owner?->name,
                'created_at' => $deal->created_at,
                'updated_at' => $deal->updated_at,
            ],
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $invoices = $this->portalService->getInvoicesForUser($user);

        return response()->json([
            'invoices' => $invoices->map(fn($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'subtotal' => $invoice->subtotal,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'balance_due' => $invoice->balance_due,
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'created_at' => $invoice->created_at,
            ]),
        ]);
    }

    public function invoice(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $invoices = $this->portalService->getInvoicesForUser($user);
        $invoice = $invoices->firstWhere('id', $id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        $this->portalService->logActivity(
            $user,
            'view_invoice',
            'invoice',
            $id,
            [],
            $request->ip(),
            $request->userAgent()
        );

        $invoice->load('lineItems');

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'tax' => $invoice->tax,
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'balance_due' => $invoice->balance_due,
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'line_items' => $invoice->lineItems->map(fn($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                ]),
                'created_at' => $invoice->created_at,
            ],
        ]);
    }

    public function quotes(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $quotes = $this->portalService->getQuotesForUser($user);

        return response()->json([
            'quotes' => $quotes->map(fn($quote) => [
                'id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'status' => $quote->status,
                'subtotal' => $quote->subtotal,
                'tax' => $quote->tax,
                'total' => $quote->total,
                'valid_until' => $quote->valid_until,
                'created_at' => $quote->created_at,
            ]),
        ]);
    }

    public function quote(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $quotes = $this->portalService->getQuotesForUser($user);
        $quote = $quotes->firstWhere('id', $id);

        if (!$quote) {
            return response()->json(['message' => 'Quote not found'], 404);
        }

        $this->portalService->logActivity(
            $user,
            'view_quote',
            'quote',
            $id,
            [],
            $request->ip(),
            $request->userAgent()
        );

        $quote->load('lineItems');

        return response()->json([
            'quote' => [
                'id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'status' => $quote->status,
                'subtotal' => $quote->subtotal,
                'discount' => $quote->discount,
                'tax' => $quote->tax,
                'total' => $quote->total,
                'valid_until' => $quote->valid_until,
                'notes' => $quote->notes,
                'terms' => $quote->terms,
                'line_items' => $quote->lineItems->map(fn($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                ]),
                'created_at' => $quote->created_at,
            ],
        ]);
    }

    public function acceptQuote(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $quotes = $this->portalService->getQuotesForUser($user);
        $quote = $quotes->firstWhere('id', $id);

        if (!$quote) {
            return response()->json(['message' => 'Quote not found'], 404);
        }

        if ($quote->status !== 'sent') {
            return response()->json(['message' => 'Quote cannot be accepted'], 400);
        }

        $quote->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->portalService->logActivity(
            $user,
            'accept_quote',
            'quote',
            $id,
            [],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'message' => 'Quote accepted successfully',
            'quote' => $quote->fresh(),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $documents = $this->portalService->getDocumentsForUser($user);

        return response()->json([
            'documents' => $documents->map(fn($doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_id' => $doc->document_id,
                'can_download' => $doc->can_download,
                'requires_signature' => $doc->requires_signature,
                'signed_at' => $doc->signed_at,
                'view_count' => $doc->view_count,
                'first_viewed_at' => $doc->first_viewed_at,
                'expires_at' => $doc->expires_at,
                'created_at' => $doc->created_at,
            ]),
        ]);
    }

    public function viewDocument(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $documents = $this->portalService->getDocumentsForUser($user);
        $document = $documents->firstWhere('id', $id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if ($document->isExpired()) {
            return response()->json(['message' => 'Document has expired'], 410);
        }

        $document->recordView();

        $this->portalService->logActivity(
            $user,
            'view_document',
            'document',
            $id,
            ['document_type' => $document->document_type],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'document' => $document,
        ]);
    }

    public function signDocument(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $documents = $this->portalService->getDocumentsForUser($user);
        $document = $documents->firstWhere('id', $id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$document->requires_signature) {
            return response()->json(['message' => 'Document does not require signature'], 400);
        }

        if ($document->isSigned()) {
            return response()->json(['message' => 'Document already signed'], 400);
        }

        $document->sign($request->ip());

        $this->portalService->logActivity(
            $user,
            'sign_document',
            'document',
            $id,
            ['document_type' => $document->document_type],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'message' => 'Document signed successfully',
            'document' => $document->fresh(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $this->portalService->markNotificationRead($notification);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $count = $this->portalService->markAllNotificationsRead($user);

        return response()->json([
            'message' => 'All notifications marked as read',
            'count' => $count,
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $announcements = $this->portalService->getActiveAnnouncements($user->account_id);

        return response()->json([
            'announcements' => $announcements,
        ]);
    }

    public function activityLog(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $limit = min($request->input('limit', 50), 100);

        $activity = $this->portalService->getActivityLog($user, $limit);

        return response()->json([
            'activity' => $activity,
        ]);
    }
}
