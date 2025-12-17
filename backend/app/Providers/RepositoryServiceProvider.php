<?php

declare(strict_types=1);

namespace App\Providers;

// Module Domain
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentBlockRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentFieldRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRecordRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;

// Workflow Domain
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowStepRepositoryInterface;
use App\Domain\Workflow\Services\ActionDispatcherService;
use App\Domain\Workflow\Services\ConditionEvaluationService;
use App\Domain\Workflow\Services\WorkflowExecutionService;
use App\Domain\Workflow\Services\WorkflowTriggerEvaluatorService;
use App\Domain\Workflow\Services\WorkflowValidationService;
use App\Infrastructure\Persistence\Eloquent\Repositories\Workflow\EloquentWorkflowExecutionRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Workflow\EloquentWorkflowRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Workflow\EloquentWorkflowStepRepository;

// Blueprint Domain
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintTransitionRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint\EloquentBlueprintRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint\EloquentBlueprintStateRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint\EloquentBlueprintTransitionRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint\EloquentBlueprintRecordStateRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Blueprint\EloquentTransitionExecutionRepository;

// Reporting Domain
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardTemplateRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Reporting\EloquentReportRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Reporting\EloquentDashboardRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Reporting\EloquentDashboardTemplateRepository;

// Forecasting Domain
use App\Domain\Forecasting\Repositories\ForecastScenarioRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\Repositories\SalesQuotaRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastAdjustmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting\EloquentForecastScenarioRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting\EloquentForecastSnapshotRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting\EloquentSalesQuotaRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Forecasting\EloquentForecastAdjustmentRepository;

// Billing Domain
use App\Domain\Billing\Repositories\QuoteRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Billing\EloquentQuoteRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Billing\EloquentInvoiceRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Billing\EloquentProductRepository;

// Scheduling Domain
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Scheduling\EloquentSchedulingPageRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Scheduling\EloquentScheduledMeetingRepository;

// Email Domain
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Email\EloquentEmailMessageRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Email\EloquentEmailTemplateRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Email\EloquentEmailAccountRepository;

// Approval Domain
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Approval\EloquentApprovalRequestRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Approval\EloquentApprovalRuleRepository;

// Competitor Domain
use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use App\Domain\Competitor\Repositories\BattlecardRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Competitor\EloquentCompetitorRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\Competitor\EloquentBattlecardRepository;

// DealRoom Domain
use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\DealRoom\EloquentDealRoomRepository;

// Activity Domain
use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Activity\EloquentActivityRepository;

// Pipeline Domain
use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Pipeline\EloquentPipelineRepository;

// Campaign Domain
use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Campaign\EloquentCampaignRepository;

// Cadence Domain
use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Cadence\EloquentCadenceRepository;

// LeadScoring Domain
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\LeadScoring\EloquentScoringModelRepository;

// Proposal Domain
use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Proposal\EloquentProposalRepository;

// Chat Domain
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Chat\EloquentChatConversationRepository;

// Sms Domain
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Sms\EloquentSmsMessageRepository;

// WhatsApp Domain
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\WhatsApp\EloquentWhatsappConversationRepository;

// Call Domain
use App\Domain\Call\Repositories\CallRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Call\EloquentCallRepository;

// Inbox Domain
use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Inbox\EloquentInboxConversationRepository;

// Document Domain
use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Document\EloquentSignatureRequestRepository;

// Portal Domain
use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Portal\EloquentPortalUserRepository;

// Support Domain
use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Support\EloquentSupportTicketRepository;

// KnowledgeBase Domain
use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\KnowledgeBase\EloquentKbArticleRepository;

// ImportExport Domain
use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\ImportExport\EloquentImportRepository;

// Duplicate Domain
use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Duplicate\EloquentDuplicateCandidateRepository;

// AI Domain
use App\Domain\AI\Repositories\AiPromptRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\AI\EloquentAiPromptRepository;

// Goal Domain
use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Goal\EloquentGoalRepository;

// Contract Domain
use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Contract\EloquentContractRepository;

// WebForm Domain
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\WebForm\EloquentWebFormRepository;

// LandingPage Domain
use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\LandingPage\EloquentLandingPageRepository;

// Webhook Domain
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Webhook\EloquentWebhookRepository;

// Plugin Domain
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Plugin\EloquentPluginRepository;

// Video Domain
use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Video\EloquentVideoMeetingRepository;

