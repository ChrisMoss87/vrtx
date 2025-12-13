# VRTX CRM Plugin Ecosystem & Pricing Strategy

## Executive Summary

This document outlines a plugin/add-on ecosystem strategy for VRTX CRM, where all plugins are developed in-house initially. The strategy is informed by market research on Salesforce AppExchange (7,000+ apps), HubSpot Marketplace (1,870+ apps), Zoho Marketplace (900+ extensions), and Pipedrive Marketplace (500+ apps).

---

## Plugin Categories (by Market Demand)

Based on AppExchange data, here's how demand breaks down:
- **Sales** (41%) - Deal management, forecasting, quotes
- **IT & Admin** (22%) - Data management, security, backups
- **Collaboration** (10%) - Team communication, deal rooms
- **Marketing** (9%) - Campaigns, forms, landing pages
- **Customer Service** (8%) - Support, chat, ticketing
- **Finance** (6%) - Billing, invoicing, payments

---

## Plugin Tier Structure

### Tier 1: CORE (Included in Base Plan)
Essential features every CRM needs - these drive adoption and stickiness.

| Plugin | Category | Status | Notes |
|--------|----------|--------|-------|
| Modules & Records | Sales | ✅ Built | Foundation |
| DataTable Views | Sales | ✅ Built | Foundation |
| Kanban Views | Sales | ✅ Built | Foundation |
| Basic Reporting | Sales | ✅ Built | Foundation |
| Dashboards | Sales | ✅ Built | Foundation |
| Workflows (Basic) | IT/Admin | ✅ Built | 5 workflow limit |
| Email Integration | Sales | ✅ Built | Gmail/Outlook sync |
| Blueprints (Basic) | Sales | ✅ Built | 3 blueprint limit |
| Activity Tracking | Sales | ✅ Built | Foundation |
| File Storage | IT/Admin | ✅ Built | 5GB limit |
| Import/Export | IT/Admin | ✅ Built | Foundation |
| User Management | IT/Admin | ✅ Built | Foundation |
| API Access (Basic) | IT/Admin | ✅ Built | 1000 calls/day |

**Pricing**: $0 for Free tier, included in all paid plans

---

### Tier 2: PROFESSIONAL ADD-ONS ($10-25/user/month each)
High-demand features that provide clear ROI - charge per user.

| Plugin | Category | Status | Price | Justification |
|--------|----------|--------|-------|---------------|
| **Sales Forecasting** | Sales | ✅ Built | $15/user/mo | High demand, revenue critical |
| **Quotes & Invoices** | Finance | ✅ Built | $20/user/mo | Direct revenue impact |
| **Meeting Scheduler** | Sales | ✅ Built | $10/user/mo | Calendly costs $12-20/user |
| **Duplicate Detection** | IT/Admin | ✅ Built | $10/user/mo | Data quality critical |
| **Deal Rotting Alerts** | Sales | ✅ Built | $8/user/mo | Pipeline health |
| **Goal & Quota Tracking** | Sales | 🔧 WIP | $12/user/mo | Sales management essential |
| **Public Web Forms** | Marketing | ✅ Built | $15/user/mo | Lead generation |
| **Advanced Workflows** | IT/Admin | ✅ Built | $15/user/mo | Unlimited workflows |
| **Advanced Blueprints** | Sales | ✅ Built | $12/user/mo | Unlimited blueprints + SLAs |
| **Custom Reports** | Sales | ✅ Built | $10/user/mo | Advanced analytics |

**Bundle: "Sales Pro Pack"** = Forecasting + Quotes + Scheduler + Rotting
**Bundle Price**: $40/user/mo (vs $53 à la carte) - 25% savings

---

### Tier 3: ADVANCED ADD-ONS ($20-40/user/month each)
Differentiated features that compete with specialized tools.

