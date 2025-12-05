<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Email\EmailAccountController;
use App\Http\Controllers\Api\Email\EmailMessageController;
use App\Http\Controllers\Api\Email\EmailTemplateController;
use App\Http\Controllers\Api\Email\EmailTrackingController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\Modules\ModuleController;
use App\Http\Controllers\Api\Modules\RecordController;
use App\Http\Controllers\Api\Modules\ViewsController;
use App\Http\Controllers\Api\Pipelines\PipelineController;
use App\Http\Controllers\Api\RbacController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\WizardDraftController;
use App\Http\Controllers\Api\Workflows\WorkflowController;
use App\Http\Controllers\Api\Reporting\ReportController;
use App\Http\Controllers\Api\Reporting\DashboardController;
use App\Http\Controllers\Api\DataManagement\ImportController;
use App\Http\Controllers\Api\DataManagement\ExportController;
use App\Http\Controllers\Api\Integration\ApiKeyController;
use App\Http\Controllers\Api\Integration\WebhookController;
use App\Http\Controllers\Api\Integration\IncomingWebhookController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Here you can register API routes for your tenant application.
| These routes are automatically scoped to the current tenant.
|
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Public authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Module Management Routes
        Route::prefix('modules')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::get('/active', [ModuleController::class, 'active']);
            Route::get('/by-api-name/{apiName}', [ModuleController::class, 'showByApiName']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::post('/reorder', [ModuleController::class, 'reorder']);
            Route::get('/{id}', [ModuleController::class, 'show']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::post('/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);
        });

        // Dynamic Module Records Routes
        Route::prefix('records')->group(function () {
            Route::get('/{moduleApiName}', [RecordController::class, 'index']);
            Route::post('/{moduleApiName}', [RecordController::class, 'store']);
            Route::post('/{moduleApiName}/bulk-delete', [RecordController::class, 'bulkDestroy']);
            Route::post('/{moduleApiName}/bulk-update', [RecordController::class, 'bulkUpdate']);
            Route::get('/{moduleApiName}/lookup', [RecordController::class, 'lookup']);
            Route::get('/{moduleApiName}/{recordId}', [RecordController::class, 'show']);
            Route::put('/{moduleApiName}/{recordId}', [RecordController::class, 'update']);
            Route::patch('/{moduleApiName}/{recordId}', [RecordController::class, 'patch']);
            Route::delete('/{moduleApiName}/{recordId}', [RecordController::class, 'destroy']);
        });

        // Module Views Routes
        Route::prefix('views')->group(function () {
            Route::get('/{moduleApiName}', [ViewsController::class, 'index']);
            Route::get('/{moduleApiName}/default', [ViewsController::class, 'getDefaultView']);
            Route::post('/{moduleApiName}', [ViewsController::class, 'store']);
            Route::get('/{moduleApiName}/{viewId}', [ViewsController::class, 'show']);
            Route::put('/{moduleApiName}/{viewId}', [ViewsController::class, 'update']);
            Route::delete('/{moduleApiName}/{viewId}', [ViewsController::class, 'destroy']);
        });

        // Wizard Draft Routes
        Route::prefix('wizard-drafts')->group(function () {
            Route::get('/', [WizardDraftController::class, 'index']);
            Route::post('/', [WizardDraftController::class, 'store']);
            Route::post('/auto-save', [WizardDraftController::class, 'autoSave']);
            Route::post('/bulk-delete', [WizardDraftController::class, 'bulkDestroy']);
            Route::get('/{id}', [WizardDraftController::class, 'show']);
            Route::delete('/{id}', [WizardDraftController::class, 'destroy']);
            Route::patch('/{id}/rename', [WizardDraftController::class, 'rename']);
            Route::post('/{id}/make-permanent', [WizardDraftController::class, 'makePermanent']);
            Route::post('/{id}/extend', [WizardDraftController::class, 'extendExpiration']);
        });

        // File Upload Routes
        Route::prefix('files')->group(function () {
            Route::post('/upload', [FileUploadController::class, 'upload']);
            Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);
            Route::post('/delete', [FileUploadController::class, 'delete']);
            Route::post('/info', [FileUploadController::class, 'info']);
        });

        // User Search (for mentions)
        Route::get('/users/search', [UserSearchController::class, 'search']);

        // Simple upload endpoint at root level
        Route::post('/upload', [FileUploadController::class, 'upload']);
        Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);

        // Pipeline Routes
        Route::prefix('pipelines')->group(function () {
            Route::get('/', [PipelineController::class, 'index']);
            Route::get('/module/{moduleApiName}', [PipelineController::class, 'forModule']);
            Route::post('/sync-all-field-options', [PipelineController::class, 'syncAllFieldOptions']);
            Route::post('/', [PipelineController::class, 'store']);
            Route::get('/{id}', [PipelineController::class, 'show']);
            Route::put('/{id}', [PipelineController::class, 'update']);
            Route::delete('/{id}', [PipelineController::class, 'destroy']);
            Route::get('/{id}/kanban', [PipelineController::class, 'kanbanData']);
            Route::post('/{id}/move-record', [PipelineController::class, 'moveRecord']);
            Route::get('/{id}/record/{recordId}/history', [PipelineController::class, 'recordHistory']);
            Route::post('/{id}/reorder-stages', [PipelineController::class, 'reorderStages']);
            Route::post('/{id}/sync-field-options', [PipelineController::class, 'syncFieldOptions']);
        });

        // Workflow Automation Routes
        Route::prefix('workflows')->group(function () {
            // Meta endpoints
            Route::get('/trigger-types', [WorkflowController::class, 'triggerTypes']);
            Route::get('/action-types', [WorkflowController::class, 'actionTypes']);

            // CRUD operations
            Route::get('/', [WorkflowController::class, 'index']);
            Route::post('/', [WorkflowController::class, 'store']);
            Route::get('/{id}', [WorkflowController::class, 'show']);
            Route::put('/{id}', [WorkflowController::class, 'update']);
            Route::delete('/{id}', [WorkflowController::class, 'destroy']);

            // Workflow actions
            Route::post('/{id}/toggle-active', [WorkflowController::class, 'toggleActive']);
            Route::post('/{id}/clone', [WorkflowController::class, 'clone']);
            Route::post('/{id}/trigger', [WorkflowController::class, 'trigger']);
            Route::post('/{id}/reorder-steps', [WorkflowController::class, 'reorderSteps']);

            // Execution history
            Route::get('/{id}/executions', [WorkflowController::class, 'executions']);
            Route::get('/{id}/executions/{executionId}', [WorkflowController::class, 'showExecution']);
        });

        // Blueprint Routes (Stage Transitions & SLAs)
        Route::prefix('blueprints')->group(function () {
            // Blueprint CRUD
            Route::get('/', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroy']);
            Route::put('/{id}/layout', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateLayout']);
            Route::post('/{id}/toggle-active', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'toggleActive']);
            Route::post('/{id}/sync-states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'syncStates']);

            // State management
            Route::get('/{id}/states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'states']);
            Route::post('/{id}/states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'storeState']);
            Route::put('/{id}/states/{stateId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateState']);
            Route::delete('/{id}/states/{stateId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroyState']);

            // Transition management
            Route::get('/{id}/transitions', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'transitions']);
            Route::post('/{id}/transitions', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'storeTransition']);
            Route::put('/{id}/transitions/{transitionId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateTransition']);
            Route::delete('/{id}/transitions/{transitionId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroyTransition']);
        });

        // Blueprint Execution Routes (Runtime)
        Route::prefix('blueprint-records')->group(function () {
            Route::get('/{recordId}/state', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'getRecordState']);
            Route::post('/{recordId}/transitions/{transitionId}/start', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'startTransition']);
            Route::get('/{recordId}/history', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'getTransitionHistory']);
            Route::get('/{recordId}/sla-status', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'getSLAStatus']);
        });

        // Blueprint Execution Management
        Route::prefix('blueprint-executions')->group(function () {
            Route::post('/{executionId}/requirements', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'submitRequirements']);
            Route::post('/{executionId}/complete', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'completeExecution']);
            Route::post('/{executionId}/cancel', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'cancelExecution']);
        });

        // Blueprint Approvals
        Route::prefix('blueprint-approvals')->group(function () {
            Route::get('/pending', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'pendingApprovals']);
            Route::post('/{requestId}/approve', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'approve']);
            Route::post('/{requestId}/reject', [\App\Http\Controllers\Api\Blueprints\BlueprintExecutionController::class, 'reject']);
        });

        // Email Account Routes
        Route::prefix('email-accounts')->group(function () {
            Route::get('/', [EmailAccountController::class, 'index']);
            Route::post('/', [EmailAccountController::class, 'store']);
            Route::get('/{emailAccount}', [EmailAccountController::class, 'show']);
            Route::put('/{emailAccount}', [EmailAccountController::class, 'update']);
            Route::delete('/{emailAccount}', [EmailAccountController::class, 'destroy']);
            Route::post('/{emailAccount}/test', [EmailAccountController::class, 'testConnection']);
            Route::post('/{emailAccount}/sync', [EmailAccountController::class, 'sync']);
            Route::get('/{emailAccount}/folders', [EmailAccountController::class, 'folders']);
        });

        // Email Message Routes
        Route::prefix('emails')->group(function () {
            Route::get('/', [EmailMessageController::class, 'index']);
            Route::post('/', [EmailMessageController::class, 'store']);
            Route::post('/bulk-read', [EmailMessageController::class, 'bulkMarkRead']);
            Route::post('/bulk-delete', [EmailMessageController::class, 'bulkDelete']);
            Route::get('/{emailMessage}', [EmailMessageController::class, 'show']);
            Route::put('/{emailMessage}', [EmailMessageController::class, 'update']);
            Route::delete('/{emailMessage}', [EmailMessageController::class, 'destroy']);
            Route::post('/{emailMessage}/send', [EmailMessageController::class, 'send']);
            Route::post('/{emailMessage}/schedule', [EmailMessageController::class, 'schedule']);
            Route::post('/{emailMessage}/reply', [EmailMessageController::class, 'reply']);
            Route::post('/{emailMessage}/forward', [EmailMessageController::class, 'forward']);
            Route::post('/{emailMessage}/mark-read', [EmailMessageController::class, 'markRead']);
            Route::post('/{emailMessage}/mark-unread', [EmailMessageController::class, 'markUnread']);
            Route::post('/{emailMessage}/toggle-star', [EmailMessageController::class, 'toggleStar']);
            Route::post('/{emailMessage}/move', [EmailMessageController::class, 'moveToFolder']);
            Route::get('/{emailMessage}/thread', [EmailMessageController::class, 'thread']);
        });

        // Email Template Routes
        Route::prefix('email-templates')->group(function () {
            Route::get('/', [EmailTemplateController::class, 'index']);
            Route::get('/categories', [EmailTemplateController::class, 'categories']);
            Route::post('/', [EmailTemplateController::class, 'store']);
            Route::get('/{emailTemplate}', [EmailTemplateController::class, 'show']);
            Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update']);
            Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy']);
            Route::post('/{emailTemplate}/duplicate', [EmailTemplateController::class, 'duplicate']);
            Route::post('/{emailTemplate}/preview', [EmailTemplateController::class, 'preview']);
        });

        // Activity Routes
        Route::prefix('activities')->group(function () {
            Route::get('/types', [ActivityController::class, 'types']);
            Route::get('/outcomes', [ActivityController::class, 'outcomes']);
            Route::get('/timeline', [ActivityController::class, 'timeline']);
            Route::get('/upcoming', [ActivityController::class, 'upcoming']);
            Route::get('/overdue', [ActivityController::class, 'overdue']);

            Route::get('/', [ActivityController::class, 'index']);
            Route::post('/', [ActivityController::class, 'store']);
            Route::get('/{activity}', [ActivityController::class, 'show']);
            Route::put('/{activity}', [ActivityController::class, 'update']);
            Route::delete('/{activity}', [ActivityController::class, 'destroy']);
            Route::post('/{activity}/complete', [ActivityController::class, 'complete']);
            Route::post('/{activity}/toggle-pin', [ActivityController::class, 'togglePin']);
        });

        // Audit Log Routes
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/for-record', [AuditLogController::class, 'forRecord']);
            Route::get('/summary', [AuditLogController::class, 'summary']);
            Route::get('/user/{userId}', [AuditLogController::class, 'forUser']);
            Route::get('/{auditLog}', [AuditLogController::class, 'show']);
            Route::get('/compare/{log1}/{log2}', [AuditLogController::class, 'compare']);
        });

        // Report Routes
        Route::prefix('reports')->group(function () {
            Route::get('/types', [ReportController::class, 'types']);
            Route::get('/fields', [ReportController::class, 'fields']);
            Route::post('/preview', [ReportController::class, 'preview']);
            Route::post('/kpi', [ReportController::class, 'kpi']);

            Route::get('/', [ReportController::class, 'index']);
            Route::post('/', [ReportController::class, 'store']);
            Route::get('/{report}', [ReportController::class, 'show']);
            Route::put('/{report}', [ReportController::class, 'update']);
            Route::delete('/{report}', [ReportController::class, 'destroy']);
            Route::get('/{report}/execute', [ReportController::class, 'execute']);
            Route::get('/{report}/export', [ReportController::class, 'export']);
            Route::post('/{report}/toggle-favorite', [ReportController::class, 'toggleFavorite']);
            Route::post('/{report}/duplicate', [ReportController::class, 'duplicate']);
        });

        // Dashboard Routes
        Route::prefix('dashboards')->group(function () {
            Route::get('/widget-types', [DashboardController::class, 'widgetTypes']);

            Route::get('/', [DashboardController::class, 'index']);
            Route::post('/', [DashboardController::class, 'store']);
            Route::get('/{dashboard}', [DashboardController::class, 'show']);
            Route::put('/{dashboard}', [DashboardController::class, 'update']);
            Route::delete('/{dashboard}', [DashboardController::class, 'destroy']);
            Route::post('/{dashboard}/duplicate', [DashboardController::class, 'duplicate']);
            Route::post('/{dashboard}/set-default', [DashboardController::class, 'setDefault']);
            Route::put('/{dashboard}/layout', [DashboardController::class, 'updateLayout']);
            Route::get('/{dashboard}/data', [DashboardController::class, 'allWidgetData']);

            // Widget routes
            Route::post('/{dashboard}/widgets', [DashboardController::class, 'addWidget']);
            Route::put('/{dashboard}/widgets/{widget}', [DashboardController::class, 'updateWidget']);
            Route::delete('/{dashboard}/widgets/{widget}', [DashboardController::class, 'removeWidget']);
            Route::post('/{dashboard}/widgets/reorder', [DashboardController::class, 'reorderWidgets']);
            Route::get('/{dashboard}/widgets/{widget}/data', [DashboardController::class, 'widgetData']);
        });

        // RBAC (Role-Based Access Control) Routes
        Route::prefix('rbac')->group(function () {
            // Current user permissions
            Route::get('/my-permissions', [RbacController::class, 'getCurrentUserPermissions']);

            // Roles management
            Route::get('/roles', [RbacController::class, 'getRoles']);
            Route::post('/roles', [RbacController::class, 'createRole']);
            Route::get('/roles/{id}', [RbacController::class, 'getRole']);
            Route::put('/roles/{id}', [RbacController::class, 'updateRole']);
            Route::delete('/roles/{id}', [RbacController::class, 'deleteRole']);
            Route::get('/roles/{id}/users', [RbacController::class, 'getRoleUsers']);

            // Permissions
            Route::get('/permissions', [RbacController::class, 'getPermissions']);

            // Module permissions
            Route::get('/roles/{roleId}/module-permissions', [RbacController::class, 'getModulePermissions']);
            Route::put('/roles/{roleId}/module-permissions', [RbacController::class, 'updateModulePermissions']);
            Route::put('/roles/{roleId}/module-permissions/bulk', [RbacController::class, 'bulkUpdateModulePermissions']);

            // User role assignment
            Route::post('/users/assign-role', [RbacController::class, 'assignRoleToUser']);
            Route::post('/users/remove-role', [RbacController::class, 'removeRoleFromUser']);
            Route::get('/users/{userId}/permissions', [RbacController::class, 'getUserPermissions']);
            Route::put('/users/{userId}/roles', [RbacController::class, 'syncUserRoles']);
        });

        // Import Routes
        Route::prefix('imports/{moduleApiName}')->group(function () {
            Route::get('/', [ImportController::class, 'index']);
            Route::get('/template', [ImportController::class, 'template']);
            Route::post('/upload', [ImportController::class, 'upload']);
            Route::get('/{importId}', [ImportController::class, 'show']);
            Route::put('/{importId}/configure', [ImportController::class, 'configure']);
            Route::post('/{importId}/validate', [ImportController::class, 'validate']);
            Route::post('/{importId}/execute', [ImportController::class, 'execute']);
            Route::post('/{importId}/cancel', [ImportController::class, 'cancel']);
            Route::get('/{importId}/errors', [ImportController::class, 'errors']);
            Route::delete('/{importId}', [ImportController::class, 'destroy']);
        });

        // Export Routes
        Route::prefix('exports/{moduleApiName}')->group(function () {
            Route::get('/', [ExportController::class, 'index']);
            Route::post('/', [ExportController::class, 'store']);
            Route::get('/templates', [ExportController::class, 'templates']);
            Route::post('/templates', [ExportController::class, 'storeTemplate']);
            Route::put('/templates/{templateId}', [ExportController::class, 'updateTemplate']);
            Route::delete('/templates/{templateId}', [ExportController::class, 'destroyTemplate']);
            Route::post('/templates/{templateId}/export', [ExportController::class, 'exportFromTemplate']);
            Route::get('/{exportId}', [ExportController::class, 'show']);
            Route::get('/{exportId}/download', [ExportController::class, 'download'])->name('exports.download');
            Route::delete('/{exportId}', [ExportController::class, 'destroy']);
        });

        // API Keys Management Routes
        Route::prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index']);
            Route::post('/', [ApiKeyController::class, 'store']);
            Route::get('/{id}', [ApiKeyController::class, 'show']);
            Route::put('/{id}', [ApiKeyController::class, 'update']);
            Route::delete('/{id}', [ApiKeyController::class, 'destroy']);
            Route::post('/{id}/revoke', [ApiKeyController::class, 'revoke']);
            Route::post('/{id}/regenerate', [ApiKeyController::class, 'regenerate']);
            Route::get('/{id}/logs', [ApiKeyController::class, 'logs']);
        });

        // Outgoing Webhooks Management Routes
        Route::prefix('webhooks')->group(function () {
            Route::get('/', [WebhookController::class, 'index']);
            Route::post('/', [WebhookController::class, 'store']);
            Route::get('/{id}', [WebhookController::class, 'show']);
            Route::put('/{id}', [WebhookController::class, 'update']);
            Route::delete('/{id}', [WebhookController::class, 'destroy']);
            Route::post('/{id}/rotate-secret', [WebhookController::class, 'rotateSecret']);
            Route::post('/{id}/test', [WebhookController::class, 'test']);
            Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries']);
            Route::get('/{webhookId}/deliveries/{deliveryId}', [WebhookController::class, 'getDelivery']);
            Route::post('/{webhookId}/deliveries/{deliveryId}/retry', [WebhookController::class, 'retryDelivery']);
        });

        // Incoming Webhooks Management Routes
        Route::prefix('incoming-webhooks')->group(function () {
            Route::get('/', [IncomingWebhookController::class, 'index']);
            Route::post('/', [IncomingWebhookController::class, 'store']);
            Route::get('/{id}', [IncomingWebhookController::class, 'show']);
            Route::put('/{id}', [IncomingWebhookController::class, 'update']);
            Route::delete('/{id}', [IncomingWebhookController::class, 'destroy']);
            Route::post('/{id}/regenerate-token', [IncomingWebhookController::class, 'regenerateToken']);
            Route::get('/{id}/logs', [IncomingWebhookController::class, 'logs']);
        });

        // Global Search & Command Palette Routes
        Route::prefix('search')->group(function () {
            Route::get('/', [SearchController::class, 'search']);
            Route::get('/quick', [SearchController::class, 'quickSearch']);
            Route::get('/suggestions', [SearchController::class, 'suggestions']);
            Route::get('/history', [SearchController::class, 'history']);
            Route::delete('/history', [SearchController::class, 'clearHistory']);
            Route::get('/saved', [SearchController::class, 'savedSearches']);
            Route::post('/saved', [SearchController::class, 'saveSearch']);
            Route::delete('/saved/{id}', [SearchController::class, 'deleteSavedSearch']);
            Route::post('/saved/{id}/toggle-pin', [SearchController::class, 'togglePin']);
            Route::post('/reindex', [SearchController::class, 'reindex']);
            Route::get('/commands', [SearchController::class, 'commands']);
        });
    });

    // Public email tracking routes (no auth required)
    Route::get('/track/open/{trackingId}', [EmailTrackingController::class, 'trackOpen'])
        ->name('email.track.open');
    Route::get('/track/click/{trackingId}/{url}', [EmailTrackingController::class, 'trackClick'])
        ->name('email.track.click');

    // Public incoming webhook endpoint (no auth required - uses token)
    Route::post('/webhooks/incoming/{token}', [IncomingWebhookController::class, 'receive'])
        ->name('webhooks.incoming.receive');
});
