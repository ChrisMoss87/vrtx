<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashboard_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('general');
            $table->string('thumbnail')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::create('dashboard_template_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('dashboard_templates')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->jsonb('config')->default('{}');
            $table->jsonb('grid_position')->default('{"x": 0, "y": 0, "w": 6, "h": 4}');
            $table->integer('refresh_interval')->default(0);
            $table->timestamps();

            $table->index('template_id');
        });

        // Seed default templates
        $this->seedDefaultTemplates();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_template_widgets');
        Schema::dropIfExists('dashboard_templates');
    }

    /**
     * Seed default dashboard templates.
     */
    private function seedDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'Sales Overview',
                'slug' => 'sales-overview',
                'description' => 'Track key sales metrics including revenue, deals, and pipeline performance.',
                'category' => 'sales',
                'sort_order' => 1,
                'widgets' => [
                    [
                        'title' => 'Total Revenue',
                        'type' => 'goal_kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'sum', 'field' => 'amount'],
                        'grid_position' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Deals Won',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'count', 'filters' => [['field' => 'stage', 'operator' => '=', 'value' => 'won']]],
                        'grid_position' => ['x' => 3, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Conversion Rate',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'avg', 'field' => 'conversion_rate'],
                        'grid_position' => ['x' => 6, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Avg Deal Size',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'avg', 'field' => 'amount'],
                        'grid_position' => ['x' => 9, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Sales Pipeline',
                        'type' => 'funnel',
                        'config' => ['module_id' => null, 'stage_field' => 'stage', 'value_field' => 'amount'],
                        'grid_position' => ['x' => 0, 'y' => 2, 'w' => 6, 'h' => 4],
                    ],
                    [
                        'title' => 'Revenue Trend',
                        'type' => 'chart',
                        'config' => ['chart_type' => 'line', 'module_id' => null, 'aggregation' => 'sum', 'field' => 'amount', 'group_by' => 'month'],
                        'grid_position' => ['x' => 6, 'y' => 2, 'w' => 6, 'h' => 4],
                    ],
                    [
                        'title' => 'Top Sales Reps',
                        'type' => 'leaderboard',
                        'config' => ['module_id' => null, 'rank_field' => 'amount', 'limit' => 5],
                        'grid_position' => ['x' => 0, 'y' => 6, 'w' => 4, 'h' => 5],
                    ],
                    [
                        'title' => 'Recent Deals',
                        'type' => 'recent_records',
                        'config' => ['module_id' => null, 'limit' => 10],
                        'grid_position' => ['x' => 4, 'y' => 6, 'w' => 4, 'h' => 5],
                    ],
                    [
                        'title' => 'Activity Feed',
                        'type' => 'activity',
                        'config' => ['limit' => 10],
                        'grid_position' => ['x' => 8, 'y' => 6, 'w' => 4, 'h' => 5],
                    ],
                ],
            ],
            [
                'name' => 'Marketing Dashboard',
                'slug' => 'marketing-dashboard',
                'description' => 'Monitor marketing campaigns, lead generation, and engagement metrics.',
                'category' => 'marketing',
                'sort_order' => 2,
                'widgets' => [
                    [
                        'title' => 'New Leads',
                        'type' => 'goal_kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'count'],
                        'grid_position' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Qualified Leads',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'count', 'filters' => [['field' => 'status', 'operator' => '=', 'value' => 'qualified']]],
                        'grid_position' => ['x' => 3, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Lead Conversion',
                        'type' => 'progress',
                        'config' => ['current_value' => 0, 'goal_value' => 100],
                        'grid_position' => ['x' => 6, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Campaign ROI',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'avg', 'field' => 'roi'],
                        'grid_position' => ['x' => 9, 'y' => 0, 'w' => 3, 'h' => 2],
                    ],
                    [
                        'title' => 'Lead Sources',
                        'type' => 'chart',
                        'config' => ['chart_type' => 'pie', 'module_id' => null, 'group_by' => 'source'],
                        'grid_position' => ['x' => 0, 'y' => 2, 'w' => 4, 'h' => 4],
                    ],
                    [
                        'title' => 'Lead Funnel',
                        'type' => 'funnel',
                        'config' => ['module_id' => null, 'stage_field' => 'status'],
                        'grid_position' => ['x' => 4, 'y' => 2, 'w' => 4, 'h' => 4],
                    ],
                    [
                        'title' => 'Leads Over Time',
                        'type' => 'chart',
                        'config' => ['chart_type' => 'area', 'module_id' => null, 'aggregation' => 'count', 'group_by' => 'week'],
                        'grid_position' => ['x' => 8, 'y' => 2, 'w' => 4, 'h' => 4],
                    ],
                    [
                        'title' => 'Recent Leads',
                        'type' => 'recent_records',
                        'config' => ['module_id' => null, 'limit' => 8],
                        'grid_position' => ['x' => 0, 'y' => 6, 'w' => 6, 'h' => 5],
                    ],
                    [
                        'title' => 'My Tasks',
                        'type' => 'tasks',
                        'config' => ['limit' => 5],
                        'grid_position' => ['x' => 6, 'y' => 6, 'w' => 6, 'h' => 5],
                    ],
                ],
            ],
            [
                'name' => 'Executive Summary',
                'slug' => 'executive-summary',
                'description' => 'High-level overview of business performance for leadership.',
                'category' => 'executive',
                'sort_order' => 3,
                'widgets' => [
                    [
                        'title' => 'Total Revenue',
                        'type' => 'goal_kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'sum', 'field' => 'amount'],
                        'grid_position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2],
                    ],
                    [
                        'title' => 'New Customers',
                        'type' => 'goal_kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'count'],
                        'grid_position' => ['x' => 4, 'y' => 0, 'w' => 4, 'h' => 2],
                    ],
                    [
                        'title' => 'Pipeline Value',
                        'type' => 'kpi',
                        'config' => ['module_id' => null, 'aggregation' => 'sum', 'field' => 'amount'],
                        'grid_position' => ['x' => 8, 'y' => 0, 'w' => 4, 'h' => 2],
                    ],
                    [
                        'title' => 'Revenue by Month',
                        'type' => 'chart',
                        'config' => ['chart_type' => 'bar', 'module_id' => null, 'aggregation' => 'sum', 'field' => 'amount', 'group_by' => 'month'],
                        'grid_position' => ['x' => 0, 'y' => 2, 'w' => 8, 'h' => 4],
                    ],
                    [
                        'title' => 'Win Rate',
                        'type' => 'progress',
                        'config' => ['current_value' => 0, 'goal_value' => 100, 'label' => 'Target: 50%'],
                        'grid_position' => ['x' => 8, 'y' => 2, 'w' => 4, 'h' => 2],
                    ],
                    [
                        'title' => 'Deals by Stage',
                        'type' => 'chart',
                        'config' => ['chart_type' => 'doughnut', 'module_id' => null, 'group_by' => 'stage'],
                        'grid_position' => ['x' => 8, 'y' => 4, 'w' => 4, 'h' => 3],
                    ],
                    [
                        'title' => 'Top Performers',
                        'type' => 'leaderboard',
                        'config' => ['module_id' => null, 'rank_field' => 'amount', 'limit' => 5],
                        'grid_position' => ['x' => 0, 'y' => 6, 'w' => 4, 'h' => 5],
                    ],
                    [
                        'title' => 'Activity by Day',
                        'type' => 'heatmap',
                        'config' => ['x_field' => 'day_of_week', 'y_field' => 'hour'],
                        'grid_position' => ['x' => 4, 'y' => 6, 'w' => 8, 'h' => 5],
                    ],
                ],
            ],
            [
                'name' => 'Blank Dashboard',
                'slug' => 'blank',
                'description' => 'Start with a clean slate and add your own widgets.',
                'category' => 'general',
                'sort_order' => 99,
                'widgets' => [],
            ],
        ];

        foreach ($templates as $templateData) {
            $widgets = $templateData['widgets'];
            unset($templateData['widgets']);

            $templateData['settings'] = json_encode([]);
            $templateData['created_at'] = now();
            $templateData['updated_at'] = now();

            $templateId = \DB::table('dashboard_templates')->insertGetId($templateData);

            foreach ($widgets as $widget) {
                \DB::table('dashboard_template_widgets')->insert([
                    'template_id' => $templateId,
                    'title' => $widget['title'],
                    'type' => $widget['type'],
                    'config' => json_encode($widget['config']),
                    'grid_position' => json_encode($widget['grid_position']),
                    'refresh_interval' => $widget['refresh_interval'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