// Playbook Domain
use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Repositories\Playbook\EloquentPlaybookRepository;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerModuleRepositories();
        $this->registerWorkflowRepositories();
        $this->registerBlueprintRepositories();
        $this->registerReportingRepositories();
        $this->registerForecastingRepositories();
        $this->registerBillingRepositories();
        $this->registerSchedulingRepositories();
        $this->registerEmailRepositories();
        $this->registerApprovalRepositories();
        $this->registerCompetitorRepositories();
        $this->registerDealRoomRepositories();
        $this->registerActivityRepositories();
        $this->registerPipelineRepositories();
        $this->registerCampaignRepositories();
        $this->registerCadenceRepositories();
        $this->registerLeadScoringRepositories();
        $this->registerProposalRepositories();
        $this->registerChatRepositories();
        $this->registerSmsRepositories();
        $this->registerWhatsAppRepositories();
        $this->registerCallRepositories();
        $this->registerInboxRepositories();
        $this->registerDocumentRepositories();
        $this->registerPortalRepositories();
        $this->registerSupportRepositories();
        $this->registerKnowledgeBaseRepositories();
        $this->registerImportExportRepositories();
        $this->registerDuplicateRepositories();
        $this->registerAIRepositories();
        $this->registerGoalRepositories();
        $this->registerContractRepositories();
        $this->registerWebFormRepositories();
        $this->registerLandingPageRepositories();
        $this->registerWebhookRepositories();
        $this->registerPluginRepositories();
        $this->registerVideoRepositories();
        $this->registerPlaybookRepositories();
        $this->registerWorkflowDomainServices();
    }

    private function registerModuleRepositories(): void
    {
        $this->app->bind(ModuleRepositoryInterface::class, EloquentModuleRepository::class);
        $this->app->bind(BlockRepositoryInterface::class, EloquentBlockRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, EloquentFieldRepository::class);
        $this->app->bind(ModuleRecordRepositoryInterface::class, EloquentModuleRecordRepository::class);
    }

    private function registerWorkflowRepositories(): void
    {
        $this->app->bind(WorkflowRepositoryInterface::class, EloquentWorkflowRepository::class);
        $this->app->bind(WorkflowExecutionRepositoryInterface::class, EloquentWorkflowExecutionRepository::class);
        $this->app->bind(WorkflowStepRepositoryInterface::class, EloquentWorkflowStepRepository::class);
    }

    private function registerBlueprintRepositories(): void
    {
        $this->app->bind(BlueprintRepositoryInterface::class, EloquentBlueprintRepository::class);
        $this->app->bind(BlueprintStateRepositoryInterface::class, EloquentBlueprintStateRepository::class);
        $this->app->bind(BlueprintTransitionRepositoryInterface::class, EloquentBlueprintTransitionRepository::class);
        $this->app->bind(BlueprintRecordStateRepositoryInterface::class, EloquentBlueprintRecordStateRepository::class);
        $this->app->bind(TransitionExecutionRepositoryInterface::class, EloquentTransitionExecutionRepository::class);
    }

    private function registerReportingRepositories(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, EloquentReportRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
        $this->app->bind(DashboardTemplateRepositoryInterface::class, EloquentDashboardTemplateRepository::class);
    }

    private function registerForecastingRepositories(): void
    {
        $this->app->bind(ForecastScenarioRepositoryInterface::class, EloquentForecastScenarioRepository::class);
        $this->app->bind(ForecastSnapshotRepositoryInterface::class, EloquentForecastSnapshotRepository::class);
        $this->app->bind(SalesQuotaRepositoryInterface::class, EloquentSalesQuotaRepository::class);
        $this->app->bind(ForecastAdjustmentRepositoryInterface::class, EloquentForecastAdjustmentRepository::class);
    }

    private function registerBillingRepositories(): void
    {
        $this->app->bind(QuoteRepositoryInterface::class, EloquentQuoteRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
    }

    private function registerSchedulingRepositories(): void
    {
        $this->app->bind(SchedulingPageRepositoryInterface::class, EloquentSchedulingPageRepository::class);
        $this->app->bind(ScheduledMeetingRepositoryInterface::class, EloquentScheduledMeetingRepository::class);
    }

    private function registerEmailRepositories(): void
    {
        $this->app->bind(EmailMessageRepositoryInterface::class, EloquentEmailMessageRepository::class);
        $this->app->bind(EmailTemplateRepositoryInterface::class, EloquentEmailTemplateRepository::class);
        $this->app->bind(EmailAccountRepositoryInterface::class, EloquentEmailAccountRepository::class);
    }

    private function registerApprovalRepositories(): void
    {
        $this->app->bind(ApprovalRequestRepositoryInterface::class, EloquentApprovalRequestRepository::class);
        $this->app->bind(ApprovalRuleRepositoryInterface::class, EloquentApprovalRuleRepository::class);
    }

    private function registerCompetitorRepositories(): void
    {
        $this->app->bind(CompetitorRepositoryInterface::class, EloquentCompetitorRepository::class);
        $this->app->bind(BattlecardRepositoryInterface::class, EloquentBattlecardRepository::class);
    }

    private function registerDealRoomRepositories(): void
    {
        $this->app->bind(DealRoomRepositoryInterface::class, EloquentDealRoomRepository::class);
    }

    private function registerActivityRepositories(): void
    {
        $this->app->bind(ActivityRepositoryInterface::class, EloquentActivityRepository::class);
    }

    private function registerPipelineRepositories(): void
    {
        $this->app->bind(PipelineRepositoryInterface::class, EloquentPipelineRepository::class);
    }

    private function registerCampaignRepositories(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, EloquentCampaignRepository::class);
    }

    private function registerCadenceRepositories(): void
    {
        $this->app->bind(CadenceRepositoryInterface::class, EloquentCadenceRepository::class);
    }

    private function registerLeadScoringRepositories(): void
    {
        $this->app->bind(ScoringModelRepositoryInterface::class, EloquentScoringModelRepository::class);
    }

    private function registerProposalRepositories(): void
    {
        $this->app->bind(ProposalRepositoryInterface::class, EloquentProposalRepository::class);
    }

    private function registerChatRepositories(): void
    {
        $this->app->bind(ChatConversationRepositoryInterface::class, EloquentChatConversationRepository::class);
    }

    private function registerSmsRepositories(): void
    {
        $this->app->bind(SmsMessageRepositoryInterface::class, EloquentSmsMessageRepository::class);
    }

    private function registerWhatsAppRepositories(): void
    {
        $this->app->bind(WhatsappConversationRepositoryInterface::class, EloquentWhatsappConversationRepository::class);
    }

    private function registerCallRepositories(): void
    {
        $this->app->bind(CallRepositoryInterface::class, EloquentCallRepository::class);
    }

    private function registerInboxRepositories(): void
    {
        $this->app->bind(InboxConversationRepositoryInterface::class, EloquentInboxConversationRepository::class);
    }

    private function registerDocumentRepositories(): void
    {
        $this->app->bind(SignatureRequestRepositoryInterface::class, EloquentSignatureRequestRepository::class);
    }

    private function registerPortalRepositories(): void
    {
        $this->app->bind(PortalUserRepositoryInterface::class, EloquentPortalUserRepository::class);
    }

    private function registerSupportRepositories(): void
    {
        $this->app->bind(SupportTicketRepositoryInterface::class, EloquentSupportTicketRepository::class);
    }

    private function registerKnowledgeBaseRepositories(): void
    {
        $this->app->bind(KbArticleRepositoryInterface::class, EloquentKbArticleRepository::class);
    }

    private function registerImportExportRepositories(): void
    {
        $this->app->bind(ImportRepositoryInterface::class, EloquentImportRepository::class);
    }

    private function registerDuplicateRepositories(): void
    {
        $this->app->bind(DuplicateCandidateRepositoryInterface::class, EloquentDuplicateCandidateRepository::class);
    }

    private function registerAIRepositories(): void
    {
        $this->app->bind(AiPromptRepositoryInterface::class, EloquentAiPromptRepository::class);
    }

    private function registerGoalRepositories(): void
    {
        $this->app->bind(GoalRepositoryInterface::class, EloquentGoalRepository::class);
    }

    private function registerContractRepositories(): void
    {
        $this->app->bind(ContractRepositoryInterface::class, EloquentContractRepository::class);
    }

    private function registerWebFormRepositories(): void
    {
        $this->app->bind(WebFormRepositoryInterface::class, EloquentWebFormRepository::class);
    }

    private function registerLandingPageRepositories(): void
    {
        $this->app->bind(LandingPageRepositoryInterface::class, EloquentLandingPageRepository::class);
    }

    private function registerWebhookRepositories(): void
    {
        $this->app->bind(WebhookRepositoryInterface::class, EloquentWebhookRepository::class);
    }

    private function registerPluginRepositories(): void
    {
        $this->app->bind(PluginRepositoryInterface::class, EloquentPluginRepository::class);
    }

    private function registerVideoRepositories(): void
    {
        $this->app->bind(VideoMeetingRepositoryInterface::class, EloquentVideoMeetingRepository::class);
    }

    private function registerPlaybookRepositories(): void
    {
        $this->app->bind(PlaybookRepositoryInterface::class, EloquentPlaybookRepository::class);
    }

    private function registerWorkflowDomainServices(): void
    {
        $this->app->singleton(ConditionEvaluationService::class);
        $this->app->singleton(WorkflowValidationService::class);
        $this->app->singleton(WorkflowTriggerEvaluatorService::class);
        $this->app->singleton(ActionDispatcherService::class);

        $this->app->singleton(WorkflowExecutionService::class, function ($app) {
            return new WorkflowExecutionService(
                $app->make(WorkflowRepositoryInterface::class),
                $app->make(WorkflowExecutionRepositoryInterface::class),
                $app->make(ConditionEvaluationService::class),
                $app->make(ActionDispatcherService::class),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register action handlers
        $this->registerActionHandlers();
    }

    /**
     * Register workflow action handlers with the dispatcher.
     */
    private function registerActionHandlers(): void
    {
        $dispatcher = $this->app->make(ActionDispatcherService::class);

        // Register the legacy action handler as a bridge
        // This allows the new DDD workflow execution to use existing action handlers
        $dispatcher->registerHandler('send_email', function (array $config, array $context) {
            return $this->handleLegacyAction('send_email', $config, $context);
        });

        $dispatcher->registerHandler('send_notification', function (array $config, array $context) {
            return $this->handleLegacyAction('send_notification', $config, $context);
        });

        $dispatcher->registerHandler('update_field', function (array $config, array $context) {
            return $this->handleLegacyAction('update_field', $config, $context);
        });

        $dispatcher->registerHandler('create_record', function (array $config, array $context) {
            return $this->handleLegacyAction('create_record', $config, $context);
        });

        $dispatcher->registerHandler('create_task', function (array $config, array $context) {
            return $this->handleLegacyAction('create_task', $config, $context);
        });

        $dispatcher->registerHandler('assign_user', function (array $config, array $context) {
            return $this->handleLegacyAction('assign_user', $config, $context);
        });

        $dispatcher->registerHandler('move_stage', function (array $config, array $context) {
            return $this->handleLegacyAction('move_stage', $config, $context);
        });

        $dispatcher->registerHandler('webhook', function (array $config, array $context) {
            return $this->handleLegacyAction('webhook', $config, $context);
        });

        $dispatcher->registerHandler('delay', function (array $config, array $context) {
            // Delay action - just return success, actual delay is handled by job scheduling
            return ['delayed' => true, 'duration' => $config['duration'] ?? 0];
        });

        $dispatcher->registerHandler('condition', function (array $config, array $context) {
            // Condition branch - evaluate and return which branch to take
            $conditionService = $this->app->make(ConditionEvaluationService::class);
            $conditions = $config['conditions'] ?? [];
            $result = $conditionService->evaluate($conditions, $context);
            return ['condition_met' => $result, 'branch' => $result ? 'true' : 'false'];
        });

        $dispatcher->registerHandler('add_tag', function (array $config, array $context) {
            return $this->handleLegacyAction('add_tag', $config, $context);
        });

        $dispatcher->registerHandler('remove_tag', function (array $config, array $context) {
            return $this->handleLegacyAction('remove_tag', $config, $context);
        });

        $dispatcher->registerHandler('update_related_record', function (array $config, array $context) {
            return $this->handleLegacyAction('update_related_record', $config, $context);
        });
    }

    /**
     * Bridge to legacy action handlers.
     */
    private function handleLegacyAction(string $actionType, array $config, array $context): array
    {
        try {
            $legacyHandler = $this->app->make(\App\Services\Workflow\Actions\ActionHandler::class);
            $result = $legacyHandler->handle($actionType, $config, $context);
            return is_array($result) ? $result : ['result' => $result];
        } catch (\Exception $e) {
            throw new \RuntimeException("Action '{$actionType}' failed: {$e->getMessage()}", 0, $e);
        }
    }
}