| Plugin | Category | Status | Price | Competing With |
|--------|----------|--------|-------|----------------|
| **Time Machine** | IT/Admin | ✅ Built | $20/user/mo | Unique differentiator |
| **Scenario Planner** | Sales | ✅ Built | $25/user/mo | Clari, Aviso ($50+) |
| **Revenue Graph** | Sales | ✅ Built | $30/user/mo | Unique differentiator |
| **Deal Rooms** | Collab | ✅ Built | $25/user/mo | DealHub ($40+) |
| **Competitor Battlecards** | Sales | ✅ Built | $20/user/mo | Klue ($35+) |
| **Process Recorder** | IT/Admin | ✅ Built | $15/user/mo | Unique differentiator |
| **Meeting Intelligence** | Sales | 🔧 WIP | $30/user/mo | Gong ($100+), Chorus |

**Bundle: "Revenue Intelligence Pack"** = Time Machine + Scenario + Graph + Battlecards
**Bundle Price**: $75/user/mo (vs $95 à la carte) - 21% savings

---

### Tier 4: COMMUNICATION CHANNELS ($15-30 flat/month per channel)
Charge per channel, not per user - usage-based model makes sense here.

| Plugin | Category | Status | Price | Notes |
|--------|----------|--------|-------|-------|
| **Live Chat Widget** | Service | 🔧 WIP | $25/mo | Per website/domain |
| **WhatsApp Business** | Comm | 🔧 WIP | $30/mo | + Meta message fees |
| **SMS Automation** | Comm | 🔧 WIP | $20/mo | + per-message fees |
| **Team Chat (Slack/Teams)** | Collab | 🔧 WIP | $15/mo | Integration fee |
| **Shared Inbox** | Service | 🔧 WIP | $25/mo | Per inbox |
| **Call Recording** | Sales | 🔧 WIP | $30/mo | + storage fees |
| **Video Conferencing** | Sales | 🔧 WIP | $20/mo | Integration only |

**Bundle: "Omnichannel Pack"** = Chat + WhatsApp + SMS + Shared Inbox
**Bundle Price**: $80/mo (vs $100 à la carte) - 20% savings

---

### Tier 5: MARKETING SUITE ($25-50/user/month)
Full marketing automation competes with HubSpot Marketing Hub.

| Plugin | Category | Status | Price | Competing With |
|--------|----------|--------|-------|----------------|
| **Marketing Campaigns** | Marketing | 🔧 WIP | $35/user/mo | HubSpot, Marketo |
| **Smart Cadences** | Marketing | 🔧 WIP | $30/user/mo | Outreach, Salesloft |
| **Landing Page Builder** | Marketing | Planned | $25/user/mo | Unbounce, Leadpages |
| **A/B Testing** | Marketing | Planned | $20/user/mo | Usually bundled |
| **Email Templates** | Marketing | ✅ Built | $10/user/mo | Basic automation |

**Bundle: "Marketing Hub"** = All 5 marketing plugins
**Bundle Price**: $99/user/mo (vs $120 à la carte) - 17% savings

---

### Tier 6: AI/ML FEATURES ($30-60/user/month)
Premium tier - requires significant compute costs.

| Plugin | Category | Status | Price | Notes |
|--------|----------|--------|-------|-------|
| **AI Email Composition** | Sales | Planned | $30/user/mo | GPT integration |
| **AI Lead Scoring** | Sales | Planned | $40/user/mo | ML model training |
| **Meeting Summarization** | Sales | Planned | $35/user/mo | Transcription + AI |
| **Predictive Analytics** | Sales | Planned | $50/user/mo | Revenue predictions |
| **Sentiment Analysis** | Service | Planned | $25/user/mo | Communication analysis |

**Bundle: "AI Copilot"** = Email + Lead Scoring + Summarization + Sentiment
**Bundle Price**: $99/user/mo (vs $130 à la carte) - 24% savings

---

### Tier 7: DOCUMENT & COMPLIANCE ($10-25/user/month)

| Plugin | Category | Status | Price | Notes |
|--------|----------|--------|-------|-------|
| **Document Templates** | IT/Admin | Planned | $15/user/mo | Doc generation |
| **E-Signatures** | Sales | Planned | $25/user/mo | DocuSign competitor |
| **Proposal Builder** | Sales | Planned | $20/user/mo | PandaDoc competitor |
| **Approval Workflows** | IT/Admin | Planned | $10/user/mo | Internal controls |

