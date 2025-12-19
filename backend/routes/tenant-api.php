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
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\WizardController;
use App\Http\Controllers\Api\WizardDraftController;
use App\Http\Controllers\Api\Workflows\WorkflowController;
use App\Http\Controllers\Api\Workflow\WorkflowEmailTemplateController;
use App\Http\Controllers\Api\Reporting\ReportController;
use App\Http\Controllers\Api\Reporting\DashboardController;
use App\Http\Controllers\Api\Reporting\DashboardTemplateController;
use App\Http\Controllers\Api\Reporting\AdvancedReportController;
use App\Http\Controllers\Api\DataManagement\ImportController;
use App\Http\Controllers\Api\DataManagement\ExportController;
use App\Http\Controllers\Api\Integration\ApiKeyController;
use App\Http\Controllers\Api\Integration\WebhookController;
use App\Http\Controllers\Api\Integration\IncomingWebhookController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\RottingAlertController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Blueprints\BlueprintTransitionConfigController;
use App\Http\Controllers\Api\Blueprints\BlueprintSlaController;
use App\Http\Controllers\Api\WebFormController;
use App\Http\Controllers\Api\WebFormPublicController;
use App\Http\Controllers\Api\Scheduling\SchedulingPageController;
use App\Http\Controllers\Api\Scheduling\MeetingTypeController;
use App\Http\Controllers\Api\Scheduling\AvailabilityController;
use App\Http\Controllers\Api\Scheduling\ScheduledMeetingController;
use App\Http\Controllers\Api\Scheduling\PublicBookingController;
use App\Http\Controllers\Api\TimeMachine\RecordHistoryController;
use App\Http\Controllers\Api\Graph\GraphController;
use App\Http\Controllers\Api\Billing\ProductController;
use App\Http\Controllers\Api\Billing\QuoteController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\PublicQuoteController;
use App\Http\Controllers\Api\Billing\LicenseController;
use App\Http\Controllers\Api\Billing\PluginController as BillingPluginController;
use App\Http\Controllers\Api\Billing\BundleController;
use App\Http\Controllers\Api\Billing\SubscriptionController;
use App\Http\Controllers\Api\Recording\RecordingController;
use App\Http\Controllers\Api\Competitor\CompetitorController;
use App\Http\Controllers\Api\AbTest\AbTestController;
use App\Http\Controllers\Api\LandingPage\LandingPageController;
use App\Http\Controllers\Api\LandingPage\PublicLandingPageController;
use App\Http\Controllers\Api\Meeting\MeetingController;
use App\Http\Controllers\Api\Quotas\QuotaController;
use App\Http\Controllers\Api\Quotas\QuotaPeriodController;
use App\Http\Controllers\Api\Quotas\GoalController;
use App\Http\Controllers\Api\Chat\ChatWidgetController;
use App\Http\Controllers\Api\Chat\ChatConversationController;
use App\Http\Controllers\Api\Chat\ChatAgentController;
use App\Http\Controllers\Api\Chat\PublicChatController;
use App\Http\Controllers\Api\Whatsapp\WhatsappConnectionController;
use App\Http\Controllers\Api\Whatsapp\WhatsappTemplateController;
use App\Http\Controllers\Api\Whatsapp\WhatsappConversationController;
use App\Http\Controllers\Api\Whatsapp\WhatsappWebhookController;
use App\Http\Controllers\Api\Lookalike\LookalikeController;
use App\Http\Controllers\Api\Document\DocumentTemplateController;
use App\Http\Controllers\Api\Signature\SignatureController;
use App\Http\Controllers\Api\Signature\PublicSignatureController;
use App\Http\Controllers\Api\Proposal\ProposalController;
use App\Http\Controllers\Api\Proposal\PublicProposalController;
use App\Http\Controllers\Api\Approval\ApprovalController;
use App\Http\Controllers\Api\UserPreferencesController;
use App\Http\Controllers\Api\CMS\CmsPageController;
use App\Http\Controllers\Api\CMS\CmsMediaController;
use App\Http\Controllers\Api\CMS\CmsMediaFolderController;
use App\Http\Controllers\Api\CMS\CmsTemplateController;
use App\Http\Controllers\Api\CMS\CmsFormController;
use App\Http\Controllers\Api\CMS\CmsCategoryController;
use App\Http\Controllers\Api\CMS\CmsMenuController;
use App\Http\Controllers\Api\CMS\CmsTagController;
use App\Http\Controllers\Api\CMS\CmsPublicController;
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
    'throttle:api',
])->prefix('api/v1')->group(function () {
    // Public authentication routes (stricter rate limiting)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Broadcasting authentication for Laravel Echo
        Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Support\Facades\Broadcast::auth($request);
        });

        // User Preferences
        Route::prefix('preferences')->group(function () {
            Route::get('/', [UserPreferencesController::class, 'index']);
            Route::get('/{key}', [UserPreferencesController::class, 'show']);
            Route::put('/', [UserPreferencesController::class, 'update']);
            Route::post('/set', [UserPreferencesController::class, 'set']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
            Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
            Route::post('/mark-all-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
            Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
            Route::post('/{id}/archive', [\App\Http\Controllers\Api\NotificationController::class, 'archive']);

            // Notification preferences
            Route::get('/preferences', [\App\Http\Controllers\Api\NotificationController::class, 'getPreferences']);
            Route::put('/preferences', [\App\Http\Controllers\Api\NotificationController::class, 'updatePreferences']);

            // Notification schedule (quiet hours, DND)
            Route::get('/schedule', [\App\Http\Controllers\Api\NotificationController::class, 'getSchedule']);
            Route::put('/schedule', [\App\Http\Controllers\Api\NotificationController::class, 'updateSchedule']);
        });

        // Module Management Routes
        Route::prefix('modules')->group(function () {
            // View operations - requires modules.view
            Route::middleware('permission:modules.view')->group(function () {
                Route::get('/', [ModuleController::class, 'index']);
                Route::get('/active', [ModuleController::class, 'active']);
                Route::get('/by-api-name/{apiName}', [ModuleController::class, 'showByApiName']);
                Route::get('/{id}', [ModuleController::class, 'show']);
            });

            // Create operations - requires modules.create
            Route::post('/', [ModuleController::class, 'store'])->middleware('permission:modules.create');

            // Edit operations - requires modules.edit
            Route::middleware('permission:modules.edit')->group(function () {
                Route::post('/reorder', [ModuleController::class, 'reorder']);
                Route::put('/{id}', [ModuleController::class, 'update']);
                Route::post('/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);
            });

            // Delete operations - requires modules.delete
            Route::delete('/{id}', [ModuleController::class, 'destroy'])->middleware('permission:modules.delete');
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

            // Time Machine Routes (Record History)
            Route::get('/{moduleApiName}/{recordId}/history', [RecordHistoryController::class, 'history']);
            Route::get('/{moduleApiName}/{recordId}/at/{timestamp}', [RecordHistoryController::class, 'atTimestamp']);
            Route::get('/{moduleApiName}/{recordId}/diff', [RecordHistoryController::class, 'diff']);
            Route::get('/{moduleApiName}/{recordId}/compare', [RecordHistoryController::class, 'compare']);
            Route::get('/{moduleApiName}/{recordId}/timeline', [RecordHistoryController::class, 'timeline']);
            Route::get('/{moduleApiName}/{recordId}/timeline-markers', [RecordHistoryController::class, 'timelineMarkers']);
            Route::get('/{moduleApiName}/{recordId}/field-changes', [RecordHistoryController::class, 'fieldChanges']);
            Route::post('/{moduleApiName}/{recordId}/snapshot', [RecordHistoryController::class, 'createSnapshot']);
        });

        // Module Views Routes
        Route::prefix('views')->group(function () {
            Route::get('/{moduleApiName}', [ViewsController::class, 'index']);
            Route::get('/{moduleApiName}/default', [ViewsController::class, 'getDefaultView']);
            Route::get('/{moduleApiName}/kanban-fields', [ViewsController::class, 'getKanbanFields']);
            Route::post('/{moduleApiName}', [ViewsController::class, 'store']);
            Route::get('/{moduleApiName}/{viewId}', [ViewsController::class, 'show']);
            Route::put('/{moduleApiName}/{viewId}', [ViewsController::class, 'update']);
            Route::delete('/{moduleApiName}/{viewId}', [ViewsController::class, 'destroy']);
            Route::get('/{moduleApiName}/{viewId}/kanban', [ViewsController::class, 'getKanbanData']);
            Route::post('/{moduleApiName}/{viewId}/kanban/move', [ViewsController::class, 'moveKanbanRecord']);
        });

        // Wizard Routes (user-created wizards)
        Route::prefix('wizards')->group(function () {
            Route::get('/', [WizardController::class, 'index']);
            Route::post('/', [WizardController::class, 'store']);
            Route::get('/module/{moduleId}', [WizardController::class, 'forModule']);
            Route::post('/reorder', [WizardController::class, 'reorder']);
            Route::get('/{id}', [WizardController::class, 'show']);
            Route::put('/{id}', [WizardController::class, 'update']);
            Route::delete('/{id}', [WizardController::class, 'destroy']);
            Route::post('/{id}/duplicate', [WizardController::class, 'duplicate']);
            Route::post('/{id}/toggle-active', [WizardController::class, 'toggleActive']);
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

        // File Upload Routes (with upload rate limiting)
        Route::prefix('files')->middleware('throttle:uploads')->group(function () {
            Route::post('/upload', [FileUploadController::class, 'upload']);
            Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);
            Route::post('/delete', [FileUploadController::class, 'delete']);
            Route::post('/info', [FileUploadController::class, 'info']);
        });

        // User Search (for mentions)
        Route::get('/users/search', [UserSearchController::class, 'search']);

        // NOTE: Use /files/upload instead - these root-level endpoints are deprecated
        // Keeping for backwards compatibility but should be removed in v2

        // Pipeline Routes
        Route::prefix('pipelines')->group(function () {
            // View operations
            Route::middleware('permission:pipelines.view')->group(function () {
                Route::get('/', [PipelineController::class, 'index']);
                Route::get('/module/{moduleApiName}', [PipelineController::class, 'forModule']);
                Route::get('/{id}', [PipelineController::class, 'show']);
                Route::get('/{id}/kanban', [PipelineController::class, 'kanbanData']);
                Route::get('/{id}/record/{recordId}/history', [PipelineController::class, 'recordHistory']);
            });

            // Create operations
            Route::post('/', [PipelineController::class, 'store'])->middleware('permission:pipelines.create');

            // Edit operations
            Route::middleware('permission:pipelines.edit')->group(function () {
                Route::post('/sync-all-field-options', [PipelineController::class, 'syncAllFieldOptions']);
                Route::put('/{id}', [PipelineController::class, 'update']);
                Route::post('/{id}/move-record', [PipelineController::class, 'moveRecord']);
                Route::post('/{id}/reorder-stages', [PipelineController::class, 'reorderStages']);
                Route::post('/{id}/sync-field-options', [PipelineController::class, 'syncFieldOptions']);
            });

            // Delete operations
            Route::delete('/{id}', [PipelineController::class, 'destroy'])->middleware('permission:pipelines.delete');
        });

        // Workflow Automation Routes
        Route::prefix('workflows')->group(function () {
            // Meta endpoints
            Route::get('/trigger-types', [WorkflowController::class, 'triggerTypes']);
            Route::get('/action-types', [WorkflowController::class, 'actionTypes']);

            // Workflow Templates
            Route::prefix('templates')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'index']);
                Route::get('/categories', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'categories']);
                Route::get('/popular', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'popular']);
                Route::get('/category/{category}', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'byCategory']);
                Route::get('/{id}', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'show']);
                Route::post('/{id}/use', [\App\Http\Controllers\Api\Workflows\WorkflowTemplateController::class, 'use']);
            });

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

            // Version history and rollback
            Route::get('/{id}/versions', [WorkflowController::class, 'versions']);
            Route::get('/{id}/versions/{versionId}', [WorkflowController::class, 'showVersion']);
            Route::post('/{id}/versions/{versionId}/rollback', [WorkflowController::class, 'rollback']);
            Route::get('/{id}/versions/{versionId1}/compare/{versionId2}', [WorkflowController::class, 'compareVersions']);
        });

        // Workflow Email Templates Routes
        Route::prefix('workflow-email-templates')->group(function () {
            Route::get('/', [WorkflowEmailTemplateController::class, 'index']);
            Route::get('/categories', [WorkflowEmailTemplateController::class, 'categories']);
            Route::get('/variables', [WorkflowEmailTemplateController::class, 'variables']);
            Route::post('/', [WorkflowEmailTemplateController::class, 'store']);
            Route::get('/{workflowEmailTemplate}', [WorkflowEmailTemplateController::class, 'show']);
            Route::put('/{workflowEmailTemplate}', [WorkflowEmailTemplateController::class, 'update']);
            Route::delete('/{workflowEmailTemplate}', [WorkflowEmailTemplateController::class, 'destroy']);
            Route::post('/{workflowEmailTemplate}/duplicate', [WorkflowEmailTemplateController::class, 'duplicate']);
            Route::post('/{workflowEmailTemplate}/preview', [WorkflowEmailTemplateController::class, 'preview']);
        });

        // Blueprint Routes (Stage Transitions & SLAs)
        Route::prefix('blueprints')->group(function () {
            // View operations
            Route::middleware('permission:blueprints.view')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'index']);
                Route::get('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'show']);
                Route::get('/{id}/states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'states']);
                Route::get('/{id}/transitions', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'transitions']);
            });

            // Create operations
            Route::middleware('permission:blueprints.create')->group(function () {
                Route::post('/', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'store']);
                Route::post('/{id}/states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'storeState']);
                Route::post('/{id}/transitions', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'storeTransition']);
            });

            // Edit operations
            Route::middleware('permission:blueprints.edit')->group(function () {
                Route::put('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'update']);
                Route::put('/{id}/layout', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateLayout']);
                Route::post('/{id}/toggle-active', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'toggleActive']);
                Route::post('/{id}/sync-states', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'syncStates']);
                Route::put('/{id}/states/{stateId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateState']);
                Route::put('/{id}/transitions/{transitionId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'updateTransition']);
            });

            // Delete operations
            Route::middleware('permission:blueprints.delete')->group(function () {
                Route::delete('/{id}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroy']);
                Route::delete('/{id}/states/{stateId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroyState']);
                Route::delete('/{id}/transitions/{transitionId}', [\App\Http\Controllers\Api\Blueprints\BlueprintController::class, 'destroyTransition']);
            });
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

        // Blueprint Transition Configuration (Conditions, Requirements, Actions, Approval)
        Route::prefix('blueprint-transitions')->middleware('permission:blueprints.edit')->group(function () {
            // Conditions
            Route::get('/{transitionId}/conditions', [BlueprintTransitionConfigController::class, 'getConditions']);
            Route::post('/{transitionId}/conditions', [BlueprintTransitionConfigController::class, 'storeCondition']);
            Route::put('/{transitionId}/conditions/{conditionId}', [BlueprintTransitionConfigController::class, 'updateCondition']);
            Route::delete('/{transitionId}/conditions/{conditionId}', [BlueprintTransitionConfigController::class, 'destroyCondition']);

            // Requirements
            Route::get('/{transitionId}/requirements', [BlueprintTransitionConfigController::class, 'getRequirements']);
            Route::post('/{transitionId}/requirements', [BlueprintTransitionConfigController::class, 'storeRequirement']);
            Route::put('/{transitionId}/requirements/{requirementId}', [BlueprintTransitionConfigController::class, 'updateRequirement']);
            Route::delete('/{transitionId}/requirements/{requirementId}', [BlueprintTransitionConfigController::class, 'destroyRequirement']);

            // Actions
            Route::get('/{transitionId}/actions', [BlueprintTransitionConfigController::class, 'getActions']);
            Route::post('/{transitionId}/actions', [BlueprintTransitionConfigController::class, 'storeAction']);
            Route::put('/{transitionId}/actions/{actionId}', [BlueprintTransitionConfigController::class, 'updateAction']);
            Route::delete('/{transitionId}/actions/{actionId}', [BlueprintTransitionConfigController::class, 'destroyAction']);

            // Approval
            Route::get('/{transitionId}/approval', [BlueprintTransitionConfigController::class, 'getApproval']);
            Route::put('/{transitionId}/approval', [BlueprintTransitionConfigController::class, 'setApproval']);
            Route::delete('/{transitionId}/approval', [BlueprintTransitionConfigController::class, 'removeApproval']);
        });

        // Blueprint SLA Management
        Route::prefix('blueprints/{blueprintId}/slas')->middleware('permission:blueprints.edit')->group(function () {
            Route::get('/', [BlueprintSlaController::class, 'index']);
            Route::post('/', [BlueprintSlaController::class, 'store']);
            Route::get('/{slaId}', [BlueprintSlaController::class, 'show']);
            Route::put('/{slaId}', [BlueprintSlaController::class, 'update']);
            Route::delete('/{slaId}', [BlueprintSlaController::class, 'destroy']);
        });

        // Blueprint SLA Escalations
        Route::prefix('blueprint-slas/{slaId}/escalations')->middleware('permission:blueprints.edit')->group(function () {
            Route::get('/', [BlueprintSlaController::class, 'getEscalations']);
            Route::post('/', [BlueprintSlaController::class, 'storeEscalation']);
            Route::put('/{escalationId}', [BlueprintSlaController::class, 'updateEscalation']);
            Route::delete('/{escalationId}', [BlueprintSlaController::class, 'destroyEscalation']);
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
            // View operations
            Route::middleware('permission:email_templates.view')->group(function () {
                Route::get('/', [EmailTemplateController::class, 'index']);
                Route::get('/categories', [EmailTemplateController::class, 'categories']);
                Route::get('/{emailTemplate}', [EmailTemplateController::class, 'show']);
                Route::post('/{emailTemplate}/preview', [EmailTemplateController::class, 'preview']);
            });

            // Create operations
            Route::middleware('permission:email_templates.create')->group(function () {
                Route::post('/', [EmailTemplateController::class, 'store']);
                Route::post('/{emailTemplate}/duplicate', [EmailTemplateController::class, 'duplicate']);
            });

            // Edit operations
            Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email_templates.edit');

            // Delete operations
            Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:email_templates.delete');
        });

        // Activity Routes - requires activity.view permission
        Route::prefix('activities')->middleware('permission:activity.view')->group(function () {
            Route::get('/types', [ActivityController::class, 'types']);
            Route::get('/outcomes', [ActivityController::class, 'outcomes']);
            Route::get('/timeline', [ActivityController::class, 'timeline']);
            Route::get('/upcoming', [ActivityController::class, 'upcoming']);
            Route::get('/overdue', [ActivityController::class, 'overdue']);
            Route::get('/', [ActivityController::class, 'index']);
            Route::get('/{activity}', [ActivityController::class, 'show']);

            // Write operations (still require activity.view as base)
            Route::post('/', [ActivityController::class, 'store']);
            Route::put('/{activity}', [ActivityController::class, 'update']);
            Route::delete('/{activity}', [ActivityController::class, 'destroy']);
            Route::post('/{activity}/complete', [ActivityController::class, 'complete']);
            Route::post('/{activity}/toggle-pin', [ActivityController::class, 'togglePin']);
        });

        // Audit Log Routes - requires activity.view permission
        Route::prefix('audit-logs')->middleware('permission:activity.view')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/for-record', [AuditLogController::class, 'forRecord']);
            Route::get('/summary', [AuditLogController::class, 'summary']);
            Route::get('/user/{userId}', [AuditLogController::class, 'forUser']);
            Route::get('/{auditLog}', [AuditLogController::class, 'show']);
            Route::get('/compare/{log1}/{log2}', [AuditLogController::class, 'compare']);
        });

        // Report Routes
        Route::prefix('reports')->group(function () {
            // View operations
            Route::middleware('permission:reports.view')->group(function () {
                Route::get('/types', [ReportController::class, 'types']);
                Route::get('/fields', [ReportController::class, 'fields']);
                Route::post('/preview', [ReportController::class, 'preview']);
                Route::post('/kpi', [ReportController::class, 'kpi']);
                Route::get('/', [ReportController::class, 'index']);
                Route::get('/{report}', [ReportController::class, 'show']);
                Route::get('/{report}/execute', [ReportController::class, 'execute']);
                Route::get('/{report}/export', [ReportController::class, 'export']);
            });

            // Create operations
            Route::middleware('permission:reports.create')->group(function () {
                Route::post('/', [ReportController::class, 'store']);
                Route::post('/{report}/duplicate', [ReportController::class, 'duplicate']);
            });

            // Edit operations
            Route::middleware('permission:reports.edit')->group(function () {
                Route::put('/{report}', [ReportController::class, 'update']);
                Route::post('/{report}/toggle-favorite', [ReportController::class, 'toggleFavorite']);
            });

            // Delete operations
            Route::delete('/{report}', [ReportController::class, 'destroy'])->middleware('permission:reports.delete');

            // Advanced Reporting (Cross-object, Calculated Fields, Cohort Analysis)
            Route::prefix('advanced')->middleware('permission:reports.view')->group(function () {
                Route::post('/execute', [AdvancedReportController::class, 'execute']);
                Route::get('/joins/{moduleId}', [AdvancedReportController::class, 'getJoins']);
                Route::post('/fields', [AdvancedReportController::class, 'getFields']);
                Route::post('/cohort', [AdvancedReportController::class, 'cohort']);
                Route::post('/validate-formula', [AdvancedReportController::class, 'validateFormula']);
            });
        });

        // Dashboard Routes
        Route::prefix('dashboards')->group(function () {
            // View operations
            Route::middleware('permission:dashboards.view')->group(function () {
                Route::get('/widget-types', [DashboardController::class, 'widgetTypes']);
                Route::get('/', [DashboardController::class, 'index']);
                Route::get('/{dashboard}', [DashboardController::class, 'show']);
                Route::get('/{dashboard}/data', [DashboardController::class, 'allWidgetData']);
                Route::get('/{dashboard}/widgets/{widget}/data', [DashboardController::class, 'widgetData']);
                Route::get('/{dashboard}/export', [DashboardController::class, 'export']);
            });

            // Create operations
            Route::middleware('permission:dashboards.create')->group(function () {
                Route::post('/', [DashboardController::class, 'store']);
                Route::post('/{dashboard}/duplicate', [DashboardController::class, 'duplicate']);
            });

            // Edit operations
            Route::middleware('permission:dashboards.edit')->group(function () {
                Route::put('/{dashboard}', [DashboardController::class, 'update']);
                Route::post('/{dashboard}/set-default', [DashboardController::class, 'setDefault']);
                Route::put('/{dashboard}/layout', [DashboardController::class, 'updateLayout']);
                Route::post('/{dashboard}/widgets', [DashboardController::class, 'addWidget']);
                Route::put('/{dashboard}/widgets/{widget}', [DashboardController::class, 'updateWidget']);
                Route::delete('/{dashboard}/widgets/{widget}', [DashboardController::class, 'removeWidget']);
                Route::post('/{dashboard}/widgets/positions', [DashboardController::class, 'updateWidgetPositions']);
            });

            // Delete operations
            Route::delete('/{dashboard}', [DashboardController::class, 'destroy'])->middleware('permission:dashboards.delete');
        });

        // Dashboard Template Routes
        Route::prefix('dashboard-templates')->middleware('permission:dashboards.view')->group(function () {
            Route::get('/', [DashboardTemplateController::class, 'index']);
            Route::get('/categories', [DashboardTemplateController::class, 'categories']);
            Route::get('/{id}', [DashboardTemplateController::class, 'show']);
            Route::post('/{id}/create-dashboard', [DashboardTemplateController::class, 'createDashboard'])
                ->middleware('permission:dashboards.create');
        });

        // RBAC (Role-Based Access Control) Routes
        Route::prefix('rbac')->group(function () {
            // Current user permissions - always accessible
            Route::get('/my-permissions', [RbacController::class, 'getCurrentUserPermissions']);

            // View operations
            Route::middleware('permission:roles.view')->group(function () {
                Route::get('/roles', [RbacController::class, 'getRoles']);
                Route::get('/roles/{id}', [RbacController::class, 'getRole']);
                Route::get('/roles/{id}/users', [RbacController::class, 'getRoleUsers']);
                Route::get('/permissions', [RbacController::class, 'getPermissions']);
                Route::get('/roles/{roleId}/module-permissions', [RbacController::class, 'getModulePermissions']);
                Route::get('/users/{userId}/permissions', [RbacController::class, 'getUserPermissions']);
            });

            // Create operations
            Route::post('/roles', [RbacController::class, 'createRole'])->middleware('permission:roles.create');

            // Edit operations
            Route::middleware('permission:roles.edit')->group(function () {
                Route::put('/roles/{id}', [RbacController::class, 'updateRole']);
                Route::put('/roles/{roleId}/module-permissions', [RbacController::class, 'updateModulePermissions']);
                Route::put('/roles/{roleId}/module-permissions/bulk', [RbacController::class, 'bulkUpdateModulePermissions']);
                Route::post('/users/assign-role', [RbacController::class, 'assignRoleToUser']);
                Route::post('/users/remove-role', [RbacController::class, 'removeRoleFromUser']);
                Route::put('/users/{userId}/roles', [RbacController::class, 'syncUserRoles']);
            });

            // Delete operations
            Route::delete('/roles/{id}', [RbacController::class, 'deleteRole'])->middleware('permission:roles.delete');
        });

        // User Management Routes
        Route::prefix('users')->group(function () {
            // View operations
            Route::middleware('permission:users.view')->group(function () {
                Route::get('/', [UserController::class, 'index']);
                Route::get('/{id}', [UserController::class, 'show']);
                Route::get('/{id}/sessions', [UserController::class, 'sessions']);
            });

            // Create operations
            Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');

            // Edit operations
            Route::middleware('permission:users.edit')->group(function () {
                Route::put('/{id}', [UserController::class, 'update']);
                Route::post('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
                Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
                Route::delete('/{id}/sessions/{sessionId}', [UserController::class, 'revokeSession']);
                Route::delete('/{id}/sessions', [UserController::class, 'revokeAllSessions']);
            });

            // Delete operations
            Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
        });

        // Import Routes - requires data.import permission
        Route::prefix('imports/{moduleApiName}')->middleware('permission:data.import')->group(function () {
            Route::get('/', [ImportController::class, 'index']);
            Route::get('/template', [ImportController::class, 'template']);
            Route::post('/upload', [ImportController::class, 'upload']);
            Route::get('/{importId}', [ImportController::class, 'show']);
            Route::put('/{importId}/configure', [ImportController::class, 'configure']);
            Route::post('/{importId}/validate', [ImportController::class, 'validateImport']);
            Route::post('/{importId}/execute', [ImportController::class, 'execute']);
            Route::post('/{importId}/cancel', [ImportController::class, 'cancel']);
            Route::get('/{importId}/errors', [ImportController::class, 'errors']);
            Route::delete('/{importId}', [ImportController::class, 'destroy']);
        });

        // Export Routes - requires data.export permission (with export rate limiting)
        Route::prefix('exports/{moduleApiName}')->middleware(['permission:data.export', 'throttle:exports'])->group(function () {
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
            // View operations
            Route::middleware('permission:api_keys.view')->group(function () {
                Route::get('/', [ApiKeyController::class, 'index']);
                Route::get('/{id}', [ApiKeyController::class, 'show']);
                Route::get('/{id}/logs', [ApiKeyController::class, 'logs']);
            });

            // Create operations
            Route::post('/', [ApiKeyController::class, 'store'])->middleware('permission:api_keys.create');

            // Edit operations
            Route::middleware('permission:api_keys.edit')->group(function () {
                Route::put('/{id}', [ApiKeyController::class, 'update']);
                Route::post('/{id}/revoke', [ApiKeyController::class, 'revoke']);
                Route::post('/{id}/regenerate', [ApiKeyController::class, 'regenerate']);
            });

            // Delete operations
            Route::delete('/{id}', [ApiKeyController::class, 'destroy'])->middleware('permission:api_keys.delete');
        });

        // Outgoing Webhooks Management Routes
        Route::prefix('webhooks')->group(function () {
            // View operations
            Route::middleware('permission:webhooks.view')->group(function () {
                Route::get('/', [WebhookController::class, 'index']);
                Route::get('/{id}', [WebhookController::class, 'show']);
                Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries']);
                Route::get('/{webhookId}/deliveries/{deliveryId}', [WebhookController::class, 'getDelivery']);
            });

            // Create operations
            Route::post('/', [WebhookController::class, 'store'])->middleware('permission:webhooks.create');

            // Edit operations
            Route::middleware('permission:webhooks.edit')->group(function () {
                Route::put('/{id}', [WebhookController::class, 'update']);
                Route::post('/{id}/rotate-secret', [WebhookController::class, 'rotateSecret']);
                Route::post('/{id}/test', [WebhookController::class, 'test']);
                Route::post('/{webhookId}/deliveries/{deliveryId}/retry', [WebhookController::class, 'retryDelivery']);
            });

            // Delete operations
            Route::delete('/{id}', [WebhookController::class, 'destroy'])->middleware('permission:webhooks.delete');
        });

        // Incoming Webhooks Management Routes
        Route::prefix('incoming-webhooks')->group(function () {
            // View operations
            Route::middleware('permission:webhooks.view')->group(function () {
                Route::get('/', [IncomingWebhookController::class, 'index']);
                Route::get('/{id}', [IncomingWebhookController::class, 'show']);
                Route::get('/{id}/logs', [IncomingWebhookController::class, 'logs']);
            });

            // Create operations
            Route::post('/', [IncomingWebhookController::class, 'store'])->middleware('permission:webhooks.create');

            // Edit operations
            Route::middleware('permission:webhooks.edit')->group(function () {
                Route::put('/{id}', [IncomingWebhookController::class, 'update']);
                Route::post('/{id}/regenerate-token', [IncomingWebhookController::class, 'regenerateToken']);
            });

            // Delete operations
            Route::delete('/{id}', [IncomingWebhookController::class, 'destroy'])->middleware('permission:webhooks.delete');
        });

        // Global Search & Command Palette Routes (with search rate limiting)
        Route::prefix('search')->middleware('throttle:search')->group(function () {
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

        // Deal Rotting Alerts Routes
        Route::prefix('rotting')->group(function () {
            // User's rotting deals and alerts
            Route::get('/deals', [RottingAlertController::class, 'index']);
            Route::get('/deals/{recordId}', [RottingAlertController::class, 'show']);
            Route::get('/summary/{pipelineId}', [RottingAlertController::class, 'summary']);

            // Alerts management
            Route::get('/alerts', [RottingAlertController::class, 'alerts']);
            Route::get('/alerts/count', [RottingAlertController::class, 'count']);
            Route::post('/alerts/{alertId}/acknowledge', [RottingAlertController::class, 'acknowledge']);
            Route::post('/alerts/acknowledge-all', [RottingAlertController::class, 'acknowledgeAll']);

            // User settings
            Route::get('/settings', [RottingAlertController::class, 'settings']);
            Route::put('/settings', [RottingAlertController::class, 'updateSettings']);

            // Stage configuration (requires pipeline edit permission)
            Route::middleware('permission:pipelines.edit')->group(function () {
                Route::put('/pipelines/{pipelineId}/stages/{stageId}', [RottingAlertController::class, 'configureStage']);
                Route::delete('/pipelines/{pipelineId}/stages/{stageId}', [RottingAlertController::class, 'removeStageConfig']);
            });

            // Record activity
            Route::post('/record-activity/{recordId}', [RottingAlertController::class, 'recordActivity']);
        });

        // Revenue Intelligence Graph Routes
        Route::prefix('graph')->group(function () {
            // Graph data endpoints
            Route::get('/nodes', [GraphController::class, 'nodes']);
            Route::get('/edges', [GraphController::class, 'edges']);
            Route::get('/neighborhood/{type}/{id}', [GraphController::class, 'neighborhood']);
            Route::get('/path', [GraphController::class, 'path']);
            Route::get('/metrics/{type}/{id}', [GraphController::class, 'metrics']);
            Route::get('/relationship-types', [GraphController::class, 'relationshipTypes']);

            // Relationship management
            Route::post('/relationships', [GraphController::class, 'createRelationship']);
            Route::delete('/relationships/{id}', [GraphController::class, 'deleteRelationship']);
        });

        // Deal Rooms Routes
        Route::prefix('deal-rooms')->group(function () {
            // Room management
            Route::get('/', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'destroy']);

            // Members
            Route::get('/{id}/members', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'members']);
            Route::post('/{id}/members', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'addMember']);
            Route::delete('/{id}/members/{memberId}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'removeMember']);

            // Action Items
            Route::get('/{id}/actions', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'actions']);
            Route::post('/{id}/actions', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'createAction']);
            Route::put('/{id}/actions/{actionId}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'updateAction']);
            Route::delete('/{id}/actions/{actionId}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'deleteAction']);

            // Documents
            Route::get('/{id}/documents', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'documents']);
            Route::post('/{id}/documents', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'uploadDocument']);
            Route::delete('/{id}/documents/{docId}', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'deleteDocument']);

            // Messages
            Route::get('/{id}/messages', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'messages']);
            Route::post('/{id}/messages', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'sendMessage']);

            // Analytics & Activities
            Route::get('/{id}/analytics', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'analytics']);
            Route::get('/{id}/activities', [\App\Http\Controllers\Api\DealRoom\DealRoomController::class, 'activities']);
        });

        // Competitor Battlecards Routes
        Route::prefix('competitors')->group(function () {
            // Competitor CRUD
            Route::get('/', [CompetitorController::class, 'index']);
            Route::post('/', [CompetitorController::class, 'store']);
            Route::get('/comparison', [CompetitorController::class, 'comparison']);
            Route::get('/{id}', [CompetitorController::class, 'show']);
            Route::put('/{id}', [CompetitorController::class, 'update']);
            Route::delete('/{id}', [CompetitorController::class, 'destroy']);

            // Battlecard
            Route::get('/{id}/battlecard', [CompetitorController::class, 'battlecard']);
            Route::post('/{id}/battlecard/sections', [CompetitorController::class, 'storeSection']);
            Route::put('/{id}/battlecard/sections/{sectionId}', [CompetitorController::class, 'updateSection']);

            // Objections
            Route::get('/{id}/objections', [CompetitorController::class, 'objections']);
            Route::post('/{id}/objections', [CompetitorController::class, 'storeObjection']);
            Route::put('/{id}/objections/{objectionId}', [CompetitorController::class, 'updateObjection']);
            Route::post('/{id}/objections/{objectionId}/feedback', [CompetitorController::class, 'objectionFeedback']);

            // Notes
            Route::get('/{id}/notes', [CompetitorController::class, 'notes']);
            Route::post('/{id}/notes', [CompetitorController::class, 'storeNote']);

            // Analytics
            Route::get('/{id}/analytics', [CompetitorController::class, 'analytics']);
        });

        // Deal-Competitor linking
        Route::prefix('deals/{dealId}/competitors')->group(function () {
            Route::get('/', [CompetitorController::class, 'getDealCompetitors']);
            Route::post('/', [CompetitorController::class, 'addToDeal']);
            Route::delete('/{competitorId}', [CompetitorController::class, 'removeFromDeal']);
            Route::put('/{competitorId}/outcome', [CompetitorController::class, 'updateDealOutcome']);
        });

        // Process Recorder Routes
        Route::prefix('recordings')->group(function () {
            // Recording session management
            Route::get('/active', [RecordingController::class, 'active']);
            Route::post('/start', [RecordingController::class, 'start']);
            Route::post('/capture', [RecordingController::class, 'captureAction']);

            // Recording CRUD
            Route::get('/', [RecordingController::class, 'index']);
            Route::get('/{id}', [RecordingController::class, 'show']);
            Route::delete('/{id}', [RecordingController::class, 'destroy']);

            // Recording controls
            Route::post('/{id}/stop', [RecordingController::class, 'stop']);
            Route::post('/{id}/pause', [RecordingController::class, 'pause']);
            Route::post('/{id}/resume', [RecordingController::class, 'resume']);
            Route::post('/{id}/duplicate', [RecordingController::class, 'duplicate']);

            // Step management
            Route::get('/{id}/steps', [RecordingController::class, 'steps']);
            Route::delete('/{id}/steps/{stepId}', [RecordingController::class, 'removeStep']);
            Route::put('/{id}/steps/reorder', [RecordingController::class, 'reorderSteps']);
            Route::post('/{id}/steps/{stepId}/parameterize', [RecordingController::class, 'parameterizeStep']);
            Route::delete('/{id}/steps/{stepId}/parameterize', [RecordingController::class, 'resetStepParameterization']);

            // Workflow generation
            Route::get('/{id}/preview', [RecordingController::class, 'preview']);
            Route::post('/{id}/generate-workflow', [RecordingController::class, 'generateWorkflow']);
        });

        // AI & Machine Learning Routes (Phase H)
        Route::prefix('ai')->group(function () {
            // AI Settings
            Route::get('/settings', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'index']);
            Route::put('/settings', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'update']);
            Route::get('/usage', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'usage']);
            Route::post('/test-connection', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'testConnection']);

            // AI Prompts
            Route::get('/prompts', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'prompts']);
            Route::post('/prompts', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'savePrompt']);
            Route::delete('/prompts/{id}', [\App\Http\Controllers\Api\AI\AiSettingsController::class, 'deletePrompt']);

            // Email Composition
            Route::prefix('email')->group(function () {
                Route::post('/compose', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'compose']);
                Route::post('/reply', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'reply']);
                Route::post('/improve', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'improve']);
                Route::post('/suggest-subjects', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'suggestSubjects']);
                Route::post('/analyze-tone', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'analyzeTone']);
                Route::get('/drafts', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'drafts']);
                Route::get('/drafts/{id}', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'getDraft']);
                Route::delete('/drafts/{id}', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'deleteDraft']);
                Route::post('/drafts/{id}/mark-used', [\App\Http\Controllers\Api\AI\EmailCompositionController::class, 'markUsed']);
            });

            // Lead Scoring
            Route::prefix('scoring')->group(function () {
                Route::get('/models', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'models']);
                Route::get('/models/{id}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'getModel']);
                Route::post('/models', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'saveModel']);
                Route::delete('/models/{id}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'deleteModel']);
                Route::post('/score-record', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'scoreRecord']);
                Route::post('/batch-score', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'batchScore']);
                Route::get('/records/{module}/{recordId}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'getRecordScore']);
                Route::get('/records/{module}/{recordId}/history', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'getScoreHistory']);
                Route::get('/stats/{module}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'statistics']);
                Route::get('/top/{module}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'topScored']);
                Route::get('/grade/{module}/{grade}', [\App\Http\Controllers\Api\AI\LeadScoringController::class, 'byGrade']);
            });

            // Sentiment Analysis
            Route::prefix('sentiment')->group(function () {
                Route::post('/analyze', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'analyze']);
                Route::post('/analyze-email/{emailId}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'analyzeEmail']);
                Route::get('/summary/{module}/{recordId}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'getRecordSummary']);
                Route::get('/timeline/{module}/{recordId}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'getTimeline']);
                Route::get('/alerts', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'alerts']);
                Route::post('/alerts/{id}/read', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'markAlertRead']);
                Route::post('/alerts/{id}/dismiss', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'dismissAlert']);
                Route::get('/declining/{module}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'declining']);
                Route::get('/negative/{module}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'negative']);
                Route::get('/distribution/{module}', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'distribution']);
                Route::post('/batch-analyze-emails', [\App\Http\Controllers\Api\AI\SentimentAnalysisController::class, 'batchAnalyzeEmails']);
            });

            // AI Report Generation
            Route::prefix('reports')->group(function () {
                Route::get('/status', [\App\Http\Controllers\Api\AiReportController::class, 'status']);
                Route::post('/generate', [\App\Http\Controllers\Api\AiReportController::class, 'generate']);
                Route::post('/create', [\App\Http\Controllers\Api\AiReportController::class, 'createReport']);
                Route::get('/suggest/{reportId}', [\App\Http\Controllers\Api\AiReportController::class, 'suggest']);
                Route::post('/parse-filter', [\App\Http\Controllers\Api\AiReportController::class, 'parseFilter']);
            });
        });

        // Analytics Alerts Routes
        Route::prefix('analytics-alerts')->group(function () {
            Route::get('/options', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'options']);
            Route::get('/stats', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'stats']);
            Route::get('/unacknowledged', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'unacknowledged']);

            Route::get('/', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'store']);
            Route::get('/{alert}', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'show']);
            Route::put('/{alert}', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'update']);
            Route::delete('/{alert}', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'destroy']);
            Route::post('/{alert}/toggle', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'toggle']);
            Route::post('/{alert}/check', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'check']);
            Route::get('/{alert}/history', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'history']);
            Route::post('/{alert}/subscribe', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'subscribe']);
            Route::delete('/{alert}/subscribe', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'unsubscribe']);
            Route::post('/{alert}/mute', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'mute']);

            // Alert history actions
            Route::post('/history/{history}/acknowledge', [\App\Http\Controllers\Api\AnalyticsAlertController::class, 'acknowledge']);
        });

        // Document Templates Routes (Phase F)
        Route::prefix('document-templates')->group(function () {
            Route::get('/variables', [DocumentTemplateController::class, 'variables']);
            Route::get('/generated', [DocumentTemplateController::class, 'generatedDocuments']);
            Route::get('/generated/{generatedDocument}', [DocumentTemplateController::class, 'showGeneratedDocument']);
            Route::delete('/generated/{generatedDocument}', [DocumentTemplateController::class, 'deleteGeneratedDocument']);

            Route::get('/', [DocumentTemplateController::class, 'index']);
            Route::post('/', [DocumentTemplateController::class, 'store']);
            Route::get('/{documentTemplate}', [DocumentTemplateController::class, 'show']);
            Route::put('/{documentTemplate}', [DocumentTemplateController::class, 'update']);
            Route::delete('/{documentTemplate}', [DocumentTemplateController::class, 'destroy']);
            Route::post('/{documentTemplate}/duplicate', [DocumentTemplateController::class, 'duplicate']);
            Route::post('/{documentTemplate}/generate', [DocumentTemplateController::class, 'generate']);
            Route::post('/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview']);
        });

        // E-Signature Routes (Phase F)
        Route::prefix('signatures')->group(function () {
            // Templates
            Route::get('/templates', [SignatureController::class, 'templates']);
            Route::post('/templates', [SignatureController::class, 'storeTemplate']);
            Route::get('/templates/{signatureTemplate}', [SignatureController::class, 'showTemplate']);
            Route::put('/templates/{signatureTemplate}', [SignatureController::class, 'updateTemplate']);
            Route::delete('/templates/{signatureTemplate}', [SignatureController::class, 'destroyTemplate']);

            // Signature Requests
            Route::get('/', [SignatureController::class, 'index']);
            Route::post('/', [SignatureController::class, 'store']);
            Route::post('/from-document/{generatedDocument}', [SignatureController::class, 'storeFromDocument']);
            Route::get('/{signatureRequest}', [SignatureController::class, 'show']);
            Route::put('/{signatureRequest}', [SignatureController::class, 'update']);
            Route::delete('/{signatureRequest}', [SignatureController::class, 'destroy']);
            Route::post('/{signatureRequest}/send', [SignatureController::class, 'send']);
            Route::post('/{signatureRequest}/void', [SignatureController::class, 'void']);
            Route::post('/{signatureRequest}/remind', [SignatureController::class, 'remind']);
            Route::get('/{signatureRequest}/audit-log', [SignatureController::class, 'auditLog']);
        });

        // Proposals Routes (Phase F)
        Route::prefix('proposals')->group(function () {
            // Templates
            Route::get('/templates', [ProposalController::class, 'templates']);
            Route::post('/templates', [ProposalController::class, 'storeTemplate']);
            Route::get('/templates/{proposalTemplate}', [ProposalController::class, 'showTemplate']);
            Route::put('/templates/{proposalTemplate}', [ProposalController::class, 'updateTemplate']);
            Route::delete('/templates/{proposalTemplate}', [ProposalController::class, 'destroyTemplate']);

            // Content Blocks
            Route::get('/content-blocks', [ProposalController::class, 'contentBlocks']);
            Route::post('/content-blocks', [ProposalController::class, 'storeContentBlock']);
            Route::put('/content-blocks/{proposalContentBlock}', [ProposalController::class, 'updateContentBlock']);
            Route::delete('/content-blocks/{proposalContentBlock}', [ProposalController::class, 'destroyContentBlock']);

            // Proposals
            Route::get('/', [ProposalController::class, 'index']);
            Route::post('/', [ProposalController::class, 'store']);
            Route::get('/{proposal}', [ProposalController::class, 'show']);
            Route::put('/{proposal}', [ProposalController::class, 'update']);
            Route::delete('/{proposal}', [ProposalController::class, 'destroy']);
            Route::post('/{proposal}/duplicate', [ProposalController::class, 'duplicate']);
            Route::post('/{proposal}/send', [ProposalController::class, 'send']);
            Route::get('/{proposal}/analytics', [ProposalController::class, 'analytics']);
            Route::get('/{proposal}/comments', [ProposalController::class, 'comments']);
            Route::post('/{proposal}/comments', [ProposalController::class, 'addComment']);
            Route::post('/comments/{proposalComment}/resolve', [ProposalController::class, 'resolveComment']);

            // Sections
            Route::post('/{proposal}/sections', [ProposalController::class, 'addSection']);
            Route::put('/sections/{proposalSection}', [ProposalController::class, 'updateSection']);
            Route::delete('/sections/{proposalSection}', [ProposalController::class, 'deleteSection']);
            Route::post('/{proposal}/sections/reorder', [ProposalController::class, 'reorderSections']);

            // Pricing Items
            Route::post('/{proposal}/pricing-items', [ProposalController::class, 'addPricingItem']);
            Route::put('/pricing-items/{proposalPricingItem}', [ProposalController::class, 'updatePricingItem']);
            Route::delete('/pricing-items/{proposalPricingItem}', [ProposalController::class, 'deletePricingItem']);
        });

        // Approval Workflow Routes (Phase F)
        Route::prefix('approvals')->group(function () {
            // Current user views
            Route::get('/pending', [ApprovalController::class, 'pending']);
            Route::get('/my-requests', [ApprovalController::class, 'myRequests']);
            Route::post('/check', [ApprovalController::class, 'checkNeedsApproval']);

            // Approval Requests
            Route::get('/', [ApprovalController::class, 'index']);
            Route::post('/submit', [ApprovalController::class, 'submit']);
            Route::get('/{approvalRequest}', [ApprovalController::class, 'show']);
            Route::post('/{approvalRequest}/approve', [ApprovalController::class, 'approve']);
            Route::post('/{approvalRequest}/reject', [ApprovalController::class, 'reject']);
            Route::post('/{approvalRequest}/cancel', [ApprovalController::class, 'cancel']);
            Route::get('/{approvalRequest}/history', [ApprovalController::class, 'history']);

            // Approval Rules
            Route::get('/rules', [ApprovalController::class, 'rules']);
            Route::post('/rules', [ApprovalController::class, 'storeRule']);
            Route::get('/rules/{approvalRule}', [ApprovalController::class, 'showRule']);
            Route::put('/rules/{approvalRule}', [ApprovalController::class, 'updateRule']);
            Route::delete('/rules/{approvalRule}', [ApprovalController::class, 'destroyRule']);

            // Delegations
            Route::get('/delegations', [ApprovalController::class, 'delegations']);
            Route::get('/delegations/to-me', [ApprovalController::class, 'delegatedToMe']);
            Route::post('/delegations', [ApprovalController::class, 'storeDelegation']);
            Route::delete('/delegations/{approvalDelegation}', [ApprovalController::class, 'destroyDelegation']);

            // Quick Actions
            Route::get('/quick-actions', [ApprovalController::class, 'quickActions']);
            Route::post('/quick-actions', [ApprovalController::class, 'storeQuickAction']);
            Route::post('/quick-actions/{approvalQuickAction}/use/{approvalRequest}', [ApprovalController::class, 'useQuickAction']);
            Route::delete('/quick-actions/{approvalQuickAction}', [ApprovalController::class, 'destroyQuickAction']);
        });

        // A/B Testing Routes
        Route::prefix('ab-tests')->group(function () {
            // Meta routes
            Route::get('/types', [AbTestController::class, 'types']);
            Route::get('/entity-types', [AbTestController::class, 'entityTypes']);
            Route::get('/statuses', [AbTestController::class, 'statuses']);
            Route::get('/goals', [AbTestController::class, 'goals']);

            // CRUD
            Route::get('/', [AbTestController::class, 'index']);
            Route::post('/', [AbTestController::class, 'store']);
            Route::get('/{id}', [AbTestController::class, 'show']);
            Route::put('/{id}', [AbTestController::class, 'update']);
            Route::delete('/{id}', [AbTestController::class, 'destroy']);

            // Test controls
            Route::post('/{id}/start', [AbTestController::class, 'start']);
            Route::post('/{id}/pause', [AbTestController::class, 'pause']);
            Route::post('/{id}/resume', [AbTestController::class, 'resume']);
            Route::post('/{id}/complete', [AbTestController::class, 'complete']);
            Route::get('/{id}/statistics', [AbTestController::class, 'statistics']);

            // Variant management
            Route::get('/{id}/variants', [AbTestController::class, 'variants']);
            Route::post('/{id}/variants', [AbTestController::class, 'createVariant']);
            Route::put('/{id}/variants/{variantId}', [AbTestController::class, 'updateVariant']);
            Route::delete('/{id}/variants/{variantId}', [AbTestController::class, 'deleteVariant']);
            Route::post('/{id}/variants/{variantId}/declare-winner', [AbTestController::class, 'declareWinner']);
        });

        // Landing Page Builder Routes
        Route::prefix('landing-pages')->group(function () {
            // Meta routes
            Route::get('/statuses', [LandingPageController::class, 'statuses']);
            Route::get('/thank-you-types', [LandingPageController::class, 'thankYouTypes']);

            // Template routes
            Route::get('/templates', [LandingPageController::class, 'templates']);
            Route::post('/templates', [LandingPageController::class, 'storeTemplate']);
            Route::get('/templates/categories', [LandingPageController::class, 'templateCategories']);
            Route::get('/templates/{templateId}', [LandingPageController::class, 'showTemplate']);
            Route::put('/templates/{templateId}', [LandingPageController::class, 'updateTemplate']);
            Route::delete('/templates/{templateId}', [LandingPageController::class, 'destroyTemplate']);

            // Page CRUD
            Route::get('/', [LandingPageController::class, 'index']);
            Route::post('/', [LandingPageController::class, 'store']);
            Route::get('/{id}', [LandingPageController::class, 'show']);
            Route::put('/{id}', [LandingPageController::class, 'update']);
            Route::delete('/{id}', [LandingPageController::class, 'destroy']);

            // Page actions
            Route::post('/{id}/duplicate', [LandingPageController::class, 'duplicate']);
            Route::post('/{id}/publish', [LandingPageController::class, 'publish']);
            Route::post('/{id}/unpublish', [LandingPageController::class, 'unpublish']);
            Route::post('/{id}/archive', [LandingPageController::class, 'archive']);
            Route::post('/{id}/save-as-template', [LandingPageController::class, 'saveAsTemplate']);
            Route::get('/{id}/analytics', [LandingPageController::class, 'analytics']);

            // Variant management
            Route::get('/{id}/variants', [LandingPageController::class, 'variants']);
            Route::post('/{id}/variants', [LandingPageController::class, 'createVariant']);
            Route::put('/{id}/variants/{variantId}', [LandingPageController::class, 'updateVariant']);
            Route::delete('/{id}/variants/{variantId}', [LandingPageController::class, 'deleteVariant']);
            Route::post('/{id}/variants/{variantId}/declare-winner', [LandingPageController::class, 'declareWinner']);
        });

        // Scenario Planner Routes
        Route::prefix('scenarios')->group(function () {
            // Static routes first (must come before parameterized routes)
            Route::get('/types', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'types']);
            Route::get('/compare', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'compare']);
            Route::get('/gap-analysis', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'gapAnalysis']);
            Route::post('/auto-generate', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'autoGenerate']);

            // CRUD operations
            Route::get('/', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'destroy']);
            Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'duplicate']);

            // Deal operations within a scenario
            Route::get('/{id}/deals', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'deals']);
            Route::put('/{id}/deals/{dealId}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'updateDeal']);
            Route::post('/{id}/commit/{dealId}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'commitDeal']);
            Route::post('/{id}/reset/{dealId}', [\App\Http\Controllers\Api\Scenario\ScenarioController::class, 'resetDeal']);
        });

        // Lookalike Audiences Routes
        Route::prefix('lookalike-audiences')->group(function () {
            // Meta routes
            Route::get('/source-types', [LookalikeController::class, 'sourceTypes']);
            Route::get('/statuses', [LookalikeController::class, 'statuses']);
            Route::get('/criteria-types', [LookalikeController::class, 'criteriaTypes']);
            Route::get('/export-destinations', [LookalikeController::class, 'exportDestinations']);

            // CRUD
            Route::get('/', [LookalikeController::class, 'index']);
            Route::post('/', [LookalikeController::class, 'store']);
            Route::get('/{id}', [LookalikeController::class, 'show']);
            Route::put('/{id}', [LookalikeController::class, 'update']);
            Route::delete('/{id}', [LookalikeController::class, 'destroy']);

            // Build and export
            Route::post('/{id}/build', [LookalikeController::class, 'build']);
            Route::get('/{id}/matches', [LookalikeController::class, 'matches']);
            Route::post('/{id}/export', [LookalikeController::class, 'export']);
        });

        // CMS Routes
        Route::prefix('cms')->group(function () {
            // Pages
            Route::prefix('pages')->group(function () {
                Route::get('/', [CmsPageController::class, 'index']);
                Route::post('/', [CmsPageController::class, 'store']);
                Route::get('/{cmsPage}', [CmsPageController::class, 'show']);
                Route::put('/{cmsPage}', [CmsPageController::class, 'update']);
                Route::delete('/{cmsPage}', [CmsPageController::class, 'destroy']);
                Route::post('/{cmsPage}/publish', [CmsPageController::class, 'publish']);
                Route::post('/{cmsPage}/unpublish', [CmsPageController::class, 'unpublish']);
                Route::post('/{cmsPage}/schedule', [CmsPageController::class, 'schedule']);
                Route::post('/{cmsPage}/duplicate', [CmsPageController::class, 'duplicate']);
                Route::get('/{cmsPage}/versions', [CmsPageController::class, 'versions']);
                Route::post('/{cmsPage}/versions/{versionNumber}/restore', [CmsPageController::class, 'restoreVersion']);
            });

            // Media
            Route::prefix('media')->group(function () {
                Route::get('/stats', [CmsMediaController::class, 'stats']);
                Route::get('/', [CmsMediaController::class, 'index']);
                Route::post('/', [CmsMediaController::class, 'store']);
                Route::get('/{cmsMedia}', [CmsMediaController::class, 'show']);
                Route::put('/{cmsMedia}', [CmsMediaController::class, 'update']);
                Route::delete('/{cmsMedia}', [CmsMediaController::class, 'destroy']);
                Route::post('/{cmsMedia}/move', [CmsMediaController::class, 'move']);
                Route::post('/bulk-delete', [CmsMediaController::class, 'bulkDelete']);
                Route::post('/bulk-move', [CmsMediaController::class, 'bulkMove']);
            });

            // Media Folders
            Route::prefix('media-folders')->group(function () {
                Route::get('/tree', [CmsMediaFolderController::class, 'tree']);
                Route::get('/', [CmsMediaFolderController::class, 'index']);
                Route::post('/', [CmsMediaFolderController::class, 'store']);
                Route::get('/{cmsMediaFolder}', [CmsMediaFolderController::class, 'show']);
                Route::put('/{cmsMediaFolder}', [CmsMediaFolderController::class, 'update']);
                Route::delete('/{cmsMediaFolder}', [CmsMediaFolderController::class, 'destroy']);
            });

            // Templates
            Route::prefix('templates')->group(function () {
                Route::get('/', [CmsTemplateController::class, 'index']);
                Route::post('/', [CmsTemplateController::class, 'store']);
                Route::get('/{cmsTemplate}', [CmsTemplateController::class, 'show']);
                Route::put('/{cmsTemplate}', [CmsTemplateController::class, 'update']);
                Route::delete('/{cmsTemplate}', [CmsTemplateController::class, 'destroy']);
                Route::post('/{cmsTemplate}/duplicate', [CmsTemplateController::class, 'duplicate']);
                Route::post('/{cmsTemplate}/preview', [CmsTemplateController::class, 'preview']);
            });

            // Forms
            Route::prefix('forms')->group(function () {
                Route::get('/', [CmsFormController::class, 'index']);
                Route::post('/', [CmsFormController::class, 'store']);
                Route::get('/{cmsForm}', [CmsFormController::class, 'show']);
                Route::put('/{cmsForm}', [CmsFormController::class, 'update']);
                Route::delete('/{cmsForm}', [CmsFormController::class, 'destroy']);
                Route::post('/{cmsForm}/duplicate', [CmsFormController::class, 'duplicate']);
                Route::get('/{cmsForm}/submissions', [CmsFormController::class, 'submissions']);
                Route::get('/{cmsForm}/submissions/{submission}', [CmsFormController::class, 'submission']);
                Route::delete('/{cmsForm}/submissions/{submission}', [CmsFormController::class, 'deleteSubmission']);
                Route::get('/{cmsForm}/embed-code', [CmsFormController::class, 'embedCode']);
                Route::get('/{cmsForm}/analytics', [CmsFormController::class, 'analytics']);
            });

            // Categories
            Route::prefix('categories')->group(function () {
                Route::get('/tree', [CmsCategoryController::class, 'tree']);
                Route::get('/', [CmsCategoryController::class, 'index']);
                Route::post('/', [CmsCategoryController::class, 'store']);
                Route::get('/{cmsCategory}', [CmsCategoryController::class, 'show']);
                Route::put('/{cmsCategory}', [CmsCategoryController::class, 'update']);
                Route::delete('/{cmsCategory}', [CmsCategoryController::class, 'destroy']);
                Route::post('/reorder', [CmsCategoryController::class, 'reorder']);
            });

            // Menus
            Route::prefix('menus')->group(function () {
                Route::get('/locations', [CmsMenuController::class, 'locations']);
                Route::get('/by-location/{location}', [CmsMenuController::class, 'byLocation']);
                Route::get('/', [CmsMenuController::class, 'index']);
                Route::post('/', [CmsMenuController::class, 'store']);
                Route::get('/{cmsMenu}', [CmsMenuController::class, 'show']);
                Route::put('/{cmsMenu}', [CmsMenuController::class, 'update']);
                Route::delete('/{cmsMenu}', [CmsMenuController::class, 'destroy']);
            });

            // Tags
            Route::prefix('tags')->group(function () {
                Route::get('/popular', [CmsTagController::class, 'popular']);
                Route::post('/merge', [CmsTagController::class, 'merge']);
                Route::get('/', [CmsTagController::class, 'index']);
                Route::post('/', [CmsTagController::class, 'store']);
                Route::get('/{cmsTag}', [CmsTagController::class, 'show']);
                Route::put('/{cmsTag}', [CmsTagController::class, 'update']);
                Route::delete('/{cmsTag}', [CmsTagController::class, 'destroy']);
            });
        });

        // Sales Forecasting Routes
        Route::prefix('forecasts')->group(function () {
            // Forecast data
            Route::get('/', [ForecastController::class, 'summary']);
            Route::get('/deals', [ForecastController::class, 'deals']);
            Route::get('/history', [ForecastController::class, 'history']);
            Route::get('/accuracy', [ForecastController::class, 'accuracy']);

            // Deal forecast management
            Route::put('/deals/{recordId}', [ForecastController::class, 'updateDeal']);
            Route::get('/deals/{recordId}/adjustments', [ForecastController::class, 'adjustments']);
        });

        // Quota & Goal Tracking Routes
        Route::prefix('quota-periods')->group(function () {
            Route::get('/', [QuotaPeriodController::class, 'index']);
            Route::get('/current', [QuotaPeriodController::class, 'current']);
            Route::get('/{quotaPeriod}', [QuotaPeriodController::class, 'show']);
            Route::middleware('permission:pipelines.edit')->group(function () {
                Route::post('/', [QuotaPeriodController::class, 'store']);
                Route::post('/generate', [QuotaPeriodController::class, 'generate']);
                Route::put('/{quotaPeriod}', [QuotaPeriodController::class, 'update']);
                Route::delete('/{quotaPeriod}', [QuotaPeriodController::class, 'destroy']);
            });
        });

        Route::prefix('quotas')->group(function () {
            Route::get('/', [QuotaController::class, 'index']);
            Route::get('/my-progress', [QuotaController::class, 'myProgress']);
            Route::get('/team-progress', [QuotaController::class, 'teamProgress']);
            Route::get('/leaderboard', [QuotaController::class, 'leaderboard']);
            Route::get('/my-position', [QuotaController::class, 'myPosition']);
            Route::get('/metric-types', [QuotaController::class, 'metricTypes']);
            Route::get('/{quota}', [QuotaController::class, 'show']);
            Route::middleware('permission:pipelines.edit')->group(function () {
                Route::post('/', [QuotaController::class, 'store']);
                Route::post('/bulk', [QuotaController::class, 'bulkCreate']);
                Route::post('/refresh-leaderboard', [QuotaController::class, 'refreshLeaderboard']);
                Route::post('/recalculate', [QuotaController::class, 'recalculate']);
                Route::put('/{quota}', [QuotaController::class, 'update']);
                Route::delete('/{quota}', [QuotaController::class, 'destroy']);
            });
        });

        Route::prefix('goals')->group(function () {
            Route::get('/', [GoalController::class, 'index']);
            Route::get('/my-goals', [GoalController::class, 'myGoals']);
            Route::get('/active', [GoalController::class, 'active']);
            Route::get('/stats', [GoalController::class, 'stats']);
            Route::get('/types', [GoalController::class, 'types']);
            Route::get('/{goal}', [GoalController::class, 'show']);
            Route::get('/{goal}/progress', [GoalController::class, 'progress']);
            Route::post('/', [GoalController::class, 'store']);
            Route::put('/{goal}', [GoalController::class, 'update']);
            Route::put('/{goal}/progress', [GoalController::class, 'updateProgress']);
            Route::post('/{goal}/pause', [GoalController::class, 'pause']);
            Route::post('/{goal}/resume', [GoalController::class, 'resume']);
            Route::delete('/{goal}', [GoalController::class, 'destroy']);
        });

        // Duplicate Detection Routes
        Route::prefix('duplicates')->group(function () {
            // Real-time duplicate check
            Route::get('/check', [\App\Http\Controllers\Api\DuplicateController::class, 'check']);
            Route::get('/candidates', [\App\Http\Controllers\Api\DuplicateController::class, 'candidates']);
            Route::get('/stats', [\App\Http\Controllers\Api\DuplicateController::class, 'stats']);
            Route::get('/history', [\App\Http\Controllers\Api\DuplicateController::class, 'history']);

            // Merge and dismiss actions
            Route::post('/merge', [\App\Http\Controllers\Api\DuplicateController::class, 'merge']);
            Route::post('/preview', [\App\Http\Controllers\Api\DuplicateController::class, 'preview']);
            Route::post('/dismiss', [\App\Http\Controllers\Api\DuplicateController::class, 'dismiss']);
            Route::post('/scan', [\App\Http\Controllers\Api\DuplicateController::class, 'scan']);

            // Duplicate rules management (requires permission)
            Route::get('/rules', [\App\Http\Controllers\Api\DuplicateController::class, 'rules']);
            Route::middleware('permission:modules.edit')->group(function () {
                Route::post('/rules', [\App\Http\Controllers\Api\DuplicateController::class, 'createRule']);
                Route::put('/rules/{id}', [\App\Http\Controllers\Api\DuplicateController::class, 'updateRule']);
                Route::delete('/rules/{id}', [\App\Http\Controllers\Api\DuplicateController::class, 'deleteRule']);
            });
        });

        // Web Forms Routes (Authenticated Admin)
        Route::prefix('web-forms')->group(function () {
            // Meta endpoints
            Route::get('/modules', [WebFormController::class, 'modules']);
            Route::get('/field-types', [WebFormController::class, 'fieldTypes']);

            // CRUD operations
            Route::get('/', [WebFormController::class, 'index']);
            Route::post('/', [WebFormController::class, 'store']);
            Route::get('/{id}', [WebFormController::class, 'show']);
            Route::put('/{id}', [WebFormController::class, 'update']);
            Route::delete('/{id}', [WebFormController::class, 'destroy']);

            // Form actions
            Route::post('/{id}/duplicate', [WebFormController::class, 'duplicate']);
            Route::post('/{id}/toggle-active', [WebFormController::class, 'toggleActive']);

            // Submissions and analytics
            Route::get('/{id}/submissions', [WebFormController::class, 'submissions']);
            Route::get('/{id}/analytics', [WebFormController::class, 'analytics']);
            Route::get('/{id}/embed', [WebFormController::class, 'embedCode']);
        });

        // Meeting Scheduler Routes
        Route::prefix('scheduling')->group(function () {
            // Scheduling Pages
            Route::get('/pages', [SchedulingPageController::class, 'index']);
            Route::post('/pages', [SchedulingPageController::class, 'store']);
            Route::get('/pages/check-slug', [SchedulingPageController::class, 'checkSlug']);
            Route::get('/pages/{schedulingPage}', [SchedulingPageController::class, 'show']);
            Route::put('/pages/{schedulingPage}', [SchedulingPageController::class, 'update']);
            Route::delete('/pages/{schedulingPage}', [SchedulingPageController::class, 'destroy']);

            // Meeting Types (nested under pages)
            Route::get('/pages/{schedulingPage}/meeting-types', [MeetingTypeController::class, 'index']);
            Route::post('/pages/{schedulingPage}/meeting-types', [MeetingTypeController::class, 'store']);
            Route::post('/pages/{schedulingPage}/meeting-types/reorder', [MeetingTypeController::class, 'reorder']);
            Route::get('/pages/{schedulingPage}/meeting-types/{meetingType}', [MeetingTypeController::class, 'show']);
            Route::put('/pages/{schedulingPage}/meeting-types/{meetingType}', [MeetingTypeController::class, 'update']);
            Route::delete('/pages/{schedulingPage}/meeting-types/{meetingType}', [MeetingTypeController::class, 'destroy']);

            // Availability
            Route::get('/availability', [AvailabilityController::class, 'index']);
            Route::put('/availability', [AvailabilityController::class, 'update']);
            Route::get('/availability/overrides', [AvailabilityController::class, 'getOverrides']);
            Route::post('/availability/overrides', [AvailabilityController::class, 'storeOverride']);
            Route::delete('/availability/overrides/{override}', [AvailabilityController::class, 'destroyOverride']);

            // Scheduled Meetings (for host)
            Route::get('/meetings', [ScheduledMeetingController::class, 'index']);
            Route::get('/meetings/stats', [ScheduledMeetingController::class, 'stats']);
            Route::get('/meetings/{scheduledMeeting}', [ScheduledMeetingController::class, 'show']);
            Route::put('/meetings/{scheduledMeeting}', [ScheduledMeetingController::class, 'update']);
            Route::post('/meetings/{scheduledMeeting}/cancel', [ScheduledMeetingController::class, 'cancel']);
            Route::post('/meetings/{scheduledMeeting}/complete', [ScheduledMeetingController::class, 'markCompleted']);
            Route::post('/meetings/{scheduledMeeting}/no-show', [ScheduledMeetingController::class, 'markNoShow']);
        });

        // Billing - Products Routes
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/categories', [ProductController::class, 'categories']);
            Route::post('/categories', [ProductController::class, 'storeCategory']);
            Route::put('/categories/{category}', [ProductController::class, 'updateCategory']);
            Route::delete('/categories/{category}', [ProductController::class, 'destroyCategory']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
        });

        // Billing - Quotes Routes
        Route::prefix('quotes')->group(function () {
            Route::get('/', [QuoteController::class, 'index']);
            Route::post('/', [QuoteController::class, 'store']);
            Route::get('/stats', [QuoteController::class, 'stats']);
            Route::get('/templates', [QuoteController::class, 'templates']);
            Route::post('/templates', [QuoteController::class, 'storeTemplate']);
            Route::put('/templates/{template}', [QuoteController::class, 'updateTemplate']);
            Route::delete('/templates/{template}', [QuoteController::class, 'destroyTemplate']);
            Route::get('/{quote}', [QuoteController::class, 'show']);
            Route::put('/{quote}', [QuoteController::class, 'update']);
            Route::delete('/{quote}', [QuoteController::class, 'destroy']);
            Route::post('/{quote}/send', [QuoteController::class, 'send']);
            Route::post('/{quote}/duplicate', [QuoteController::class, 'duplicate']);
            Route::get('/{quote}/pdf', [QuoteController::class, 'pdf']);
            Route::get('/{quote}/download', [QuoteController::class, 'downloadPdf']);
            Route::post('/{quote}/convert-to-invoice', [QuoteController::class, 'convertToInvoice']);
        });

        // Billing - Invoices Routes
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::post('/', [InvoiceController::class, 'store']);
            Route::get('/stats', [InvoiceController::class, 'stats']);
            Route::get('/{invoice}', [InvoiceController::class, 'show']);
            Route::put('/{invoice}', [InvoiceController::class, 'update']);
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
            Route::post('/{invoice}/send', [InvoiceController::class, 'send']);
            Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf']);
            Route::get('/{invoice}/download', [InvoiceController::class, 'downloadPdf']);
            Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel']);
            Route::post('/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
            Route::delete('/{invoice}/payments/{paymentId}', [InvoiceController::class, 'deletePayment']);
        });

        // Meeting Intelligence Routes
        Route::prefix('meetings')->group(function () {
            // Quick access endpoints
            Route::get('/upcoming', [MeetingController::class, 'upcoming']);
            Route::get('/today', [MeetingController::class, 'today']);

            // Analytics endpoints
            Route::get('/analytics/overview', [MeetingController::class, 'analyticsOverview']);
            Route::get('/analytics/heatmap', [MeetingController::class, 'analyticsHeatmap']);
            Route::get('/analytics/by-deal/{dealId}', [MeetingController::class, 'analyticsByDeal']);
            Route::get('/analytics/by-company/{companyId}', [MeetingController::class, 'analyticsByCompany']);
            Route::get('/analytics/stakeholder-coverage/{companyId}', [MeetingController::class, 'stakeholderCoverage']);

            // Insights endpoints
            Route::get('/insights/deal/{dealId}', [MeetingController::class, 'dealInsights']);

            // Meeting CRUD
            Route::get('/', [MeetingController::class, 'index']);
            Route::post('/', [MeetingController::class, 'store']);
            Route::get('/{id}', [MeetingController::class, 'show']);
            Route::put('/{id}', [MeetingController::class, 'update']);
            Route::delete('/{id}', [MeetingController::class, 'destroy']);

            // Meeting actions
            Route::post('/{id}/link-deal', [MeetingController::class, 'linkToDeal']);
            Route::post('/{id}/log-outcome', [MeetingController::class, 'logOutcome']);
        });

        // Live Chat Widget Routes
        Route::prefix('chat')->group(function () {
            // Widget management
            Route::prefix('widgets')->group(function () {
                Route::get('/', [ChatWidgetController::class, 'index']);
                Route::post('/', [ChatWidgetController::class, 'store']);
                Route::get('/{id}', [ChatWidgetController::class, 'show']);
                Route::put('/{id}', [ChatWidgetController::class, 'update']);
                Route::delete('/{id}', [ChatWidgetController::class, 'destroy']);
                Route::get('/{id}/embed', [ChatWidgetController::class, 'embedCode']);
                Route::get('/{id}/analytics', [ChatWidgetController::class, 'analytics']);
            });

            // Conversation management
            Route::prefix('conversations')->group(function () {
                Route::get('/', [ChatConversationController::class, 'index']);
                Route::get('/{id}', [ChatConversationController::class, 'show']);
                Route::put('/{id}', [ChatConversationController::class, 'update']);
                Route::post('/{id}/assign', [ChatConversationController::class, 'assign']);
                Route::post('/{id}/close', [ChatConversationController::class, 'close']);
                Route::post('/{id}/reopen', [ChatConversationController::class, 'reopen']);
                Route::get('/{id}/messages', [ChatConversationController::class, 'messages']);
                Route::post('/{id}/messages', [ChatConversationController::class, 'sendMessage']);
            });

            // Agent management
            Route::prefix('agents')->group(function () {
                Route::get('/status', [ChatAgentController::class, 'getStatus']);
                Route::put('/status', [ChatAgentController::class, 'updateStatus']);
                Route::get('/', [ChatAgentController::class, 'listAgents']);
                Route::get('/performance', [ChatAgentController::class, 'agentPerformance']);
            });

            // Canned responses
            Route::prefix('canned-responses')->group(function () {
                Route::get('/', [ChatAgentController::class, 'listCannedResponses']);
                Route::get('/search', [ChatAgentController::class, 'searchCannedResponses']);
                Route::post('/', [ChatAgentController::class, 'storeCannedResponse']);
                Route::put('/{id}', [ChatAgentController::class, 'updateCannedResponse']);
                Route::delete('/{id}', [ChatAgentController::class, 'destroyCannedResponse']);
                Route::post('/{id}/use', [ChatAgentController::class, 'useCannedResponse']);
            });
        });

        // WhatsApp Integration Routes
        Route::prefix('whatsapp')->group(function () {
            // Connection management
            Route::prefix('connections')->group(function () {
                Route::get('/', [WhatsappConnectionController::class, 'index']);
                Route::post('/', [WhatsappConnectionController::class, 'store']);
                Route::get('/{connection}', [WhatsappConnectionController::class, 'show']);
                Route::put('/{connection}', [WhatsappConnectionController::class, 'update']);
                Route::delete('/{connection}', [WhatsappConnectionController::class, 'destroy']);
                Route::post('/{connection}/verify', [WhatsappConnectionController::class, 'verify']);
                Route::get('/{connection}/webhook-config', [WhatsappConnectionController::class, 'getWebhookConfig']);
            });

            // Template management
            Route::prefix('templates')->group(function () {
                Route::get('/', [WhatsappTemplateController::class, 'index']);
                Route::post('/', [WhatsappTemplateController::class, 'store']);
                Route::get('/{template}', [WhatsappTemplateController::class, 'show']);
                Route::put('/{template}', [WhatsappTemplateController::class, 'update']);
                Route::delete('/{template}', [WhatsappTemplateController::class, 'destroy']);
                Route::post('/{template}/submit', [WhatsappTemplateController::class, 'submit']);
                Route::post('/{template}/sync-status', [WhatsappTemplateController::class, 'syncStatus']);
                Route::post('/{template}/preview', [WhatsappTemplateController::class, 'preview']);
            });

            // Conversation management
            Route::prefix('conversations')->group(function () {
                Route::get('/', [WhatsappConversationController::class, 'index']);
                Route::get('/by-phone', [WhatsappConversationController::class, 'findByPhone']);
                Route::post('/start', [WhatsappConversationController::class, 'startConversation']);
                Route::get('/{conversation}', [WhatsappConversationController::class, 'show']);
                Route::get('/{conversation}/messages', [WhatsappConversationController::class, 'messages']);
                Route::post('/{conversation}/messages', [WhatsappConversationController::class, 'sendMessage']);
                Route::post('/{conversation}/assign', [WhatsappConversationController::class, 'assign']);
                Route::post('/{conversation}/close', [WhatsappConversationController::class, 'close']);
                Route::post('/{conversation}/reopen', [WhatsappConversationController::class, 'reopen']);
                Route::post('/{conversation}/link-record', [WhatsappConversationController::class, 'linkToRecord']);
            });
        });

        // SMS Automation Routes
        Route::prefix('sms')->group(function () {
            // Connection management
            Route::prefix('connections')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'store']);
                Route::get('/{smsConnection}', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'show']);
                Route::put('/{smsConnection}', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'update']);
                Route::delete('/{smsConnection}', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'destroy']);
                Route::post('/{smsConnection}/verify', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'verify']);
                Route::get('/{smsConnection}/stats', [\App\Http\Controllers\Api\Sms\SmsConnectionController::class, 'stats']);
            });

            // Template management
            Route::prefix('templates')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'store']);
                Route::get('/{smsTemplate}', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'show']);
                Route::put('/{smsTemplate}', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'update']);
                Route::delete('/{smsTemplate}', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'destroy']);
                Route::post('/{smsTemplate}/preview', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'preview']);
                Route::post('/{smsTemplate}/duplicate', [\App\Http\Controllers\Api\Sms\SmsTemplateController::class, 'duplicate']);
            });

            // Message management
            Route::prefix('messages')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Sms\SmsMessageController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Sms\SmsMessageController::class, 'store']);
                Route::get('/conversation', [\App\Http\Controllers\Api\Sms\SmsMessageController::class, 'conversation']);
                Route::get('/for-record', [\App\Http\Controllers\Api\Sms\SmsMessageController::class, 'forRecord']);
                Route::get('/{smsMessage}', [\App\Http\Controllers\Api\Sms\SmsMessageController::class, 'show']);
            });

            // Campaign management
            Route::prefix('campaigns')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'store']);
                Route::get('/{smsCampaign}', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'show']);
                Route::put('/{smsCampaign}', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'update']);
                Route::delete('/{smsCampaign}', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'destroy']);
                Route::post('/{smsCampaign}/schedule', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'schedule']);
                Route::post('/{smsCampaign}/send-now', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'sendNow']);
                Route::post('/{smsCampaign}/pause', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'pause']);
                Route::post('/{smsCampaign}/cancel', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'cancel']);
                Route::get('/{smsCampaign}/preview', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'preview']);
                Route::get('/{smsCampaign}/recipients', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'recipients']);
                Route::get('/{smsCampaign}/stats', [\App\Http\Controllers\Api\Sms\SmsCampaignController::class, 'stats']);
            });

            // Opt-out management
            Route::prefix('opt-outs')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'store']);
                Route::get('/check', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'check']);
                Route::post('/opt-in', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'optIn']);
                Route::post('/bulk', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'bulkOptOut']);
                Route::delete('/{smsOptOut}', [\App\Http\Controllers\Api\Sms\SmsOptOutController::class, 'destroy']);
            });
        });

        // Shared Team Inboxes Routes
        Route::prefix('inboxes')->group(function () {
            // Inbox management
            Route::get('/', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'store']);
            Route::get('/{sharedInbox}', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'show']);
            Route::put('/{sharedInbox}', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'update']);
            Route::delete('/{sharedInbox}', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'destroy']);
            Route::post('/{sharedInbox}/verify', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'verify']);
            Route::post('/{sharedInbox}/sync', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'sync']);
            Route::get('/{sharedInbox}/stats', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'stats']);

            // Member management
            Route::get('/{sharedInbox}/members', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'members']);
            Route::post('/{sharedInbox}/members', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'addMember']);
            Route::put('/{sharedInbox}/members/{member}', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'updateMember']);
            Route::delete('/{sharedInbox}/members/{member}', [\App\Http\Controllers\Api\Inbox\SharedInboxController::class, 'removeMember']);

            // Rules management
            Route::get('/{sharedInbox}/rules', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'index']);
            Route::post('/{sharedInbox}/rules', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'store']);
            Route::get('/{sharedInbox}/rules/{rule}', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'show']);
            Route::put('/{sharedInbox}/rules/{rule}', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'update']);
            Route::delete('/{sharedInbox}/rules/{rule}', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'destroy']);
            Route::post('/{sharedInbox}/rules/reorder', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'reorder']);
            Route::post('/{sharedInbox}/rules/{rule}/toggle', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'toggle']);
        });

        // Inbox meta routes
        Route::get('/inbox-rules/fields', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'availableFields']);
        Route::get('/inbox-rules/operators', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'availableOperators']);
        Route::get('/inbox-rules/actions', [\App\Http\Controllers\Api\Inbox\InboxRuleController::class, 'availableActions']);

        // Conversation management
        Route::prefix('inbox-conversations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'index']);
            Route::get('/{inboxConversation}', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'show']);
            Route::put('/{inboxConversation}', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'update']);
            Route::post('/{inboxConversation}/reply', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'reply']);
            Route::post('/{inboxConversation}/note', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'note']);
            Route::post('/{inboxConversation}/assign', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'assign']);
            Route::post('/{inboxConversation}/resolve', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'resolve']);
            Route::post('/{inboxConversation}/reopen', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'reopen']);
            Route::post('/{inboxConversation}/close', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'close']);
            Route::post('/{inboxConversation}/spam', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'spam']);
            Route::post('/{inboxConversation}/star', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'star']);
            Route::post('/{inboxConversation}/tags', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'addTag']);
            Route::delete('/{inboxConversation}/tags', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'removeTag']);
            Route::post('/{inboxConversation}/merge', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'merge']);

            // Bulk operations
            Route::post('/bulk-assign', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'bulkAssign']);
            Route::post('/bulk-resolve', [\App\Http\Controllers\Api\Inbox\InboxConversationController::class, 'bulkResolve']);
        });

        // Canned responses management
        Route::prefix('inbox-canned-responses')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'store']);
            Route::get('/categories', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'categories']);
            Route::get('/by-shortcut', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'findByShortcut']);
            Route::get('/{inboxCannedResponse}', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'show']);
            Route::put('/{inboxCannedResponse}', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'update']);
            Route::delete('/{inboxCannedResponse}', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'destroy']);
            Route::post('/{inboxCannedResponse}/render', [\App\Http\Controllers\Api\Inbox\InboxCannedResponseController::class, 'render']);
        });

        // Team Chat Integration Routes (Slack/Teams)
        Route::prefix('team-chat')->group(function () {
            // Connection management
            Route::prefix('connections')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'store']);
                Route::get('/{teamChatConnection}', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'show']);
                Route::put('/{teamChatConnection}', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'update']);
                Route::delete('/{teamChatConnection}', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'destroy']);
                Route::post('/{teamChatConnection}/verify', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'verify']);
                Route::post('/{teamChatConnection}/sync-channels', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'syncChannels']);
                Route::post('/{teamChatConnection}/sync-users', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'syncUsers']);
                Route::get('/{teamChatConnection}/channels', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'channels']);
                Route::get('/{teamChatConnection}/user-mappings', [\App\Http\Controllers\Api\TeamChat\TeamChatConnectionController::class, 'userMappings']);
            });

            // Notification management
            Route::prefix('notifications')->group(function () {
                Route::get('/events', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'events']);
                Route::get('/', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'store']);
                Route::get('/{teamChatNotification}', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'show']);
                Route::put('/{teamChatNotification}', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'update']);
                Route::delete('/{teamChatNotification}', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'destroy']);
                Route::post('/{teamChatNotification}/test', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'test']);
                Route::post('/{teamChatNotification}/duplicate', [\App\Http\Controllers\Api\TeamChat\TeamChatNotificationController::class, 'duplicate']);
            });

            // Message management
            Route::prefix('messages')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\TeamChat\TeamChatMessageController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\TeamChat\TeamChatMessageController::class, 'store']);
                Route::get('/for-record', [\App\Http\Controllers\Api\TeamChat\TeamChatMessageController::class, 'forRecord']);
                Route::get('/{teamChatMessage}', [\App\Http\Controllers\Api\TeamChat\TeamChatMessageController::class, 'show']);
                Route::post('/{teamChatMessage}/retry', [\App\Http\Controllers\Api\TeamChat\TeamChatMessageController::class, 'retry']);
            });
        });

        // Marketing Campaign Routes
        Route::prefix('campaigns')->group(function () {
            // Meta routes
            Route::get('/types', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'types']);
            Route::get('/statuses', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'statuses']);

            // Template management
            Route::prefix('templates')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'templates']);
                Route::post('/', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'storeTemplate']);
                Route::get('/{templateId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'showTemplate']);
                Route::put('/{templateId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'updateTemplate']);
                Route::delete('/{templateId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'destroyTemplate']);
            });

            // Campaign CRUD
            Route::get('/', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'destroy']);

            // Campaign actions
            Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'duplicate']);
            Route::post('/{id}/start', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'start']);
            Route::post('/{id}/pause', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'pause']);
            Route::post('/{id}/complete', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'complete']);
            Route::post('/{id}/cancel', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'cancel']);

            // Campaign analytics
            Route::get('/{id}/analytics', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'analytics']);
            Route::get('/{id}/metrics', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'metrics']);

            // Campaign audiences
            Route::post('/{id}/audiences', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'addAudience']);
            Route::put('/{id}/audiences/{audienceId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'updateAudience']);
            Route::delete('/{id}/audiences/{audienceId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'deleteAudience']);
            Route::get('/{id}/audiences/{audienceId}/preview', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'previewAudience']);
            Route::post('/{id}/audiences/{audienceId}/refresh', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'refreshAudience']);

            // Campaign assets
            Route::post('/{id}/assets', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'addAsset']);
            Route::put('/{id}/assets/{assetId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'updateAsset']);
            Route::delete('/{id}/assets/{assetId}', [\App\Http\Controllers\Api\Campaign\CampaignController::class, 'deleteAsset']);
        });

        // Landing Page Builder Routes
        Route::prefix('landing-pages')->group(function () {
            // Meta routes
            Route::get('/statuses', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'statuses']);
            Route::get('/thank-you-types', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'thankYouTypes']);

            // Template management
            Route::prefix('templates')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'templates']);
                Route::get('/categories', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'templateCategories']);
                Route::post('/', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'storeTemplate']);
                Route::get('/{templateId}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'showTemplate']);
                Route::put('/{templateId}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'updateTemplate']);
                Route::delete('/{templateId}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'destroyTemplate']);
            });

            // Page CRUD
            Route::get('/', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'destroy']);

            // Page actions
            Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'duplicate']);
            Route::post('/{id}/publish', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'publish']);
            Route::post('/{id}/unpublish', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'unpublish']);
            Route::post('/{id}/archive', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'archive']);
            Route::post('/{id}/save-as-template', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'saveAsTemplate']);

            // Page analytics
            Route::get('/{id}/analytics', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'analytics']);

            // Page variants (A/B testing)
            Route::get('/{id}/variants', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'variants']);
            Route::post('/{id}/variants', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'createVariant']);
            Route::put('/{id}/variants/{variantId}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'updateVariant']);
            Route::delete('/{id}/variants/{variantId}', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'deleteVariant']);
            Route::post('/{id}/variants/{variantId}/declare-winner', [\App\Http\Controllers\Api\LandingPage\LandingPageController::class, 'declareWinner']);
        });

        // Call Recording & Telephony Routes
        Route::prefix('calls')->group(function () {
            // Provider management
            Route::prefix('providers')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'store']);
                Route::get('/{callProvider}', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'show']);
                Route::put('/{callProvider}', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'update']);
                Route::delete('/{callProvider}', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'destroy']);
                Route::post('/{callProvider}/verify', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'verify']);
                Route::post('/{callProvider}/toggle-active', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'toggleActive']);
                Route::get('/{callProvider}/phone-numbers', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'listPhoneNumbers']);
                Route::post('/{callProvider}/sync-phone-number', [\App\Http\Controllers\Api\Call\CallProviderController::class, 'syncPhoneNumber']);
            });

            // Call queues
            Route::prefix('queues')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'store']);
                Route::get('/my-status', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'myStatus']);
                Route::put('/my-status', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'setMyStatus']);
                Route::get('/{callQueue}', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'show']);
                Route::put('/{callQueue}', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'update']);
                Route::delete('/{callQueue}', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'destroy']);
                Route::post('/{callQueue}/toggle-active', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'toggleActive']);
                Route::get('/{callQueue}/stats', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'stats']);
                Route::post('/{callQueue}/reset-daily-stats', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'resetDailyStats']);

                // Queue members
                Route::post('/{callQueue}/members', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'addMember']);
                Route::delete('/{callQueue}/members/{userId}', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'removeMember']);
                Route::put('/{callQueue}/members/{userId}', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'updateMember']);
                Route::put('/{callQueue}/members/{userId}/status', [\App\Http\Controllers\Api\Call\CallQueueController::class, 'setMemberStatus']);
            });

            // Call management
            Route::get('/', [\App\Http\Controllers\Api\Call\CallController::class, 'index']);
            Route::post('/initiate', [\App\Http\Controllers\Api\Call\CallController::class, 'initiate']);
            Route::get('/stats', [\App\Http\Controllers\Api\Call\CallController::class, 'stats']);
            Route::get('/{call}', [\App\Http\Controllers\Api\Call\CallController::class, 'show']);
            Route::delete('/{call}', [\App\Http\Controllers\Api\Call\CallController::class, 'destroy']);
            Route::post('/{call}/end', [\App\Http\Controllers\Api\Call\CallController::class, 'end']);
            Route::post('/{call}/transfer', [\App\Http\Controllers\Api\Call\CallController::class, 'transfer']);
            Route::post('/{call}/hold', [\App\Http\Controllers\Api\Call\CallController::class, 'hold']);
            Route::post('/{call}/mute', [\App\Http\Controllers\Api\Call\CallController::class, 'mute']);
            Route::post('/{call}/log-outcome', [\App\Http\Controllers\Api\Call\CallController::class, 'logOutcome']);
            Route::post('/{call}/link-contact', [\App\Http\Controllers\Api\Call\CallController::class, 'linkContact']);

            // Transcription
            Route::post('/{call}/transcribe', [\App\Http\Controllers\Api\Call\CallController::class, 'transcribe']);
            Route::get('/{call}/transcription', [\App\Http\Controllers\Api\Call\CallController::class, 'getTranscription']);
        });

        // Smart Cadences Routes
        Route::prefix('cadences')->group(function () {
            // Meta routes
            Route::get('/statuses', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'statuses']);
            Route::get('/channels', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'channels']);

            // Template management
            Route::get('/templates', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'templates']);
            Route::post('/from-template', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'createFromTemplate']);

            // Cadence CRUD
            Route::get('/', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'destroy']);

            // Cadence actions
            Route::post('/{id}/activate', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'activate']);
            Route::post('/{id}/pause', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'pause']);
            Route::post('/{id}/archive', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'archive']);
            Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'duplicate']);
            Route::post('/{id}/save-as-template', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'saveAsTemplate']);

            // Cadence analytics
            Route::get('/{id}/analytics', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'analytics']);

            // Cadence steps
            Route::post('/{cadenceId}/steps', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'addStep']);
            Route::put('/{cadenceId}/steps/{stepId}', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'updateStep']);
            Route::delete('/{cadenceId}/steps/{stepId}', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'deleteStep']);
            Route::post('/{cadenceId}/steps/reorder', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'reorderSteps']);

            // Cadence enrollments
            Route::get('/{cadenceId}/enrollments', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'enrollments']);
            Route::post('/{cadenceId}/enroll', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'enroll']);
            Route::post('/{cadenceId}/bulk-enroll', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'bulkEnroll']);
            Route::post('/{cadenceId}/enrollments/{enrollmentId}/unenroll', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'unenroll']);
            Route::post('/{cadenceId}/enrollments/{enrollmentId}/pause', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'pauseEnrollment']);
            Route::post('/{cadenceId}/enrollments/{enrollmentId}/resume', [\App\Http\Controllers\Api\Cadence\CadenceController::class, 'resumeEnrollment']);
        });

        // Video Conferencing Routes
        Route::prefix('video')->group(function () {
            // Provider management
            Route::prefix('providers')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'store']);
                Route::get('/{videoProvider}', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'show']);
                Route::put('/{videoProvider}', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'update']);
                Route::delete('/{videoProvider}', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'destroy']);
                Route::post('/{videoProvider}/verify', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'verify']);
                Route::post('/{videoProvider}/toggle-active', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'toggleActive']);
                Route::get('/{videoProvider}/oauth-url', [\App\Http\Controllers\Api\Video\VideoProviderController::class, 'getOAuthUrl']);
            });

            // Meeting management
            Route::prefix('meetings')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'store']);
                Route::get('/upcoming', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'upcoming']);
                Route::get('/stats', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'stats']);
                Route::get('/{videoMeeting}', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'show']);
                Route::put('/{videoMeeting}', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'update']);
                Route::post('/{videoMeeting}/cancel', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'cancel']);
                Route::post('/{videoMeeting}/end', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'end']);
                Route::post('/{videoMeeting}/sync-recordings', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'syncRecordings']);
                Route::post('/{videoMeeting}/sync-participants', [\App\Http\Controllers\Api\Video\VideoMeetingController::class, 'syncParticipants']);

                // Participants
                Route::get('/{videoMeeting}/participants', [\App\Http\Controllers\Api\Video\VideoParticipantController::class, 'index']);
                Route::post('/{videoMeeting}/participants', [\App\Http\Controllers\Api\Video\VideoParticipantController::class, 'store']);
                Route::post('/{videoMeeting}/participants/bulk', [\App\Http\Controllers\Api\Video\VideoParticipantController::class, 'bulkAdd']);
                Route::delete('/{videoMeeting}/participants/{participant}', [\App\Http\Controllers\Api\Video\VideoParticipantController::class, 'destroy']);

                // Recordings
                Route::get('/{videoMeeting}/recordings', [\App\Http\Controllers\Api\Video\VideoRecordingController::class, 'index']);
                Route::get('/{videoMeeting}/recordings/{recording}', [\App\Http\Controllers\Api\Video\VideoRecordingController::class, 'show']);
                Route::delete('/{videoMeeting}/recordings/{recording}', [\App\Http\Controllers\Api\Video\VideoRecordingController::class, 'destroy']);
                Route::get('/{videoMeeting}/recordings/{recording}/transcript', [\App\Http\Controllers\Api\Video\VideoRecordingController::class, 'getTranscript']);
            });

            // All recordings list
            Route::get('/recordings', [\App\Http\Controllers\Api\Video\VideoRecordingController::class, 'listAll']);
        });

        // Plugin License & Billing Routes
        Route::prefix('billing')->group(function () {
            // License state
            Route::get('/license', [LicenseController::class, 'show']);
            Route::get('/license/plugin/{pluginSlug}', [LicenseController::class, 'checkPlugin']);
            Route::get('/license/feature/{featureKey}', [LicenseController::class, 'checkFeature']);

            // Usage tracking
            Route::get('/usage', [LicenseController::class, 'usage']);
            Route::get('/usage/{metric}', [LicenseController::class, 'usageMetric']);

            // Subscription management
            Route::get('/subscription', [SubscriptionController::class, 'show']);
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::put('/subscription', [SubscriptionController::class, 'update']);
            Route::delete('/subscription', [SubscriptionController::class, 'cancel']);

            // Plugin management
            Route::get('/plugins', [BillingPluginController::class, 'index']);
            Route::get('/plugins/licenses', [BillingPluginController::class, 'licenses']);
            Route::get('/plugins/{slug}', [BillingPluginController::class, 'show']);
            Route::post('/plugins/{slug}/activate', [BillingPluginController::class, 'activate']);
            Route::delete('/plugins/{slug}', [BillingPluginController::class, 'deactivate']);

            // Bundle management
            Route::get('/bundles', [BundleController::class, 'index']);
            Route::get('/bundles/{slug}', [BundleController::class, 'show']);
            Route::post('/bundles/{slug}/activate', [BundleController::class, 'activate']);
            Route::delete('/bundles/{slug}', [BundleController::class, 'deactivate']);
        });

        // Support Ticketing Routes
        Route::prefix('support')->group(function () {
            // Ticket Categories
            Route::prefix('categories')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'store']);
                Route::get('/{id}', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'show']);
                Route::put('/{id}', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'destroy']);
                Route::post('/reorder', [\App\Http\Controllers\Api\Support\TicketCategoryController::class, 'reorder']);
            });

            // Tickets
            Route::prefix('tickets')->group(function () {
                // Meta routes
                Route::get('/statuses', [\App\Http\Controllers\Api\Support\TicketController::class, 'statuses']);
                Route::get('/priorities', [\App\Http\Controllers\Api\Support\TicketController::class, 'priorities']);
                Route::get('/channels', [\App\Http\Controllers\Api\Support\TicketController::class, 'channels']);
                Route::get('/stats', [\App\Http\Controllers\Api\Support\TicketController::class, 'stats']);

                // CRUD
                Route::get('/', [\App\Http\Controllers\Api\Support\TicketController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Support\TicketController::class, 'store']);
                Route::get('/{id}', [\App\Http\Controllers\Api\Support\TicketController::class, 'show']);
                Route::put('/{id}', [\App\Http\Controllers\Api\Support\TicketController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Api\Support\TicketController::class, 'destroy']);

                // Actions
                Route::post('/{id}/reply', [\App\Http\Controllers\Api\Support\TicketController::class, 'reply']);
                Route::post('/{id}/assign', [\App\Http\Controllers\Api\Support\TicketController::class, 'assign']);
                Route::post('/{id}/resolve', [\App\Http\Controllers\Api\Support\TicketController::class, 'resolve']);
                Route::post('/{id}/close', [\App\Http\Controllers\Api\Support\TicketController::class, 'close']);
                Route::post('/{id}/reopen', [\App\Http\Controllers\Api\Support\TicketController::class, 'reopen']);
                Route::post('/{id}/escalate', [\App\Http\Controllers\Api\Support\TicketController::class, 'escalate']);
                Route::post('/{id}/merge', [\App\Http\Controllers\Api\Support\TicketController::class, 'merge']);
            });

            // Knowledge Base
            Route::prefix('kb')->group(function () {
                // Search
                Route::get('/search', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'search']);

                // Categories
                Route::get('/categories', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'categories']);
                Route::post('/categories', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'storeCategory']);
                Route::get('/categories/{slug}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'category']);
                Route::put('/categories/{id}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'updateCategory']);
                Route::delete('/categories/{id}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'destroyCategory']);

                // Articles
                Route::get('/articles', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'articles']);
                Route::post('/articles', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'storeArticle']);
                Route::get('/articles/{slug}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'article']);
                Route::put('/articles/{id}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'updateArticle']);
                Route::delete('/articles/{id}', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'destroyArticle']);
                Route::post('/articles/{id}/publish', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'publishArticle']);
                Route::post('/articles/{id}/unpublish', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'unpublishArticle']);
                Route::post('/articles/{id}/feedback', [\App\Http\Controllers\Api\Support\KnowledgeBaseController::class, 'articleFeedback']);
            });
        });

        // Customer Portal Admin Routes
        Route::prefix('portal-admin')->group(function () {
            // Portal Users
            Route::get('/users', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'users']);
            Route::get('/users/{id}', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'user']);
            Route::put('/users/{id}', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'updateUser']);
            Route::post('/users/{id}/deactivate', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'deactivateUser']);
            Route::post('/users/{id}/activate', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'activateUser']);

            // Invitations
            Route::get('/invitations', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'invitations']);
            Route::post('/invitations', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'createInvitation']);
            Route::post('/invitations/{id}/resend', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'resendInvitation']);
            Route::post('/invitations/{id}/cancel', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'cancelInvitation']);

            // Announcements
            Route::get('/announcements', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'announcements']);
            Route::post('/announcements', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'createAnnouncement']);
            Route::put('/announcements/{id}', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'updateAnnouncement']);
            Route::delete('/announcements/{id}', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'deleteAnnouncement']);

            // Document Sharing
            Route::post('/share-document', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'shareDocument']);

            // Analytics
            Route::get('/analytics', [\App\Http\Controllers\Api\Portal\PortalAdminController::class, 'activityAnalytics']);
        });

        // Renewal Management - Contracts
        Route::prefix('contracts')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'store']);
            Route::get('/expiring', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'expiring']);
            Route::get('/for-record', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'forRecord']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Renewal\ContractController::class, 'destroy']);
        });

        // Renewal Management - Renewals
        Route::prefix('renewals')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'store']);
            Route::get('/pipeline', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'pipeline']);
            Route::get('/forecast', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'forecast']);
            Route::post('/generate', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'generate']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'show']);
            Route::post('/{id}/start', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'start']);
            Route::post('/{id}/win', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'win']);
            Route::post('/{id}/lose', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'lose']);
            Route::post('/{id}/activities', [\App\Http\Controllers\Api\Renewal\RenewalController::class, 'addActivity']);
        });

        // Renewal Management - Health Scores
        Route::prefix('health-scores')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'index']);
            Route::get('/summary', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'summary']);
            Route::get('/at-risk', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'atRisk']);
            Route::post('/calculate', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'calculate']);
            Route::post('/recalculate-all', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'recalculateAll']);
            Route::get('/{module}/{recordId}', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'show']);
            Route::put('/{id}/notes', [\App\Http\Controllers\Api\Renewal\HealthScoreController::class, 'updateNotes']);
        });

        // Onboarding Playbooks
        Route::prefix('playbooks')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'destroy']);
            Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'duplicate']);

            // Phases
            Route::post('/{playbookId}/phases', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'addPhase']);
            Route::put('/{playbookId}/phases/{phaseId}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'updatePhase']);
            Route::delete('/{playbookId}/phases/{phaseId}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'deletePhase']);

            // Tasks
            Route::post('/{playbookId}/tasks', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'addTask']);
            Route::put('/{playbookId}/tasks/{taskId}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'updateTask']);
            Route::delete('/{playbookId}/tasks/{taskId}', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'deleteTask']);
            Route::post('/{playbookId}/tasks/reorder', [\App\Http\Controllers\Api\Playbook\PlaybookController::class, 'reorderTasks']);
        });

        // Playbook Instances
        Route::prefix('playbook-instances')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'index']);
            Route::post('/start', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'start']);
            Route::get('/for-record', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'forRecord']);
            Route::get('/my-tasks', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'myTasks']);
            Route::get('/{id}', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'show']);
            Route::post('/{id}/pause', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'pause']);
            Route::post('/{id}/resume', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'resume']);
            Route::post('/{id}/cancel', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'cancel']);
            Route::get('/{id}/tasks', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'tasks']);
            Route::get('/{id}/activities', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'activities']);

            // Task operations
            Route::post('/{instanceId}/tasks/{taskInstanceId}/start', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'startTask']);
            Route::post('/{instanceId}/tasks/{taskInstanceId}/complete', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'completeTask']);
            Route::post('/{instanceId}/tasks/{taskInstanceId}/skip', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'skipTask']);
            Route::post('/{instanceId}/tasks/{taskInstanceId}/reassign', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'reassignTask']);
            Route::post('/{instanceId}/tasks/{taskInstanceId}/checklist', [\App\Http\Controllers\Api\Playbook\PlaybookInstanceController::class, 'updateTaskChecklist']);
        });
    });

    // Public email tracking routes (no auth required)
    Route::get('/track/open/{trackingId}', [EmailTrackingController::class, 'trackOpen'])
        ->name('email.track.open');
    Route::get('/track/click/{trackingId}/{url}', [EmailTrackingController::class, 'trackClick'])
        ->name('email.track.click');

    // Public incoming webhook endpoint (no auth required - uses token, rate limited)
    Route::post('/webhooks/incoming/{token}', [IncomingWebhookController::class, 'receive'])
        ->middleware('throttle:webhooks')
        ->name('webhooks.incoming.receive');

    // Public Web Forms Routes (no auth required, rate limited)
    Route::prefix('forms')->group(function () {
        Route::get('/{slug}', [WebFormPublicController::class, 'show']);
        Route::get('/{slug}/render', [WebFormPublicController::class, 'render']);
        Route::post('/{slug}/submit', [WebFormPublicController::class, 'submit'])->middleware('throttle:public-forms');
        Route::get('/{slug}/embed.js', [WebFormPublicController::class, 'embedScript']);
    });

    // Public Landing Page Routes (no auth required, rate limited)
    Route::prefix('p')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{slug}', [\App\Http\Controllers\Api\LandingPage\PublicLandingPageController::class, 'show']);
        Route::get('/{slug}/thank-you', [\App\Http\Controllers\Api\LandingPage\PublicLandingPageController::class, 'thankYou']);
        Route::post('/{slug}/engagement', [\App\Http\Controllers\Api\LandingPage\PublicLandingPageController::class, 'trackEngagement']);
        Route::post('/{slug}/conversion', [\App\Http\Controllers\Api\LandingPage\PublicLandingPageController::class, 'trackConversion']);
    });

    // Public Scheduling Routes (no auth required, rate limited)
    Route::prefix('schedule')->group(function () {
        // Get scheduling page info
        Route::get('/{slug}', [PublicBookingController::class, 'getPage']);
        Route::get('/{slug}/{type}', [PublicBookingController::class, 'getMeetingType']);

        // Get availability
        Route::get('/{slug}/{type}/dates', [PublicBookingController::class, 'getAvailableDates']);
        Route::get('/{slug}/{type}/slots', [PublicBookingController::class, 'getAvailableSlots']);

        // Book meeting (rate limited)
        Route::post('/{slug}/{type}/book', [PublicBookingController::class, 'book'])
            ->middleware('throttle:public-forms');

        // Manage booking by token
        Route::get('/manage/{token}', [PublicBookingController::class, 'getMeetingByToken']);
        Route::post('/cancel/{token}', [PublicBookingController::class, 'cancelByToken'])
            ->middleware('throttle:public-forms');
        Route::post('/reschedule/{token}', [PublicBookingController::class, 'rescheduleByToken'])
            ->middleware('throttle:public-forms');
    });

    // Public Quote Routes (no auth required, rate limited)
    Route::prefix('quote')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{token}', [PublicQuoteController::class, 'show']);
        Route::post('/{token}/accept', [PublicQuoteController::class, 'accept']);
        Route::post('/{token}/reject', [PublicQuoteController::class, 'reject']);
        Route::get('/{token}/pdf', [PublicQuoteController::class, 'pdf']);
    });

    // Public Signature Routes (no auth required, rate limited)
    Route::prefix('sign')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{uuid}', [PublicSignatureController::class, 'show']);
        Route::post('/{uuid}/sign', [PublicSignatureController::class, 'sign']);
        Route::post('/{uuid}/decline', [PublicSignatureController::class, 'decline']);
        Route::get('/{uuid}/download', [PublicSignatureController::class, 'downloadDocument']);
    });

    // Public Proposal Routes (no auth required, rate limited)
    Route::prefix('proposal')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{uuid}', [PublicProposalController::class, 'show']);
        Route::post('/{uuid}/track-view', [PublicProposalController::class, 'trackView']);
        Route::post('/{uuid}/update-session', [PublicProposalController::class, 'updateViewSession']);
        Route::post('/{uuid}/items/{itemId}/toggle', [PublicProposalController::class, 'toggleItem']);
        Route::post('/{uuid}/accept', [PublicProposalController::class, 'accept']);
        Route::post('/{uuid}/reject', [PublicProposalController::class, 'reject']);
        Route::get('/{uuid}/comments', [PublicProposalController::class, 'comments']);
        Route::post('/{uuid}/comments', [PublicProposalController::class, 'addComment']);
    });

    // Public Deal Rooms Routes (token-based, rate limited)
    Route::prefix('rooms')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{slug}', [\App\Http\Controllers\Api\DealRoom\PublicDealRoomController::class, 'show']);
        Route::get('/{slug}/messages', [\App\Http\Controllers\Api\DealRoom\PublicDealRoomController::class, 'messages']);
        Route::post('/{slug}/messages', [\App\Http\Controllers\Api\DealRoom\PublicDealRoomController::class, 'sendMessage']);
        Route::post('/{slug}/actions/{actionId}/complete', [\App\Http\Controllers\Api\DealRoom\PublicDealRoomController::class, 'completeAction']);
        Route::post('/{slug}/documents/{docId}/view', [\App\Http\Controllers\Api\DealRoom\PublicDealRoomController::class, 'recordDocumentView']);
    });

    // Public Landing Pages Routes (no auth required, rate limited)
    Route::prefix('p')->middleware('throttle:public-forms')->group(function () {
        Route::get('/{slug}', [PublicLandingPageController::class, 'show']);
        Route::get('/{slug}/thank-you', [PublicLandingPageController::class, 'thankYou']);
        Route::post('/{slug}/engagement', [PublicLandingPageController::class, 'trackEngagement']);
        Route::post('/{slug}/conversion', [PublicLandingPageController::class, 'trackConversion']);
    });

    // Public CMS Routes (no auth required, rate limited)
    Route::prefix('cms/public')->middleware('throttle:public-forms')->group(function () {
        // Blog posts
        Route::get('/blog', [CmsPublicController::class, 'blogPosts']);
        Route::get('/blog/{slug}', [CmsPublicController::class, 'blogPost']);

        // Pages
        Route::get('/pages/{slug}', [CmsPublicController::class, 'page']);

        // Forms
        Route::get('/forms/{slug}', [CmsPublicController::class, 'formEmbed']);
        Route::post('/forms/{slug}/submit', [CmsPublicController::class, 'formSubmit']);
    });

    // WhatsApp Webhook Routes (no auth required - uses verify token)
    Route::prefix('whatsapp/webhook')->group(function () {
        Route::get('/{connectionId}', [WhatsappWebhookController::class, 'verify']);
        Route::post('/{connectionId}', [WhatsappWebhookController::class, 'handle']);
    });

    // SMS Webhook Routes (no auth required - uses provider signature verification)
    Route::prefix('sms/webhook')->group(function () {
        Route::post('/twilio', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'twilio']);
        Route::post('/vonage', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'vonage']);
        Route::post('/vonage/dlr', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'vonageDeliveryReceipt']);
        Route::post('/messagebird', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'messagebird']);
        Route::post('/plivo', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'plivo']);
        Route::post('/plivo/dlr', [\App\Http\Controllers\Api\Sms\SmsWebhookController::class, 'plivoDeliveryReport']);
    });

    // Public Live Chat Widget Routes (no auth required, rate limited)
    Route::prefix('chat-widget')->middleware('throttle:public-forms')->group(function () {
        // Get widget config
        Route::get('/{widgetKey}/config', [PublicChatController::class, 'getConfig']);

        // Visitor session management
        Route::post('/{widgetKey}/init', [PublicChatController::class, 'initSession']);
        Route::post('/{widgetKey}/identify', [PublicChatController::class, 'identify']);
        Route::post('/{widgetKey}/track-page', [PublicChatController::class, 'trackPageView']);

        // Conversation
        Route::post('/{widgetKey}/start', [PublicChatController::class, 'startConversation']);
        Route::post('/{widgetKey}/conversations/{conversationId}/messages', [PublicChatController::class, 'sendMessage']);
        Route::get('/{widgetKey}/conversations/{conversationId}/messages', [PublicChatController::class, 'getMessages']);
        Route::post('/{widgetKey}/conversations/{conversationId}/rate', [PublicChatController::class, 'rateConversation']);
    });

    // Call Recording Webhook Routes (no auth required - uses provider signature verification)
    Route::prefix('calls/webhook')->group(function () {
        Route::post('/inbound', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'inbound'])->name('api.calls.webhook.inbound');
        Route::post('/status', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'status'])->name('api.calls.webhook.status');
        Route::post('/recording', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'recording'])->name('api.calls.webhook.recording');
        Route::post('/transcription', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'transcription'])->name('api.calls.webhook.transcription');
        Route::post('/outbound-twiml', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'outboundTwiml'])->name('api.calls.twiml.outbound');
        Route::post('/menu', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'menu'])->name('api.calls.twiml.menu');
        Route::post('/voicemail', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'voicemailComplete'])->name('api.calls.webhook.voicemail');
        Route::post('/dial-result', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'dialResult'])->name('api.calls.webhook.dial-result');
        Route::any('/fallback', [\App\Http\Controllers\Api\Call\CallWebhookController::class, 'fallback'])->name('api.calls.webhook.fallback');
    });

    // Video Conferencing Webhook Routes (no auth required - uses provider signature verification)
    Route::prefix('video/webhook')->group(function () {
        Route::post('/zoom', [\App\Http\Controllers\Api\Video\VideoWebhookController::class, 'zoom'])->name('api.video.webhook.zoom');
    });

    // Video OAuth Callback (no auth required)
    Route::get('/video/oauth/callback', [\App\Http\Controllers\Api\Video\VideoWebhookController::class, 'oauthCallback'])->name('api.video.oauth.callback');

    // Customer Portal Public Routes (no internal auth required)
    Route::prefix('portal')->group(function () {
        // Auth routes (no auth required)
        Route::post('/login', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'login'])->middleware('throttle:portal-auth');
        Route::post('/register', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'register'])->middleware('throttle:portal-auth');
        Route::post('/verify-invitation', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'verifyInvitation']);

        // Authenticated portal routes (using portal token)
        Route::middleware(\App\Http\Middleware\PortalAuthenticate::class)->group(function () {
            // Auth
            Route::post('/logout', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'logout']);
            Route::get('/me', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'me']);
            Route::put('/profile', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'updateProfile']);
            Route::post('/change-password', [\App\Http\Controllers\Api\Portal\PortalAuthController::class, 'changePassword']);

            // Dashboard
            Route::get('/dashboard', [\App\Http\Controllers\Api\Portal\PortalController::class, 'dashboard']);

            // Deals
            Route::get('/deals', [\App\Http\Controllers\Api\Portal\PortalController::class, 'deals']);
            Route::get('/deals/{id}', [\App\Http\Controllers\Api\Portal\PortalController::class, 'deal']);

            // Invoices
            Route::get('/invoices', [\App\Http\Controllers\Api\Portal\PortalController::class, 'invoices']);
            Route::get('/invoices/{id}', [\App\Http\Controllers\Api\Portal\PortalController::class, 'invoice']);

            // Quotes
            Route::get('/quotes', [\App\Http\Controllers\Api\Portal\PortalController::class, 'quotes']);
            Route::get('/quotes/{id}', [\App\Http\Controllers\Api\Portal\PortalController::class, 'quote']);
            Route::post('/quotes/{id}/accept', [\App\Http\Controllers\Api\Portal\PortalController::class, 'acceptQuote']);

            // Documents
            Route::get('/documents', [\App\Http\Controllers\Api\Portal\PortalController::class, 'documents']);
            Route::get('/documents/{id}', [\App\Http\Controllers\Api\Portal\PortalController::class, 'viewDocument']);
            Route::post('/documents/{id}/sign', [\App\Http\Controllers\Api\Portal\PortalController::class, 'signDocument']);

            // Notifications
            Route::get('/notifications', [\App\Http\Controllers\Api\Portal\PortalController::class, 'notifications']);
            Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\Portal\PortalController::class, 'markNotificationRead']);
            Route::post('/notifications/read-all', [\App\Http\Controllers\Api\Portal\PortalController::class, 'markAllNotificationsRead']);

            // Announcements
            Route::get('/announcements', [\App\Http\Controllers\Api\Portal\PortalController::class, 'announcements']);

            // Activity Log
            Route::get('/activity', [\App\Http\Controllers\Api\Portal\PortalController::class, 'activityLog']);
        });
    });
});
