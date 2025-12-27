<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\PluginLicenseService;
use App\Services\TenantSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Handle incoming Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // Verify webhook signature in production
        if ($endpointSecret && $sigHeader) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::warning('Stripe webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        } else {
            // For development/testing without signature verification
            $event = json_decode($payload, false);
            if (!$event || !isset($event->type)) {
                return response()->json(['error' => 'Invalid payload'], 400);
            }
        }

        $eventType = $event->type ?? null;
        $eventData = $event->data->object ?? null;

        if (!$eventType || !$eventData) {
            return response()->json(['error' => 'Invalid event structure'], 400);
        }

        Log::info('Stripe webhook received', ['type' => $eventType, 'id' => $event->id ?? null]);

        return match ($eventType) {
            'customer.subscription.created' => $this->handleSubscriptionCreated($eventData),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($eventData),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($eventData),
            'customer.subscription.paused' => $this->handleSubscriptionPaused($eventData),
            'customer.subscription.resumed' => $this->handleSubscriptionResumed($eventData),
            'customer.subscription.trial_will_end' => $this->handleTrialWillEnd($eventData),
            'invoice.paid' => $this->handleInvoicePaid($eventData),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($eventData),
            default => response()->json(['received' => true]),
        };
    }

    /**
     * Handle subscription created event.
     */
    private function handleSubscriptionCreated(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromMetadata($subscription);
        if (!$tenantId) {
            Log::warning('Stripe subscription created without tenant_id metadata', [
                'subscription_id' => $subscription->id,
            ]);
            return response()->json(['received' => true, 'skipped' => 'no_tenant_id']);
        }

        $this->runInTenantContext($tenantId, function () use ($subscription) {
            $plan = $this->getPlanFromSubscription($subscription);
            $status = $this->mapStripeStatus($subscription->status);
            $billingCycle = $this->getBillingCycle($subscription);

            DB::table('tenant_subscriptions')->updateOrInsert(
                [],
                [
                    'plan' => $plan,
                    'status' => $status,
                    'billing_cycle' => $billingCycle,
                    'user_count' => $subscription->quantity ?? 1,
                    'external_subscription_id' => $subscription->id,
                    'external_customer_id' => $subscription->customer,
                    'trial_ends_at' => $subscription->trial_end
                        ? date('Y-m-d H:i:s', $subscription->trial_end)
                        : null,
                    'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                    'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->licenseService->clearCache();
        });

        Log::info('Subscription created', [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscription->id,
        ]);

        return response()->json(['received' => true, 'processed' => true]);
    }

    /**
     * Handle subscription updated event.
     */
    private function handleSubscriptionUpdated(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromMetadata($subscription);
        if (!$tenantId) {
            // Try to find tenant by external_id
            $tenantId = $this->getTenantIdFromExternalId($subscription->id);
        }

        if (!$tenantId) {
            Log::warning('Stripe subscription updated but tenant not found', [
                'subscription_id' => $subscription->id,
            ]);
            return response()->json(['received' => true, 'skipped' => 'tenant_not_found']);
        }

        $this->runInTenantContext($tenantId, function () use ($subscription) {
            $plan = $this->getPlanFromSubscription($subscription);
            $status = $this->mapStripeStatus($subscription->status);
            $billingCycle = $this->getBillingCycle($subscription);

            DB::table('tenant_subscriptions')
                ->where('external_subscription_id', $subscription->id)
                ->update([
                    'plan' => $plan,
                    'status' => $status,
                    'billing_cycle' => $billingCycle,
                    'user_count' => $subscription->quantity ?? 1,
                    'trial_ends_at' => $subscription->trial_end
                        ? date('Y-m-d H:i:s', $subscription->trial_end)
                        : null,
                    'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
                    'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
                    'updated_at' => now(),
                ]);

            $this->licenseService->clearCache();
        });

        Log::info('Subscription updated', [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        return response()->json(['received' => true, 'processed' => true]);
    }

    /**
     * Handle subscription deleted event.
     */
    private function handleSubscriptionDeleted(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromExternalId($subscription->id);

        if (!$tenantId) {
            Log::warning('Stripe subscription deleted but tenant not found', [
                'subscription_id' => $subscription->id,
            ]);
            return response()->json(['received' => true, 'skipped' => 'tenant_not_found']);
        }

        $this->runInTenantContext($tenantId, function () use ($subscription) {
            DB::table('tenant_subscriptions')
                ->where('external_subscription_id', $subscription->id)
                ->update([
                    'plan' => TenantSubscription::PLAN_FREE,
                    'status' => TenantSubscription::STATUS_CANCELED,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            // Deactivate paid plugin licenses
            DB::table('plugin_licenses')
                ->where('status', 'active')
                ->whereNotNull('external_subscription_item_id')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->licenseService->clearCache();
        });

        Log::info('Subscription deleted', [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscription->id,
        ]);

        return response()->json(['received' => true, 'processed' => true]);
    }

    /**
     * Handle subscription paused event.
     */
    private function handleSubscriptionPaused(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromExternalId($subscription->id);

        if ($tenantId) {
            $this->runInTenantContext($tenantId, function () use ($subscription) {
                DB::table('tenant_subscriptions')
                    ->where('external_subscription_id', $subscription->id)
                    ->update([
                        'status' => 'paused',
                        'updated_at' => now(),
                    ]);

                $this->licenseService->clearCache();
            });
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle subscription resumed event.
     */
    private function handleSubscriptionResumed(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromExternalId($subscription->id);

        if ($tenantId) {
            $this->runInTenantContext($tenantId, function () use ($subscription) {
                $status = $this->mapStripeStatus($subscription->status);

                DB::table('tenant_subscriptions')
                    ->where('external_subscription_id', $subscription->id)
                    ->update([
                        'status' => $status,
                        'updated_at' => now(),
                    ]);

                $this->licenseService->clearCache();
            });
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle trial will end event.
     */
    private function handleTrialWillEnd(object $subscription): JsonResponse
    {
        $tenantId = $this->getTenantIdFromExternalId($subscription->id);

        if ($tenantId) {
            Log::info('Trial ending soon', [
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
                'trial_end' => $subscription->trial_end,
            ]);

            // TODO: Send email notification to tenant admins
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle invoice paid event.
     */
    private function handleInvoicePaid(object $invoice): JsonResponse
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (!$subscriptionId) {
            return response()->json(['received' => true, 'skipped' => 'no_subscription']);
        }

        $tenantId = $this->getTenantIdFromExternalId($subscriptionId);

        if ($tenantId) {
            $this->runInTenantContext($tenantId, function () use ($subscriptionId) {
                // Ensure subscription is active after successful payment
                DB::table('tenant_subscriptions')
                    ->where('external_subscription_id', $subscriptionId)
                    ->where('status', TenantSubscription::STATUS_PAST_DUE)
                    ->update([
                        'status' => TenantSubscription::STATUS_ACTIVE,
                        'updated_at' => now(),
                    ]);

                $this->licenseService->clearCache();
            });

            Log::info('Invoice paid', [
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle invoice payment failed event.
     */
    private function handleInvoicePaymentFailed(object $invoice): JsonResponse
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (!$subscriptionId) {
            return response()->json(['received' => true, 'skipped' => 'no_subscription']);
        }

        $tenantId = $this->getTenantIdFromExternalId($subscriptionId);

        if ($tenantId) {
            $this->runInTenantContext($tenantId, function () use ($subscriptionId) {
                DB::table('tenant_subscriptions')
                    ->where('external_subscription_id', $subscriptionId)
                    ->update([
                        'status' => TenantSubscription::STATUS_PAST_DUE,
                        'updated_at' => now(),
                    ]);

                $this->licenseService->clearCache();
            });

            Log::warning('Invoice payment failed', [
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
            ]);

            // TODO: Send email notification to tenant admins
        }

        return response()->json(['received' => true]);
    }

    /**
     * Get tenant ID from subscription metadata.
     */
    private function getTenantIdFromMetadata(object $subscription): ?string
    {
        return $subscription->metadata->tenant_id ?? null;
    }

    /**
     * Get tenant ID from external subscription ID.
     */
    private function getTenantIdFromExternalId(string $externalId): ?string
    {
        // First check if we have a stripe_subscription_id in central tenants table
        $result = DB::connection('central')
            ->table('tenants')
            ->where('stripe_subscription_id', $externalId)
            ->value('id');

        if ($result) {
            return $result;
        }

        // Alternative: Check metadata stored during subscription creation
        // This searches all tenant databases (expensive, use sparingly)
        $tenants = DB::connection('central')
            ->table('tenants')
            ->whereNotNull('id')
            ->pluck('id');

        foreach ($tenants as $tenantId) {
            try {
                $exists = DB::connection("tenant_{$tenantId}")
                    ->table('tenant_subscriptions')
                    ->where('external_subscription_id', $externalId)
                    ->exists();

                if ($exists) {
                    return $tenantId;
                }
            } catch (\Exception $e) {
                // Skip tenants with database issues
                continue;
            }
        }

        return null;
    }

    /**
     * Run a callback in tenant context.
     */
    private function runInTenantContext(string $tenantId, callable $callback): void
    {
        // This uses the tenancy package to switch database connection
        $tenant = \App\Infrastructure\Tenancy\TenantManager::findById($tenantId);

        if ($tenant) {
            \App\Infrastructure\Tenancy\TenantManager::initialize($tenant);
            try {
                $callback();
            } finally {
                \App\Infrastructure\Tenancy\TenantManager::end();
            }
        }
    }

    /**
     * Get plan from subscription items.
     */
    private function getPlanFromSubscription(object $subscription): string
    {
        $priceId = $subscription->items->data[0]->price->id ?? null;

        if (!$priceId) {
            return TenantSubscription::PLAN_FREE;
        }

        // Map Stripe price IDs to plans
        $pricePlanMap = [
            config('services.stripe.prices.starter_monthly') => TenantSubscription::PLAN_STARTER,
            config('services.stripe.prices.starter_yearly') => TenantSubscription::PLAN_STARTER,
            config('services.stripe.prices.professional_monthly') => TenantSubscription::PLAN_PROFESSIONAL,
            config('services.stripe.prices.professional_yearly') => TenantSubscription::PLAN_PROFESSIONAL,
            config('services.stripe.prices.business_monthly') => TenantSubscription::PLAN_BUSINESS,
            config('services.stripe.prices.business_yearly') => TenantSubscription::PLAN_BUSINESS,
            config('services.stripe.prices.enterprise_monthly') => TenantSubscription::PLAN_ENTERPRISE,
            config('services.stripe.prices.enterprise_yearly') => TenantSubscription::PLAN_ENTERPRISE,
        ];

        return $pricePlanMap[$priceId] ?? TenantSubscription::PLAN_FREE;
    }

    /**
     * Map Stripe subscription status to internal status.
     */
    private function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'active' => TenantSubscription::STATUS_ACTIVE,
            'trialing' => TenantSubscription::STATUS_TRIALING,
            'past_due' => TenantSubscription::STATUS_PAST_DUE,
            'canceled', 'cancelled' => TenantSubscription::STATUS_CANCELED,
            'unpaid', 'incomplete_expired' => TenantSubscription::STATUS_EXPIRED,
            default => TenantSubscription::STATUS_ACTIVE,
        };
    }

    /**
     * Get billing cycle from subscription.
     */
    private function getBillingCycle(object $subscription): string
    {
        $interval = $subscription->items->data[0]->price->recurring->interval ?? 'month';

        return $interval === 'year'
            ? TenantSubscription::CYCLE_YEARLY
            : TenantSubscription::CYCLE_MONTHLY;
    }
}