**Bundle: "Document Suite"** = All 4 document plugins
**Bundle Price**: $55/user/mo (vs $70 à la carte) - 21% savings

---

### Tier 8: CUSTOMER SUCCESS ($20-35/user/month)

| Plugin | Category | Status | Price | Notes |
|--------|----------|--------|-------|-------|
| **Customer Portal** | Service | Planned | $35/user/mo | Self-service hub |
| **Support Ticketing** | Service | Planned | $25/user/mo | Zendesk competitor |
| **Onboarding Playbooks** | Service | Planned | $20/user/mo | CS workflows |
| **Renewal Management** | Service | Planned | $25/user/mo | Subscription focus |

**Bundle: "Customer Success Hub"** = All 4 plugins
**Bundle Price**: $85/user/mo (vs $105 à la carte) - 19% savings

---

## Recommended Base Plan Pricing

Based on market research, here's a competitive pricing structure:

### Free Tier
- Up to 3 users
- Core features only
- 500 records
- 1GB storage
- Community support

### Starter - $15/user/month
- Core features
- 10,000 records
- 5GB storage
- Email support
- 1 workflow
- 1 blueprint

### Professional - $45/user/month
- Everything in Starter
- 100,000 records
- 25GB storage
- Priority support
- 10 workflows
- 5 blueprints
- API access (5,000 calls/day)
- Custom fields
- **Includes**: Basic Forecasting, Quotes (view only), Web Forms (3)

### Business - $85/user/month
- Everything in Professional
- Unlimited records
- 100GB storage
- Phone support
- Unlimited workflows
- Unlimited blueprints
- API access (25,000 calls/day)
- **Includes**: Full Forecasting, Quotes & Invoices, All Web Forms, Duplicate Detection, Deal Rotting

### Enterprise - $150/user/month
- Everything in Business
- Unlimited storage
- Dedicated support
- Custom SLAs
- White-label options
- API access (unlimited)
- **Includes**: All Professional Add-ons + Time Machine + Scenario Planner

---

## Bundle Strategy Summary

| Bundle Name | Plugins Included | À La Carte | Bundle Price | Savings |
|-------------|------------------|------------|--------------|---------|
| Sales Pro Pack | 4 sales plugins | $53/user/mo | $40/user/mo | 25% |
| Revenue Intelligence | 4 advanced plugins | $95/user/mo | $75/user/mo | 21% |
| Omnichannel | 4 channels | $100/mo | $80/mo | 20% |
| Marketing Hub | 5 marketing plugins | $120/user/mo | $99/user/mo | 17% |
| AI Copilot | 4 AI plugins | $130/user/mo | $99/user/mo | 24% |
| Document Suite | 4 doc plugins | $70/user/mo | $55/user/mo | 21% |
| Customer Success Hub | 4 CS plugins | $105/user/mo | $85/user/mo | 19% |

---

## Competitive Analysis

### vs Salesforce
- **Their pricing**: $165/user/mo Enterprise + $30/user Einstein AI
- **Our advantage**: 40-60% lower, all-in-one without AppExchange dependency
- **Our weakness**: Smaller ecosystem, fewer integrations

### vs HubSpot
- **Their pricing**: $120-150/user/mo Enterprise
- **Our advantage**: More flexible à la carte options, better forecasting
- **Our weakness**: Less marketing automation maturity

### vs Zoho
- **Their pricing**: $40-52/user/mo Ultimate
- **Our advantage**: Better UX, unique differentiators (Time Machine, Scenario)
- **Our weakness**: Zoho has 45+ integrated apps

### vs Pipedrive
- **Their pricing**: $59-99/user/mo
- **Our advantage**: More comprehensive feature set, blueprints
- **Our weakness**: Pipedrive is more focused/simple

---

## Rollout Strategy

### Phase 1: Foundation (Now - Complete)
- Core features included in all plans
- Basic forecasting, quotes, forms in Professional+
- Establish value perception

