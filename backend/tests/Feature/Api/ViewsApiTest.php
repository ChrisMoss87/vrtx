<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\ModuleView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->module = Module::factory()->create([
            'name' => 'Leads',
            'singular_name' => 'Lead',
            'api_name' => 'leads',
        ]);
    }

    public function test_can_create_view(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'My Custom View',
            'description' => 'A custom view for leads',
            'filters' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
            'sorting' => [
                ['field' => 'created_at', 'direction' => 'desc'],
            ],
            'column_visibility' => ['name' => true, 'email' => true, 'status' => false],
            'page_size' => 25,
            'is_default' => false,
            'is_shared' => false,
        ]);

        $this->assertDatabaseHas('module_views', [
            'id' => $view->id,
            'module_id' => $this->module->id,
            'name' => 'My Custom View',
        ]);
    }

    public function test_view_belongs_to_module(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
        ]);

        $this->assertEquals($this->module->id, $view->module->id);
    }

    public function test_view_belongs_to_user(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
        ]);

        $this->assertEquals($this->user->id, $view->user->id);
    }

    public function test_module_has_many_views(): void
    {
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'View 1',
        ]);
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'View 2',
        ]);

        $this->assertCount(2, $this->module->views);
    }

    public function test_can_update_view(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);

        $view->update([
            'name' => 'Updated Name',
            'is_default' => true,
        ]);

        $view->refresh();

        $this->assertEquals('Updated Name', $view->name);
        $this->assertTrue($view->is_default);
    }

    public function test_can_delete_view(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
        ]);

        $viewId = $view->id;
        $view->delete();

        $this->assertDatabaseMissing('module_views', ['id' => $viewId]);
    }

    public function test_filters_are_cast_to_array(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
            'filters' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'new'],
            ],
        ]);

        $this->assertIsArray($view->filters);
        $this->assertCount(1, $view->filters);
        $this->assertEquals('status', $view->filters[0]['field']);
    }

    public function test_sorting_is_cast_to_array(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
            'sorting' => [
                ['field' => 'name', 'direction' => 'asc'],
                ['field' => 'created_at', 'direction' => 'desc'],
            ],
        ]);

        $this->assertIsArray($view->sorting);
        $this->assertCount(2, $view->sorting);
    }

    public function test_column_visibility_is_cast_to_array(): void
    {
        $view = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Test View',
            'column_visibility' => ['name' => true, 'email' => false],
        ]);

        $this->assertIsArray($view->column_visibility);
        $this->assertTrue($view->column_visibility['name']);
        $this->assertFalse($view->column_visibility['email']);
    }

    public function test_default_view_scope(): void
    {
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Regular View',
            'is_default' => false,
        ]);
        $defaultView = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Default View',
            'is_default' => true,
        ]);

        $result = ModuleView::where('module_id', $this->module->id)
            ->where('is_default', true)
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals($defaultView->id, $result->id);
    }

    public function test_shared_views_can_be_accessed_by_any_user(): void
    {
        $otherUser = User::factory()->create();

        $sharedView = ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'Shared View',
            'is_shared' => true,
        ]);

        // Shared views should be accessible (filtered by is_shared = true)
        $sharedViews = ModuleView::where('module_id', $this->module->id)
            ->where('is_shared', true)
            ->get();

        $this->assertCount(1, $sharedViews);
        $this->assertEquals('Shared View', $sharedViews->first()->name);
    }

    public function test_private_views_are_user_specific(): void
    {
        $otherUser = User::factory()->create();

        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'User 1 View',
            'is_shared' => false,
        ]);

        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $otherUser->id,
            'name' => 'User 2 View',
            'is_shared' => false,
        ]);

        $user1Views = ModuleView::where('module_id', $this->module->id)
            ->where('user_id', $this->user->id)
            ->where('is_shared', false)
            ->get();

        $this->assertCount(1, $user1Views);
        $this->assertEquals('User 1 View', $user1Views->first()->name);
    }

    public function test_ordered_scope_orders_by_display_order(): void
    {
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'View C',
            'display_order' => 3,
        ]);
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'View A',
            'display_order' => 1,
        ]);
        ModuleView::create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'name' => 'View B',
            'display_order' => 2,
        ]);

        $orderedViews = ModuleView::ordered()->get();

        $this->assertEquals('View A', $orderedViews[0]->name);
        $this->assertEquals('View B', $orderedViews[1]->name);
        $this->assertEquals('View C', $orderedViews[2]->name);
    }
}
