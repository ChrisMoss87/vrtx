<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Plugin;

use App\Domain\Plugin\Entities\Plugin as PluginEntity;
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbPluginRepository implements PluginRepositoryInterface
{
    private const TABLE_PLUGINS = 'plugins';
    private const TABLE_PLUGIN_BUNDLES = 'plugin_bundles';
    private const TABLE_PLUGIN_LICENSES = 'plugin_licenses';
    private const TABLE_PLUGIN_USAGE = 'plugin_usage';

    // Plugin constants
    private const TIER_PROFESSIONAL = 'professional';
    private const PRICING_PER_USER = 'per_user';

    // License status constants
    private const STATUS_ACTIVE = 'active';
    private const STATUS_EXPIRED = 'expired';
    private const STATUS_CANCELLED = 'cancelled';

    // =========================================================================
    // BASIC CRUD
    // =========================================================================

    public function findById(int $id): ?PluginEntity
    {
        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->first();

        return $plugin ? $this->toDomainEntity($plugin) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->first();

        return $plugin ? $this->toArray($plugin) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('slug', $slug)
            ->first();

        return $plugin ? $this->toArray($plugin) : null;
    }

    public function findAll(): array
    {
        $plugins = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->get();

        return array_map(fn($plugin) => $this->toArray($plugin), $plugins->all());
    }

    public function save(PluginEntity $entity): PluginEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->where('id', $entity->getId())
                ->update($data);

            $plugin = DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->where('id', $entity->getId())
                ->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $id = DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->insertGetId($data);

            $plugin = DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->where('id', $id)
                ->first();
        }

        return $this->toDomainEntity($plugin);
    }

    public function delete(int $id): bool
    {
        $deleted = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    // =========================================================================
    // PLUGIN CATALOG QUERIES
    // =========================================================================

    public function listPlugins(array $filters = []): array
    {
        $query = DB::connection('central')->table(self::TABLE_PLUGINS);

        if (!empty($filters['active'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $plugins = $query->orderBy('display_order')->orderBy('name')->get();

        return array_map(fn($plugin) => $this->toArray($plugin), $plugins->all());
    }

    public function getPluginsByCategory(string $category): array
    {
        $plugins = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return array_map(fn($plugin) => $this->toArray($plugin), $plugins->all());
    }

    public function getAvailablePlugins(): array
    {
        $plugins = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return array_map(fn($plugin) => $this->toArray($plugin), $plugins->all());
    }

    // =========================================================================
    // PLUGIN BUNDLE QUERIES
    // =========================================================================

    public function listBundles(array $filters = []): array
    {
        $query = DB::connection('central')->table(self::TABLE_PLUGIN_BUNDLES);

        if (!empty($filters['active'])) {
            $query->where('is_active', true);
        }

        $bundles = $query->orderBy('display_order')->orderBy('name')->get();

        return array_map(fn($bundle) => $this->toArray($bundle), $bundles->all());
    }

    public function findBundleById(int $id): ?array
    {
        $bundle = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('id', $id)
            ->first();

        return $bundle ? $this->toArray($bundle) : null;
    }

    public function findBundleBySlug(string $slug): ?array
    {
        $bundle = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('slug', $slug)
            ->first();

        return $bundle ? $this->toArray($bundle) : null;
    }

    public function getActiveBundles(): array
    {
        $bundles = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return array_map(fn($bundle) => $this->toArray($bundle), $bundles->all());
    }

    // =========================================================================
    // PLUGIN LICENSE QUERIES (TENANT DB)
    // =========================================================================

    public function listLicenses(array $filters = []): array
    {
        $query = DB::table(self::TABLE_PLUGIN_LICENSES);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['active'])) {
            $query->where('status', self::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        if (!empty($filters['plugin_slug'])) {
            $query->where('plugin_slug', $filters['plugin_slug']);
        }

        $licenses = $query->orderBy('created_at', 'desc')->get();

        return array_map(fn($license) => $this->toArray($license), $licenses->all());
    }

    public function findLicenseById(int $id): ?array
    {
        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->first();

        return $license ? $this->toArray($license) : null;
    }

    public function getLicenseForPlugin(string $pluginSlug): ?array
    {
        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('plugin_slug', $pluginSlug)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $license ? $this->toArray($license) : null;
    }

    public function getActiveLicenses(): array
    {
        $licenses = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        return array_map(fn($license) => $this->toArray($license), $licenses->all());
    }

    public function getInstalledPlugins(): array
    {
        $licenses = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $result = [];
        foreach ($licenses as $license) {
            $plugin = DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->where('slug', $license->plugin_slug)
                ->first();

            if ($plugin) {
                $pluginData = $this->toArray($plugin);
                $pluginData['license_id'] = $license->id;
                $pluginData['license_status'] = $license->status;
                $pluginData['license_expires_at'] = $license->expires_at;
                $result[] = $pluginData;
            }
        }

        return $result;
    }

    public function isPluginInstalled(string $pluginSlug): bool
    {
        return DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('plugin_slug', $pluginSlug)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    // =========================================================================
    // PLUGIN USAGE QUERIES (TENANT DB)
    // =========================================================================

    public function getPluginUsage(string $pluginSlug, string $metric): ?array
    {
        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        return $usage ? $this->mapUsageToArray($usage) : null;
    }

    public function getCurrentUsage(string $pluginSlug, string $metric, string $period = 'monthly'): int
    {
        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        return $usage ? (int) $usage->quantity : 0;
    }

    public function getPluginUsageMetrics(string $pluginSlug): array
    {
        $usages = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        return array_map(fn($usage) => $this->mapUsageToArray($usage), $usages->all());
    }

    public function listPluginUsage(): array
    {
        $usages = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        return array_map(fn($usage) => $this->mapUsageToArray($usage), $usages->all());
    }

    public function isUsageLimitReached(string $pluginSlug, string $metric): bool
    {
        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        if (!$usage || $usage->limit_quantity === null) {
            return false;
        }

        return $usage->quantity >= $usage->limit_quantity;
    }

    // =========================================================================
    // PLUGIN CATALOG COMMANDS
    // =========================================================================

    public function createPlugin(array $data): array
    {
        $insertData = [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'tier' => $data['tier'] ?? self::TIER_PROFESSIONAL,
            'pricing_model' => $data['pricing_model'] ?? self::PRICING_PER_USER,
            'price_monthly' => $data['price_monthly'] ?? null,
            'price_yearly' => $data['price_yearly'] ?? null,
            'features' => json_encode($data['features'] ?? []),
            'requirements' => json_encode($data['requirements'] ?? []),
            'limits' => json_encode($data['limits'] ?? []),
            'icon' => $data['icon'] ?? null,
            'display_order' => $data['display_order'] ?? 999,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->insertGetId($insertData);

        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->first();

        return $this->toArray($plugin);
    }

    public function updatePlugin(int $id, array $data): array
    {
        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['category'])) $updateData['category'] = $data['category'];
        if (isset($data['tier'])) $updateData['tier'] = $data['tier'];
        if (isset($data['pricing_model'])) $updateData['pricing_model'] = $data['pricing_model'];
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['price_yearly'])) $updateData['price_yearly'] = $data['price_yearly'];
        if (isset($data['features'])) $updateData['features'] = json_encode($data['features']);
        if (isset($data['requirements'])) $updateData['requirements'] = json_encode($data['requirements']);
        if (isset($data['limits'])) $updateData['limits'] = json_encode($data['limits']);
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['display_order'])) $updateData['display_order'] = $data['display_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::connection('central')
                ->table(self::TABLE_PLUGINS)
                ->where('id', $id)
                ->update($updateData);
        }

        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->first();

        return $this->toArray($plugin);
    }

    public function deletePlugin(int $id): bool
    {
        $deleted = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    // =========================================================================
    // PLUGIN BUNDLE COMMANDS
    // =========================================================================

    public function createBundle(array $data): array
    {
        $insertData = [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plugins' => json_encode($data['plugins']),
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'discount_percent' => $data['discount_percent'] ?? null,
            'icon' => $data['icon'] ?? null,
            'display_order' => $data['display_order'] ?? 999,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->insertGetId($insertData);

        $bundle = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('id', $id)
            ->first();

        return $this->toArray($bundle);
    }

    public function updateBundle(int $id, array $data): array
    {
        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['plugins'])) $updateData['plugins'] = json_encode($data['plugins']);
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['price_yearly'])) $updateData['price_yearly'] = $data['price_yearly'];
        if (isset($data['discount_percent'])) $updateData['discount_percent'] = $data['discount_percent'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['display_order'])) $updateData['display_order'] = $data['display_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::connection('central')
                ->table(self::TABLE_PLUGIN_BUNDLES)
                ->where('id', $id)
                ->update($updateData);
        }

        $bundle = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('id', $id)
            ->first();

        return $this->toArray($bundle);
    }

    public function deleteBundle(int $id): bool
    {
        $deleted = DB::connection('central')
            ->table(self::TABLE_PLUGIN_BUNDLES)
            ->where('id', $id)
            ->delete();

        return $deleted > 0;
    }

    // =========================================================================
    // PLUGIN LICENSE COMMANDS (TENANT DB)
    // =========================================================================

    public function activatePlugin(array $data): array
    {
        $insertData = [
            'plugin_slug' => $data['plugin_slug'],
            'bundle_slug' => $data['bundle_slug'] ?? null,
            'status' => self::STATUS_ACTIVE,
            'pricing_model' => $data['pricing_model'] ?? self::PRICING_PER_USER,
            'quantity' => $data['quantity'] ?? 1,
            'price_monthly' => $data['price_monthly'] ?? null,
            'external_subscription_item_id' => $data['external_subscription_item_id'] ?? null,
            'activated_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->insertGetId($insertData);

        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->first();

        return $this->toArray($license);
    }

    public function updateLicense(int $id, array $data): array
    {
        $updateData = [];

        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['quantity'])) $updateData['quantity'] = $data['quantity'];
        if (isset($data['price_monthly'])) $updateData['price_monthly'] = $data['price_monthly'];
        if (isset($data['external_subscription_item_id'])) $updateData['external_subscription_item_id'] = $data['external_subscription_item_id'];
        if (isset($data['expires_at'])) $updateData['expires_at'] = $data['expires_at'];

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();
            DB::table(self::TABLE_PLUGIN_LICENSES)
                ->where('id', $id)
                ->update($updateData);
        }

        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->first();

        return $this->toArray($license);
    }

    public function deactivatePlugin(string $pluginSlug): bool
    {
        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('plugin_slug', $pluginSlug)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$license) {
            return false;
        }

        DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $license->id)
            ->update([
                'status' => self::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);

        return true;
    }

    public function cancelLicense(int $id): array
    {
        DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);

        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->first();

        return $this->toArray($license);
    }

    public function reactivateLicense(int $id, ?\DateTimeInterface $expiresAt = null): array
    {
        DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->update([
                'status' => self::STATUS_ACTIVE,
                'activated_at' => now(),
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('id', $id)
            ->first();

        return $this->toArray($license);
    }

    // =========================================================================
    // PLUGIN USAGE COMMANDS (TENANT DB)
    // =========================================================================

    public function trackUsage(
        string $pluginSlug,
        string $metric,
        int $amount = 1,
        ?int $limit = null,
        ?float $overageRate = null
    ): array {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        // Try to find existing usage record
        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if (!$usage) {
            // Create new usage record
            $id = DB::table(self::TABLE_PLUGIN_USAGE)
                ->insertGetId([
                    'plugin_slug' => $pluginSlug,
                    'metric' => $metric,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'quantity' => $amount,
                    'limit_quantity' => $limit,
                    'overage_rate' => $overageRate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $usage = DB::table(self::TABLE_PLUGIN_USAGE)
                ->where('id', $id)
                ->first();
        } else {
            // Increment existing usage
            DB::table(self::TABLE_PLUGIN_USAGE)
                ->where('id', $usage->id)
                ->increment('quantity', $amount, ['updated_at' => now()]);

            $usage = DB::table(self::TABLE_PLUGIN_USAGE)
                ->where('id', $usage->id)
                ->first();
        }

        return $this->mapUsageToArray($usage);
    }

    public function resetUsage(string $pluginSlug, string $metric): array
    {
        $insertData = [
            'plugin_slug' => $pluginSlug,
            'metric' => $metric,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table(self::TABLE_PLUGIN_USAGE)
            ->insertGetId($insertData);

        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('id', $id)
            ->first();

        return $this->mapUsageToArray($usage);
    }

    public function updateUsageLimits(
        string $pluginSlug,
        string $metric,
        ?int $limitQuantity,
        ?float $overageRate = null
    ): ?array {
        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('metric', $metric)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->first();

        if (!$usage) {
            return null;
        }

        DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('id', $usage->id)
            ->update([
                'limit_quantity' => $limitQuantity,
                'overage_rate' => $overageRate,
                'updated_at' => now(),
            ]);

        $usage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('id', $usage->id)
            ->first();

        return $this->mapUsageToArray($usage);
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getPluginStats(): array
    {
        $totalPlugins = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('is_active', true)
            ->count();

        $installedCount = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $categoryResults = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->selectRaw('category, COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('category')
            ->get();

        $byCategory = [];
        foreach ($categoryResults as $row) {
            $byCategory[$row->category] = $row->count;
        }

        $tierResults = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->selectRaw('tier, COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('tier')
            ->get();

        $byTier = [];
        foreach ($tierResults as $row) {
            $byTier[$row->tier] = $row->count;
        }

        return [
            'total_available' => $totalPlugins,
            'total_installed' => $installedCount,
            'by_category' => $byCategory,
            'by_tier' => $byTier,
        ];
    }

    public function getLicenseStats(): array
    {
        $total = DB::table(self::TABLE_PLUGIN_LICENSES)->count();

        $active = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $expired = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_EXPIRED)
            ->count();

        $cancelled = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_CANCELLED)
            ->count();

        $expiringSoon = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        $totalSpend = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('price_monthly');

        return [
            'total_licenses' => $total,
            'active' => $active,
            'expired' => $expired,
            'cancelled' => $cancelled,
            'expiring_soon' => $expiringSoon,
            'monthly_spend' => (float) $totalSpend,
        ];
    }

    public function getUsageStats(): array
    {
        $currentUsage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        // Group by plugin_slug
        $byPluginSlug = [];
        foreach ($currentUsage as $usage) {
            if (!isset($byPluginSlug[$usage->plugin_slug])) {
                $byPluginSlug[$usage->plugin_slug] = [];
            }
            $byPluginSlug[$usage->plugin_slug][] = $usage;
        }

        $byPlugin = [];
        foreach ($byPluginSlug as $pluginSlug => $usages) {
            $metrics = [];
            foreach ($usages as $usage) {
                $metrics[$usage->metric] = $this->calculateUsageMetrics($usage);
            }

            $byPlugin[] = [
                'plugin_slug' => $pluginSlug,
                'metrics' => $metrics,
            ];
        }

        $totalOverage = 0;
        $limitReached = 0;

        foreach ($currentUsage as $usage) {
            $metrics = $this->calculateUsageMetrics($usage);
            $totalOverage += $metrics['overage_cost'];
            if ($usage->limit_quantity !== null && $usage->quantity >= $usage->limit_quantity) {
                $limitReached++;
            }
        }

        return [
            'by_plugin' => $byPlugin,
            'total_overage_cost' => (float) $totalOverage,
            'limits_reached' => $limitReached,
        ];
    }

    public function getPluginUsageReport(string $pluginSlug, int $months = 3): array
    {
        $plugin = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('slug', $pluginSlug)
            ->first();

        $license = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('plugin_slug', $pluginSlug)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        $historicalUsage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('period_start', '>=', now()->subMonths($months)->startOfMonth())
            ->orderBy('period_start', 'desc')
            ->get();

        $currentPeriodUsage = DB::table(self::TABLE_PLUGIN_USAGE)
            ->where('plugin_slug', $pluginSlug)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->get();

        $currentPeriod = [];
        foreach ($currentPeriodUsage as $usage) {
            $metrics = $this->calculateUsageMetrics($usage);
            $currentPeriod[] = [
                'metric' => $usage->metric,
                'quantity' => $usage->quantity,
                'limit' => $usage->limit_quantity,
                'remaining' => $metrics['remaining'],
                'usage_percent' => $metrics['usage_percent'],
                'overage' => $metrics['overage'],
                'overage_cost' => $metrics['overage_cost'],
            ];
        }

        // Group historical by period
        $historicalByPeriod = [];
        foreach ($historicalUsage as $usage) {
            $period = date('Y-m', strtotime($usage->period_start));
            if (!isset($historicalByPeriod[$period])) {
                $historicalByPeriod[$period] = [];
            }
            $metrics = $this->calculateUsageMetrics($usage);
            $historicalByPeriod[$period][$usage->metric] = [
                'metric' => $usage->metric,
                'quantity' => $usage->quantity,
                'limit' => $usage->limit_quantity,
                'overage' => $metrics['overage'],
                'overage_cost' => $metrics['overage_cost'],
            ];
        }

        $historical = [];
        foreach ($historicalByPeriod as $period => $metrics) {
            $historical[] = [
                'period' => $period,
                'metrics' => $metrics,
            ];
        }

        return [
            'plugin' => $plugin ? $this->toArray($plugin) : null,
            'license' => $license ? $this->toArray($license) : null,
            'current_period' => $currentPeriod,
            'historical' => $historical,
        ];
    }

    public function getPluginRecommendations(int $limit = 5): array
    {
        $installedSlugs = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->pluck('plugin_slug')
            ->toArray();

        $plugins = DB::connection('central')
            ->table(self::TABLE_PLUGINS)
            ->where('is_active', true)
            ->whereNotIn('slug', $installedSlugs)
            ->orderBy('display_order')
            ->limit($limit)
            ->get();

        return array_map(fn($plugin) => $this->toArray($plugin), $plugins->all());
    }

    public function getExpiringLicenses(int $days = 30): array
    {
        $licenses = DB::table(self::TABLE_PLUGIN_LICENSES)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->get();

        return array_map(fn($license) => $this->toArray($license), $licenses->all());
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function mapUsageToArray(stdClass $usage): array
    {
        $metrics = $this->calculateUsageMetrics($usage);

        return array_merge($this->toArray($usage), $metrics);
    }

    private function calculateUsageMetrics(stdClass $usage): array
    {
        $remaining = null;
        $usagePercent = null;
        $overage = 0;
        $overageCost = 0.0;

        if ($usage->limit_quantity !== null) {
            $remaining = max(0, $usage->limit_quantity - $usage->quantity);
            $overage = max(0, $usage->quantity - $usage->limit_quantity);

            if ($usage->limit_quantity > 0) {
                $usagePercent = min(100, round(($usage->quantity / $usage->limit_quantity) * 100, 1));
            }
        }

        if ($usage->overage_rate !== null && $overage > 0) {
            $overageCost = $overage * (float) $usage->overage_rate;
        }

        return [
            'remaining' => $remaining,
            'usage_percent' => $usagePercent,
            'overage' => $overage,
            'overage_cost' => $overageCost,
        ];
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $data): PluginEntity
    {
        return PluginEntity::reconstitute(
            id: $data->id,
            createdAt: $data->created_at ? new DateTimeImmutable($data->created_at) : null,
            updatedAt: $data->updated_at ? new DateTimeImmutable($data->updated_at) : null,
        );
    }

    private function toModelData(PluginEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    private function toArray(stdClass $obj): array
    {
        $array = (array) $obj;

        // Handle JSON fields - decode if they are strings
        if (isset($array['features']) && is_string($array['features'])) {
            $array['features'] = json_decode($array['features'], true) ?? [];
        }

        if (isset($array['requirements']) && is_string($array['requirements'])) {
            $array['requirements'] = json_decode($array['requirements'], true) ?? [];
        }

        if (isset($array['limits']) && is_string($array['limits'])) {
            $array['limits'] = json_decode($array['limits'], true) ?? [];
        }

        if (isset($array['plugins']) && is_string($array['plugins'])) {
            $array['plugins'] = json_decode($array['plugins'], true) ?? [];
        }

        return $array;
    }
}