### Phase 2: Professional Add-ons (Q1)
- Launch individual purchasable plugins
- Start with highest-demand items: Forecasting Pro, Quotes Pro, Scheduler
- Introduce Sales Pro Pack bundle

### Phase 3: Advanced Differentiators (Q2)
- Time Machine, Scenario Planner, Revenue Graph
- Revenue Intelligence Pack bundle
- Position as "Enterprise Intelligence" tier

### Phase 4: Communication Channels (Q2-Q3)
- Live Chat, WhatsApp, SMS
- Omnichannel Pack bundle
- Usage-based pricing model

### Phase 5: Marketing & AI (Q3-Q4)
- Marketing Hub bundle
- AI Copilot bundle
- Compete with HubSpot Marketing Hub

---

## Revenue Projections (Per 100 Customers)

Assuming average company size of 10 users:

| Scenario | Base MRR | Add-on MRR | Total MRR |
|----------|----------|------------|-----------|
| Conservative | $45,000 | $15,000 | $60,000 |
| Moderate | $65,000 | $35,000 | $100,000 |
| Aggressive | $85,000 | $60,000 | $145,000 |

**Key Metrics to Track**:
- Add-on attach rate (target: 40%+)
- Bundle vs à la carte ratio (target: 60% bundles)
- Upgrade path: Free → Starter → Pro → Business

---

## Technical Considerations

### Plugin Architecture
```
app/
├── Plugins/
│   ├── Contracts/
│   │   ├── PluginInterface.php
│   │   └── HasLicenseCheck.php
│   ├── Sales/
│   │   ├── Forecasting/
│   │   ├── Quotes/
│   │   └── Scheduler/
│   ├── Marketing/
│   ├── Communication/
│   └── AI/
├── Services/
│   └── PluginLicenseService.php
└── Middleware/
    └── CheckPluginLicense.php
```

### License Checking
- Check license on every plugin route
- Cache license status (5 minute TTL)
- Graceful degradation for expired licenses
- Usage tracking for metered features

### Feature Flags
- Database-driven feature toggles
- Per-tenant configuration
- A/B testing capability for new features

---

---

## Implementation Details

### Database Schema for Plugin Licensing

```sql
-- Plugin definitions (central database)
CREATE TABLE plugins (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,           -- 'forecasting-pro', 'quotes-invoices'
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,              -- 'sales', 'marketing', 'communication'
    tier VARCHAR(20) NOT NULL,                  -- 'core', 'professional', 'advanced', 'enterprise'
    pricing_model VARCHAR(20) NOT NULL,         -- 'per_user', 'flat', 'usage', 'included'
    price_monthly DECIMAL(10,2),
    price_yearly DECIMAL(10,2),                 -- Annual discount
    features JSONB NOT NULL,                    -- Feature list for display
    requirements JSONB,                         -- Required plugins/plans
    limits JSONB,                               -- Usage limits if applicable
    icon VARCHAR(50),                           -- Icon name
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Bundles (groups of plugins at discount)
CREATE TABLE plugin_bundles (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    plugins JSONB NOT NULL,                     -- Array of plugin slugs
    price_monthly DECIMAL(10,2),
    price_yearly DECIMAL(10,2),
    discount_percent INT,                       -- e.g., 25 for 25% off
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Tenant subscriptions (per-tenant database)
CREATE TABLE tenant_subscriptions (
    id SERIAL PRIMARY KEY,
    plan VARCHAR(50) NOT NULL,                  -- 'free', 'starter', 'professional', 'business', 'enterprise'
    status VARCHAR(20) NOT NULL,                -- 'active', 'past_due', 'cancelled', 'trialing'
    billing_cycle VARCHAR(20) NOT NULL,         -- 'monthly', 'yearly'
    user_count INT NOT NULL,
    price_per_user DECIMAL(10,2),
    external_subscription_id VARCHAR(100),      -- Stripe subscription ID
    external_customer_id VARCHAR(100),          -- Stripe customer ID
    trial_ends_at TIMESTAMP,
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Plugin licenses (per-tenant database)
CREATE TABLE plugin_licenses (
    id SERIAL PRIMARY KEY,
    plugin_slug VARCHAR(50) NOT NULL,
    bundle_slug VARCHAR(50),                    -- If purchased via bundle
    status VARCHAR(20) NOT NULL,                -- 'active', 'expired', 'cancelled'
    pricing_model VARCHAR(20) NOT NULL,
    quantity INT DEFAULT 1,                     -- For per-user: number of seats
    price_monthly DECIMAL(10,2),
    external_subscription_item_id VARCHAR(100), -- Stripe subscription item ID
    activated_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(plugin_slug)
);

-- Usage tracking for metered features
CREATE TABLE plugin_usage (
    id SERIAL PRIMARY KEY,
    plugin_slug VARCHAR(50) NOT NULL,
    metric VARCHAR(50) NOT NULL,                -- 'api_calls', 'storage_mb', 'records', 'messages'
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    quantity BIGINT DEFAULT 0,
    limit_quantity BIGINT,                      -- NULL = unlimited
    overage_rate DECIMAL(10,4),                 -- Price per unit over limit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(plugin_slug, metric, period_start)
);

-- Feature flags for granular control
CREATE TABLE feature_flags (
    id SERIAL PRIMARY KEY,
    feature_key VARCHAR(100) UNIQUE NOT NULL,   -- 'forecasting.ai_predictions'
    plugin_slug VARCHAR(50),                    -- Which plugin enables this
    plan_required VARCHAR(50),                  -- Minimum plan required
    is_enabled BOOLEAN DEFAULT false,           -- Tenant override
    config JSONB,                               -- Feature-specific config
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Plugin License Service

```php
<?php

