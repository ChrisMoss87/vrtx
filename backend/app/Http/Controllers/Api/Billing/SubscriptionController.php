<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantSubscription;
use App\Services\PluginLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function __construct(
        private PluginLicenseService $licenseService
    ) {}

    /**
     * Get current subscription
     */
    public function show(): JsonResponse
    {
        $subscription = $this->licenseService->getSubscription();

        if (!$subscription) {
            // Return default free plan info
            return response()->json([
                'plan' => TenantSubscription::PLAN_FREE,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'billing_cycle' => TenantSubscription::CYCLE_MONTHLY,
                'user_count' => 1,
                'price_per_user' => 0,
                'is_free' => true,
            ]);
        }

        return response()->json($subscription);
    }

    /**
     * Get available plans
     */
    public function plans(): JsonResponse
    {
        $currentPlan = $this->licenseService->getCurrentPlan();

        $plans = [
            [
                'slug' => TenantSubscription::PLAN_FREE,
                'name' => 'Free',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'user_limit' => 3,
                'features' => [
                    'Up to 3 users',
                    '500 records',
                    '1GB storage',
                    'Basic modules & views',
                    'Community support',
                ],
                'is_current' => $currentPlan === TenantSubscription::PLAN_FREE,
            ],
            [
                'slug' => TenantSubscription::PLAN_STARTER,
                'name' => 'Starter',
                'price_monthly' => 15,
                'price_yearly' => 144, // 20% discount
                'user_limit' => null,
                'features' => [
                    'Everything in Free',
                    '10,000 records',
                    '5GB storage',
                    'Email support',
                    '1 workflow',
                    '1 blueprint',
                ],
                'is_current' => $currentPlan === TenantSubscription::PLAN_STARTER,
            ],
            [
                'slug' => TenantSubscription::PLAN_PROFESSIONAL,
                'name' => 'Professional',
                'price_monthly' => 45,
                'price_yearly' => 432, // 20% discount
                'user_limit' => null,
                'popular' => true,
                'features' => [
                    'Everything in Starter',
                    '100,000 records',
                    '25GB storage',
                    'Priority support',
                    '10 workflows',
                    '5 blueprints',
                    'Basic forecasting',
                    'Web forms (3)',
                ],
                'is_current' => $currentPlan === TenantSubscription::PLAN_PROFESSIONAL,
            ],
            [
                'slug' => TenantSubscription::PLAN_BUSINESS,
                'name' => 'Business',
                'price_monthly' => 85,
                'price_yearly' => 816, // 20% discount
                'user_limit' => null,
                'features' => [
                    'Everything in Professional',
                    'Unlimited records',
                    '100GB storage',
                    'Phone support',
                    'Unlimited workflows',
                    'Unlimited blueprints',
                    'Quotes & Invoices',
                    'Duplicate detection',
                    'Deal rotting alerts',
                ],
                'is_current' => $currentPlan === TenantSubscription::PLAN_BUSINESS,
            ],
            [
                'slug' => TenantSubscription::PLAN_ENTERPRISE,
                'name' => 'Enterprise',
                'price_monthly' => 150,
                'price_yearly' => 1440, // 20% discount
                'user_limit' => null,
                'features' => [
                    'Everything in Business',
                    'Unlimited storage',
                    'Dedicated support',
                    'Custom SLAs',
                    'White-label options',
                    'Time Machine',
                    'Scenario Planner',
                    'Revenue Graph',
                ],
                'is_current' => $currentPlan === TenantSubscription::PLAN_ENTERPRISE,
            ],
        ];

        return response()->json([
            'plans' => $plans,
            'current_plan' => $currentPlan,
        ]);
    }

    /**
     * Update subscription (stub for Stripe integration)
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:free,starter,professional,business,enterprise',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'user_count' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = $request->input('plan');
        $billingCycle = $request->input('billing_cycle', TenantSubscription::CYCLE_MONTHLY);
        $userCount = $request->input('user_count', 1);

        // Get pricing
        $pricePerUser = TenantSubscription::PLAN_PRICING[$plan] ?? 0;
        if ($billingCycle === TenantSubscription::CYCLE_YEARLY) {
            $pricePerUser = $pricePerUser * 0.8; // 20% discount for yearly
        }

        // In production, this would create/update a Stripe subscription
        // For now, we'll update directly (for development/testing)
        $subscription = TenantSubscription::first();

        if ($subscription) {
            $subscription->update([
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
                'user_count' => $userCount,
                'price_per_user' => $pricePerUser,
                'current_period_start' => now(),
                'current_period_end' => $billingCycle === TenantSubscription::CYCLE_YEARLY
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);
        } else {
            $subscription = TenantSubscription::create([
                'plan' => $plan,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'billing_cycle' => $billingCycle,
                'user_count' => $userCount,
                'price_per_user' => $pricePerUser,
                'current_period_start' => now(),
                'current_period_end' => $billingCycle === TenantSubscription::CYCLE_YEARLY
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);
        }

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(): JsonResponse
    {
        $subscription = TenantSubscription::first();

        if (!$subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        if ($subscription->plan === TenantSubscription::PLAN_FREE) {
            return response()->json(['error' => 'Cannot cancel free plan'], 400);
        }

        // In production, this would cancel the Stripe subscription
        $subscription->update([
            'status' => TenantSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->licenseService->clearCache();

        return response()->json([
            'message' => 'Subscription cancelled. Access continues until end of billing period.',
            'access_until' => $subscription->current_period_end,
        ]);
    }
}
