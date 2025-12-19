<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardTemplateRepositoryInterface;
use App\Domain\Shared\ValueObjects\UserId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardTemplateController extends Controller
{
    public function __construct(
        protected DashboardTemplateRepositoryInterface $templateRepository,
        protected DashboardRepositoryInterface $dashboardRepository
    ) {}

    /**
     * List all active dashboard templates.
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');

        if ($category) {
            $templates = $this->templateRepository->findByCategory($category);
        } else {
            $templates = $this->templateRepository->findAllActive();
        }

        return response()->json([
            'data' => array_map(fn($t) => $this->formatTemplate($t), $templates),
        ]);
    }

    /**
     * Get template categories.
     */
    public function categories(): JsonResponse
    {
        $categories = $this->templateRepository->getCategories();

        // Format categories with labels
        $formatted = array_map(fn($cat) => [
            'value' => $cat,
            'label' => ucfirst($cat),
        ], $categories);

        return response()->json([
            'data' => $formatted,
        ]);
    }

    /**
     * Get a single template.
     */
    public function show(int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json([
            'data' => $this->formatTemplate($template, true),
        ]);
    }

    /**
     * Create a dashboard from a template.
     */
    public function createDashboard(Request $request, int $id): JsonResponse
    {
        $template = $this->templateRepository->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Create dashboard from template
        $dashboard = $template->createDashboard(
            name: $validated['name'],
            description: $validated['description'] ?? null
        );

        // Set user
        $dashboard = \App\Domain\Reporting\Entities\Dashboard::create(
            name: $validated['name'],
            userId: new UserId(Auth::id()),
            description: $validated['description'] ?? $template->description(),
        );

        // Save dashboard first
        $dashboard = $this->dashboardRepository->save($dashboard);

        // Create widgets from template
        $templateWidgets = $template->widgets();
        foreach ($templateWidgets as $templateWidget) {
            $widget = $templateWidget->toDashboardWidget($dashboard->getId());
            $dashboard->addWidget($widget);
        }

        // Save again with widgets
        $dashboard = $this->dashboardRepository->save($dashboard);

        return response()->json([
            'message' => 'Dashboard created from template',
            'data' => [
                'id' => $dashboard->getId(),
                'name' => $dashboard->name(),
                'description' => $dashboard->description(),
                'widgets_count' => count($dashboard->widgets()),
            ],
        ], 201);
    }

    /**
     * Format template for API response.
     */
    private function formatTemplate($template, bool $includeWidgets = false): array
    {
        $data = [
            'id' => $template->getId(),
            'name' => $template->name(),
            'slug' => $template->slug(),
            'description' => $template->description(),
            'category' => $template->category(),
            'thumbnail' => $template->thumbnail(),
            'is_active' => $template->isActive(),
            'sort_order' => $template->sortOrder(),
            'widgets_count' => count($template->widgets()),
            'created_at' => $template->createdAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $template->updatedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($includeWidgets) {
            $data['widgets'] = array_map(fn($w) => [
                'id' => $w->getId(),
                'title' => $w->title(),
                'type' => $w->type()->value,
                'config' => $w->config(),
                'grid_position' => $w->gridPosition(),
                'refresh_interval' => $w->refreshInterval(),
            ], $template->widgets());
        }

        return $data;
    }
}