namespace App\Services;

use App\Models\PluginLicense;
use App\Models\TenantSubscription;
use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

class PluginLicenseService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a plugin is licensed for the current tenant
     */
    public function hasPlugin(string $pluginSlug): bool
    {
        $cacheKey = "plugin_license:{$pluginSlug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($pluginSlug) {
            // Check if included in base plan
            if ($this->isIncludedInPlan($pluginSlug)) {
                return true;
            }

            // Check for active license
            return PluginLicense::where('plugin_slug', $pluginSlug)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->exists();
        });
    }

    /**
     * Check if a feature is enabled
     */
    public function hasFeature(string $featureKey): bool
    {
        $cacheKey = "feature_flag:{$featureKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($featureKey) {
            $flag = FeatureFlag::where('feature_key', $featureKey)->first();

            if (!$flag) {
                return false;
            }

            // Check if tenant has override enabled
            if ($flag->is_enabled) {
                return true;
            }

            // Check if plugin is licensed
            if ($flag->plugin_slug && !$this->hasPlugin($flag->plugin_slug)) {
                return false;
            }

            // Check plan requirement
            if ($flag->plan_required && !$this->hasPlan($flag->plan_required)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Get all licensed plugins
     */
    public function getLicensedPlugins(): array
    {
        $cacheKey = "licensed_plugins";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $subscription = TenantSubscription::first();
            $plan = $subscription?->plan ?? 'free';

            // Get plugins included in plan
            $includedPlugins = $this->getPluginsForPlan($plan);

            // Get additional licensed plugins
            $licensedPlugins = PluginLicense::where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->pluck('plugin_slug')
                ->toArray();

            return array_unique(array_merge($includedPlugins, $licensedPlugins));
        });
    }

    /**
     * Check usage limits for metered features
     */
    public function checkUsageLimit(string $pluginSlug, string $metric): array
    {
        $usage = PluginUsage::where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        if (!$usage) {
            return ['allowed' => true, 'remaining' => null];
        }

        $remaining = $usage->limit_quantity
            ? $usage->limit_quantity - $usage->quantity
            : null;

        return [
            'allowed' => $remaining === null || $remaining > 0,
            'used' => $usage->quantity,
            'limit' => $usage->limit_quantity,
            'remaining' => $remaining,
            'overage_rate' => $usage->overage_rate,
        ];
    }

    /**
     * Increment usage counter
     */
    public function trackUsage(string $pluginSlug, string $metric, int $amount = 1): void
    {
        PluginUsage::updateOrCreate(
            [
                'plugin_slug' => $pluginSlug,
                'metric' => $metric,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
            ],
            []
        )->increment('quantity', $amount);
    }

    /**
     * Clear license cache (call after subscription changes)
     */
    public function clearCache(): void
    {
        Cache::forget('licensed_plugins');
        // Clear all plugin-related cache keys
    }

    private function isIncludedInPlan(string $pluginSlug): bool
    {
        $subscription = TenantSubscription::first();
        $plan = $subscription?->plan ?? 'free';
        $includedPlugins = $this->getPluginsForPlan($plan);

        return in_array($pluginSlug, $includedPlugins);
    }

    private function hasPlan(string $requiredPlan): bool
    {
        $subscription = TenantSubscription::first();
        $currentPlan = $subscription?->plan ?? 'free';

        $planHierarchy = [
            'free' => 0,
            'starter' => 1,
            'professional' => 2,
            'business' => 3,
            'enterprise' => 4
        ];

        return ($planHierarchy[$currentPlan] ?? 0) >= ($planHierarchy[$requiredPlan] ?? 0);
    }

    private function getPluginsForPlan(string $plan): array
    {
        $planPlugins = [
            'free' => [
                'core-modules', 'core-datatable', 'core-kanban',
                'core-dashboards', 'core-workflows-basic',
            ],
            'starter' => [
                'core-reports', 'core-email', 'core-import-export',
            ],
            'professional' => [
                'forecasting-basic', 'quotes-view', 'web-forms-basic', 'blueprints-basic',
            ],
            'business' => [
                'forecasting-pro', 'quotes-invoices', 'duplicate-detection',
                'deal-rotting', 'web-forms-pro', 'workflows-advanced', 'blueprints-pro',
            ],
            'enterprise' => [
                'time-machine', 'scenario-planner', 'revenue-graph',
                'deal-rooms', 'competitor-battlecards', 'api-unlimited',
            ],
        ];

        $plugins = [];
        foreach ($planPlugins as $planName => $planPluginList) {
            $plugins = array_merge($plugins, $planPluginList);
            if ($planName === $plan) break;
        }

        return $plugins;
    }
}
```

### Middleware for Plugin Access Control

```php
<?php

namespace App\Http\Middleware;

use App\Services\PluginLicenseService;
use Closure;
use Illuminate\Http\Request;

class CheckPluginLicense
{
    public function __construct(private PluginLicenseService $licenseService) {}

    public function handle(Request $request, Closure $next, string $pluginSlug)
    {
        if (!$this->licenseService->hasPlugin($pluginSlug)) {
            return response()->json([
                'error' => 'Plugin not licensed',
                'plugin' => $pluginSlug,
                'upgrade_url' => '/settings/billing/plugins',
            ], 403);
        }

        return $next($request);
    }
}

// Usage in routes:
// Route::middleware('plugin:forecasting-pro')->group(function () {
//     Route::get('/forecasts', [ForecastController::class, 'index']);
// });
```

### Frontend License Store

```typescript
// frontend/src/lib/stores/license.ts
import { writable, derived } from 'svelte/store';
import { api } from '$lib/api/client';

interface LicenseState {
    plan: string;
    plugins: string[];
    features: string[];
    usage: Record<string, { used: number; limit: number | null }>;
    loading: boolean;
}

function createLicenseStore() {
    const { subscribe, set, update } = writable<LicenseState>({
        plan: 'free',
        plugins: [],
        features: [],
        usage: {},
        loading: true,
    });

    return {
        subscribe,
        async load() {
            update(s => ({ ...s, loading: true }));
            const response = await api.get('/billing/license');
            set({ ...response.data, loading: false });
        },
        hasPlugin(slug: string): boolean {
            let result = false;
            subscribe(s => { result = s.plugins.includes(slug); })();
            return result;
        },
        hasFeature(key: string): boolean {
            let result = false;
            subscribe(s => { result = s.features.includes(key); })();
            return result;
        },
    };
}

export const license = createLicenseStore();

// Derived stores for common checks
export const isPro = derived(license, $l =>
    ['professional', 'business', 'enterprise'].includes($l.plan)
);

export const isBusiness = derived(license, $l =>
    ['business', 'enterprise'].includes($l.plan)
);

export const isEnterprise = derived(license, $l => $l.plan === 'enterprise');
```

### Plugin Gate Component

```svelte
<!-- frontend/src/lib/components/billing/PluginGate.svelte -->
<script lang="ts">
    import { license } from '$lib/stores/license';
    import { Button } from '$lib/components/ui/button';
    import * as Card from '$lib/components/ui/card';
    import { Lock } from 'lucide-svelte';

    export let plugin: string;
    export let feature: string | undefined = undefined;
    export let showUpgrade: boolean = true;
    export let title: string = 'Premium Feature';

    $: hasAccess = feature
        ? $license.features.includes(feature)
        : $license.plugins.includes(plugin);
</script>

{#if hasAccess}
    <slot />
{:else if showUpgrade}
    <Card.Root class="border-dashed">
        <Card.Content class="flex flex-col items-center justify-center py-10">
            <Lock class="h-12 w-12 text-muted-foreground mb-4" />
            <h3 class="text-lg font-semibold mb-2">{title}</h3>
            <p class="text-muted-foreground text-center mb-4 max-w-md">
                This feature requires the <strong>{plugin}</strong> plugin.
                Upgrade your plan to unlock this functionality.
            </p>
            <Button href="/settings/billing/plugins?highlight={plugin}">
                View Upgrade Options
            </Button>
        </Card.Content>
    </Card.Root>
{/if}
```

---

## Plugin Marketplace Routes

```
/settings/billing                    # Subscription overview
/settings/billing/plans              # Plan comparison & upgrade
/settings/billing/plugins            # Plugin marketplace
/settings/billing/plugins/{slug}     # Plugin details & purchase
/settings/billing/usage              # Usage & limits dashboard
/settings/billing/invoices           # Billing history
```

---

## API Endpoints for Billing

```
# License & Subscription
GET    /api/v1/billing/license           # Get current license state
GET    /api/v1/billing/subscription      # Get subscription details
POST   /api/v1/billing/subscription      # Create/update subscription
DELETE /api/v1/billing/subscription      # Cancel subscription

# Plugins
GET    /api/v1/billing/plugins           # List all available plugins
GET    /api/v1/billing/plugins/{slug}    # Get plugin details
POST   /api/v1/billing/plugins/{slug}    # Purchase/activate plugin
DELETE /api/v1/billing/plugins/{slug}    # Cancel plugin

# Bundles
GET    /api/v1/billing/bundles           # List all bundles
POST   /api/v1/billing/bundles/{slug}    # Purchase bundle

# Usage
GET    /api/v1/billing/usage             # Get usage stats
GET    /api/v1/billing/usage/{metric}    # Get specific metric usage

# Stripe Integration
POST   /api/v1/billing/checkout          # Create Stripe checkout session
POST   /api/v1/billing/portal            # Create Stripe customer portal session
POST   /api/v1/webhooks/stripe           # Handle Stripe webhooks
```

---

## Next Steps

1. **Define Plugin Boundaries**: Clearly separate code that becomes paid add-ons
2. **Build License System**: Implement per-tenant plugin licensing
3. **Create Admin UI**: Plugin marketplace in admin settings
4. **Usage Metering**: Track API calls, storage, records for limits
5. **Billing Integration**: Stripe/Paddle for subscription management

---

## Sources

- [Salesforce AppExchange Statistics 2025](https://www.sfapps.info/salesforce-apps-stats-2025/)
- [HubSpot App Marketplace](https://ecosystem.hubspot.com/marketplace/apps)
- [Zoho Marketplace](https://marketplace.zoho.com/app/crm)
- [Pipedrive Marketplace](https://www.pipedrive.com/en/marketplace)
- [CRM Pricing Guide 2025](https://zeeg.me/en/blog/post/crm-pricing)
- [CRM Pricing Comparison](https://www.engagebay.com/blog/crm-pricing/)
