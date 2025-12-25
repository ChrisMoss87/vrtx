<?php

declare(strict_types=1);

namespace App\Providers;

// Module Domain
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\DbBlockRepository;
use App\Infrastructure\Persistence\Database\Repositories\DbFieldRepository;
use App\Infrastructure\Persistence\Database\Repositories\DbModuleRecordRepository;
use App\Infrastructure\Persistence\Database\Repositories\DbModuleRepository;

// Shared Domain Contracts
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\HasherInterface;
use App\Domain\Shared\Contracts\LoggerInterface;
use App\Domain\Shared\Contracts\StringHelperInterface;
use App\Domain\Shared\Contracts\ValidatorInterface;
use App\Infrastructure\Services\LaravelAuthContext;
use App\Infrastructure\Services\LaravelEventDispatcher;
use App\Infrastructure\Services\LaravelHasher;
use App\Infrastructure\Services\LaravelLogger;
use App\Infrastructure\Services\LaravelStringHelper;
use App\Infrastructure\Services\LaravelValidator;

// Workflow Domain
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowStepRepositoryInterface;
use App\Domain\Workflow\Services\ActionDispatcherService;
use App\Domain\Workflow\Services\ConditionEvaluationService;
use App\Domain\Workflow\Services\WorkflowExecutionService;
use App\Domain\Workflow\Services\WorkflowTriggerEvaluatorService;
use App\Domain\Workflow\Services\WorkflowValidationService;
use App\Infrastructure\Persistence\Database\Repositories\Workflow\DbWorkflowExecutionRepository;
use App\Infrastructure\Persistence\Database\Repositories\Workflow\DbWorkflowRepository;
use App\Infrastructure\Persistence\Database\Repositories\Workflow\DbWorkflowStepRepository;

// Blueprint Domain
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintTransitionRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Blueprint\DbBlueprintRepository;
use App\Infrastructure\Persistence\Database\Repositories\Blueprint\DbBlueprintStateRepository;
use App\Infrastructure\Persistence\Database\Repositories\Blueprint\DbBlueprintTransitionRepository;
use App\Infrastructure\Persistence\Database\Repositories\Blueprint\DbBlueprintRecordStateRepository;
use App\Infrastructure\Persistence\Database\Repositories\Blueprint\DbTransitionExecutionRepository;

// Reporting Domain
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\Repositories\DashboardTemplateRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Reporting\DbReportRepository;
use App\Infrastructure\Persistence\Database\Repositories\Reporting\DbDashboardRepository;
use App\Infrastructure\Persistence\Database\Repositories\Reporting\DbDashboardTemplateRepository;

// Forecasting Domain
use App\Domain\Forecasting\Repositories\ForecastScenarioRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastSnapshotRepositoryInterface;
use App\Domain\Forecasting\Repositories\SalesQuotaRepositoryInterface;
use App\Domain\Forecasting\Repositories\ForecastAdjustmentRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Forecasting\DbForecastScenarioRepository;
use App\Infrastructure\Persistence\Database\Repositories\Forecasting\DbForecastSnapshotRepository;
use App\Infrastructure\Persistence\Database\Repositories\Forecasting\DbSalesQuotaRepository;
use App\Infrastructure\Persistence\Database\Repositories\Forecasting\DbForecastAdjustmentRepository;

// Billing Domain
use App\Domain\Billing\Repositories\QuoteRepositoryInterface;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Billing\DbQuoteRepository;
use App\Infrastructure\Persistence\Database\Repositories\Billing\DbInvoiceRepository;
use App\Infrastructure\Persistence\Database\Repositories\Billing\DbProductRepository;

// Scheduling Domain
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Scheduling\DbSchedulingPageRepository;
use App\Infrastructure\Persistence\Database\Repositories\Scheduling\DbScheduledMeetingRepository;

// Email Domain
use App\Domain\Email\Repositories\EmailMessageRepositoryInterface;
use App\Domain\Email\Repositories\EmailTemplateRepositoryInterface;
use App\Domain\Email\Repositories\EmailAccountRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Email\DbEmailMessageRepository;
use App\Infrastructure\Persistence\Database\Repositories\Email\DbEmailTemplateRepository;
use App\Infrastructure\Persistence\Database\Repositories\Email\DbEmailAccountRepository;

// Approval Domain
use App\Domain\Approval\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Approval\Repositories\ApprovalRuleRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Approval\DbApprovalRequestRepository;
use App\Infrastructure\Persistence\Database\Repositories\Approval\DbApprovalRuleRepository;

// Competitor Domain
use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use App\Domain\Competitor\Repositories\BattlecardRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Competitor\DbCompetitorRepository;
use App\Infrastructure\Persistence\Database\Repositories\Competitor\DbBattlecardRepository;

// DealRoom Domain
use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\DealRoom\DbDealRoomRepository;

// Activity Domain
use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Activity\DbActivityRepository;

// Pipeline Domain
use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Pipeline\DbPipelineRepository;

// Campaign Domain
use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Campaign\DbCampaignRepository;

// Cadence Domain
use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Cadence\DbCadenceRepository;

// LeadScoring Domain
use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\LeadScoring\DbScoringModelRepository;

// Proposal Domain
use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Proposal\DbProposalRepository;

// Chat Domain
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Chat\DbChatConversationRepository;

// Sms Domain
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Sms\DbSmsMessageRepository;

// WhatsApp Domain
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\WhatsApp\DbWhatsappConversationRepository;

// Call Domain
use App\Domain\Call\Repositories\CallRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Call\DbCallRepository;

// Inbox Domain
use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Inbox\DbInboxConversationRepository;

// Document Domain
use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Document\DbSignatureRequestRepository;

// Portal Domain
use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Portal\DbPortalUserRepository;

// Support Domain
use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Support\DbSupportTicketRepository;

// KnowledgeBase Domain
use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\KnowledgeBase\DbKbArticleRepository;

// ImportExport Domain
use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\ImportExport\DbImportRepository;

// Duplicate Domain
use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Duplicate\DbDuplicateCandidateRepository;

// AI Domain
use App\Domain\AI\Repositories\AiPromptRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\AI\DbAiPromptRepository;

// Goal Domain
use App\Domain\Goal\Repositories\GoalRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Goal\DbGoalRepository;

// Contract Domain
use App\Domain\Contract\Repositories\ContractRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Contract\DbContractRepository;

// WebForm Domain
use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\WebForm\DbWebFormRepository;

// LandingPage Domain
use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\LandingPage\DbLandingPageRepository;

// Webhook Domain
use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Webhook\DbWebhookRepository;

// Plugin Domain
use App\Domain\Plugin\Repositories\PluginRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Plugin\DbPluginRepository;

// Video Domain
use App\Domain\Video\Repositories\VideoMeetingRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Video\DbVideoMeetingRepository;

// Playbook Domain
use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Playbook\DbPlaybookRepository;

// Notification Domain
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;
use App\Domain\Notification\Repositories\NotificationScheduleRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Notification\DbNotificationRepository;
use App\Infrastructure\Persistence\Database\Repositories\Notification\DbNotificationPreferenceRepository;
use App\Infrastructure\Persistence\Database\Repositories\Notification\DbNotificationScheduleRepository;

// User Domain
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Repositories\SessionRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\User\DbUserRepository;
use App\Infrastructure\Persistence\Database\Repositories\User\DbSessionRepository;

// Wizard Domain
use App\Domain\Wizard\Repositories\WizardRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Wizard\DbWizardRepository;

// CMS Domain
use App\Domain\CMS\Repositories\CmsPageRepositoryInterface;
use App\Domain\CMS\Repositories\CmsTemplateRepositoryInterface;
use App\Domain\CMS\Repositories\CmsMediaRepositoryInterface;
use App\Domain\CMS\Repositories\CmsFormRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\CMS\DbCmsPageRepository;
use App\Infrastructure\Persistence\Database\Repositories\CMS\DbCmsTemplateRepository;
use App\Infrastructure\Persistence\Database\Repositories\CMS\DbCmsMediaRepository;
use App\Infrastructure\Persistence\Database\Repositories\CMS\DbCmsFormRepository;

// Analytics Domain
use App\Domain\Analytics\Repositories\AnalyticsAlertRepositoryInterface;
use App\Domain\Analytics\Repositories\AnalyticsAlertHistoryRepositoryInterface;
use App\Infrastructure\Persistence\Database\Repositories\Analytics\DbAnalyticsAlertRepository;
use App\Infrastructure\Persistence\Database\Repositories\Analytics\DbAnalyticsAlertHistoryRepository;

// Integration Domain
use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\Services\IntegrationOAuthServiceInterface;
use App\Infrastructure\Persistence\Database\Repositories\Integration\DbIntegrationConnectionRepository;
use App\Infrastructure\Services\Integration\IntegrationOAuthService;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerSharedInfrastructure();
        $this->registerModuleRepositories();
        $this->registerWorkflowRepositories();
        $this->registerBlueprintRepositories();
        $this->registerReportingRepositories();
        $this->registerForecastingRepositories();
        $this->registerBillingRepositories();
        $this->registerSchedulingRepositories();
        $this->registerEmailRepositories();
        $this->registerCommunicationRepositories();
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
        $this->registerNotificationRepositories();
        $this->registerUserRepositories();
        $this->registerWizardRepositories();
        $this->registerAnalyticsRepositories();
        $this->registerIntegrationRepositories();
        $this->registerCmsRepositories();
        $this->registerWorkflowDomainServices();
    }

    private function registerSharedInfrastructure(): void
    {
        $this->app->bind(AuthContextInterface::class, LaravelAuthContext::class);
        $this->app->bind(EventDispatcherInterface::class, LaravelEventDispatcher::class);
        $this->app->bind(LoggerInterface::class, LaravelLogger::class);
        $this->app->bind(ValidatorInterface::class, LaravelValidator::class);
        $this->app->bind(HasherInterface::class, LaravelHasher::class);
        $this->app->bind(StringHelperInterface::class, LaravelStringHelper::class);
    }

    private function registerModuleRepositories(): void
    {
        $this->app->bind(ModuleRepositoryInterface::class, DbModuleRepository::class);
        $this->app->bind(BlockRepositoryInterface::class, DbBlockRepository::class);
        $this->app->bind(FieldRepositoryInterface::class, DbFieldRepository::class);
        $this->app->bind(ModuleRecordRepositoryInterface::class, DbModuleRecordRepository::class);
    }

    private function registerWorkflowRepositories(): void
    {
        $this->app->bind(WorkflowRepositoryInterface::class, DbWorkflowRepository::class);
        $this->app->bind(WorkflowExecutionRepositoryInterface::class, DbWorkflowExecutionRepository::class);
        $this->app->bind(WorkflowStepRepositoryInterface::class, DbWorkflowStepRepository::class);
    }

    private function registerBlueprintRepositories(): void
    {
        $this->app->bind(BlueprintRepositoryInterface::class, DbBlueprintRepository::class);
        $this->app->bind(BlueprintStateRepositoryInterface::class, DbBlueprintStateRepository::class);
        $this->app->bind(BlueprintTransitionRepositoryInterface::class, DbBlueprintTransitionRepository::class);
        $this->app->bind(BlueprintRecordStateRepositoryInterface::class, DbBlueprintRecordStateRepository::class);
        $this->app->bind(TransitionExecutionRepositoryInterface::class, DbTransitionExecutionRepository::class);
    }

    private function registerReportingRepositories(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, DbReportRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DbDashboardRepository::class);
        $this->app->bind(DashboardTemplateRepositoryInterface::class, DbDashboardTemplateRepository::class);
    }

    private function registerForecastingRepositories(): void
    {
        $this->app->bind(ForecastScenarioRepositoryInterface::class, DbForecastScenarioRepository::class);
        $this->app->bind(ForecastSnapshotRepositoryInterface::class, DbForecastSnapshotRepository::class);
        $this->app->bind(SalesQuotaRepositoryInterface::class, DbSalesQuotaRepository::class);
        $this->app->bind(ForecastAdjustmentRepositoryInterface::class, DbForecastAdjustmentRepository::class);
    }

    private function registerBillingRepositories(): void
    {
        $this->app->bind(QuoteRepositoryInterface::class, DbQuoteRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, DbInvoiceRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, DbProductRepository::class);
    }

    private function registerSchedulingRepositories(): void
    {
        $this->app->bind(SchedulingPageRepositoryInterface::class, DbSchedulingPageRepository::class);
        $this->app->bind(ScheduledMeetingRepositoryInterface::class, DbScheduledMeetingRepository::class);
    }

    private function registerEmailRepositories(): void
    {
        $this->app->bind(EmailMessageRepositoryInterface::class, DbEmailMessageRepository::class);
        $this->app->bind(EmailTemplateRepositoryInterface::class, DbEmailTemplateRepository::class);
        $this->app->bind(EmailAccountRepositoryInterface::class, DbEmailAccountRepository::class);

        // OAuth Authorization Service
        $this->app->bind(
            \App\Domain\Email\Services\OAuthAuthorizationServiceInterface::class,
            \App\Infrastructure\Services\OAuth\OAuthAuthorizationService::class
        );
    }

    private function registerCommunicationRepositories(): void
    {
        // Unified Conversation Repository
        $this->app->bind(
            \App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface::class,
            \App\Infrastructure\Persistence\Database\Repositories\Communication\DbUnifiedConversationRepository::class
        );

        // Communication Aggregator Service (singleton for channel registration)
        $this->app->singleton(
            \App\Domain\Communication\Services\CommunicationAggregatorService::class,
            function ($app) {
                $aggregator = new \App\Domain\Communication\Services\CommunicationAggregatorService(
                    $app->make(\App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface::class)
                );

                // Register available channel adapters
                $aggregator->registerChannel(
                    $app->make(\App\Infrastructure\Communication\Adapters\EmailChannelAdapter::class)
                );
                $aggregator->registerChannel(
                    $app->make(\App\Infrastructure\Communication\Adapters\ChatChannelAdapter::class)
                );
                $aggregator->registerChannel(
                    $app->make(\App\Infrastructure\Communication\Adapters\WhatsAppChannelAdapter::class)
                );
                $aggregator->registerChannel(
                    $app->make(\App\Infrastructure\Communication\Adapters\SmsChannelAdapter::class)
                );

                return $aggregator;
            }
        );
    }

    private function registerApprovalRepositories(): void
    {
        $this->app->bind(ApprovalRequestRepositoryInterface::class, DbApprovalRequestRepository::class);
        $this->app->bind(ApprovalRuleRepositoryInterface::class, DbApprovalRuleRepository::class);
    }

    private function registerCompetitorRepositories(): void
    {
        $this->app->bind(CompetitorRepositoryInterface::class, DbCompetitorRepository::class);
        $this->app->bind(BattlecardRepositoryInterface::class, DbBattlecardRepository::class);
    }

    private function registerDealRoomRepositories(): void
    {
        $this->app->bind(DealRoomRepositoryInterface::class, DbDealRoomRepository::class);
    }

    private function registerActivityRepositories(): void
    {
        $this->app->bind(ActivityRepositoryInterface::class, DbActivityRepository::class);
    }

    private function registerPipelineRepositories(): void
    {
        $this->app->bind(PipelineRepositoryInterface::class, DbPipelineRepository::class);
    }

    private function registerCampaignRepositories(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, DbCampaignRepository::class);
    }

    private function registerCadenceRepositories(): void
    {
        $this->app->bind(CadenceRepositoryInterface::class, DbCadenceRepository::class);
    }

    private function registerLeadScoringRepositories(): void
    {
        $this->app->bind(ScoringModelRepositoryInterface::class, DbScoringModelRepository::class);
    }

    private function registerProposalRepositories(): void
    {
        $this->app->bind(ProposalRepositoryInterface::class, DbProposalRepository::class);
    }

    private function registerChatRepositories(): void
    {
        $this->app->bind(ChatConversationRepositoryInterface::class, DbChatConversationRepository::class);
    }

    private function registerSmsRepositories(): void
    {
        $this->app->bind(SmsMessageRepositoryInterface::class, DbSmsMessageRepository::class);
    }

    private function registerWhatsAppRepositories(): void
    {
        $this->app->bind(WhatsappConversationRepositoryInterface::class, DbWhatsappConversationRepository::class);
    }

    private function registerCallRepositories(): void
    {
        $this->app->bind(CallRepositoryInterface::class, DbCallRepository::class);
    }

    private function registerInboxRepositories(): void
    {
        $this->app->bind(InboxConversationRepositoryInterface::class, DbInboxConversationRepository::class);
    }

    private function registerDocumentRepositories(): void
    {
        $this->app->bind(SignatureRequestRepositoryInterface::class, DbSignatureRequestRepository::class);
    }

    private function registerPortalRepositories(): void
    {
        $this->app->bind(PortalUserRepositoryInterface::class, DbPortalUserRepository::class);
    }

    private function registerSupportRepositories(): void
    {
        $this->app->bind(SupportTicketRepositoryInterface::class, DbSupportTicketRepository::class);
    }

    private function registerKnowledgeBaseRepositories(): void
    {
        $this->app->bind(KbArticleRepositoryInterface::class, DbKbArticleRepository::class);
    }

    private function registerImportExportRepositories(): void
    {
        $this->app->bind(ImportRepositoryInterface::class, DbImportRepository::class);
    }

    private function registerDuplicateRepositories(): void
    {
        $this->app->bind(DuplicateCandidateRepositoryInterface::class, DbDuplicateCandidateRepository::class);
    }

    private function registerAIRepositories(): void
    {
        $this->app->bind(AiPromptRepositoryInterface::class, DbAiPromptRepository::class);
    }

    private function registerGoalRepositories(): void
    {
        $this->app->bind(GoalRepositoryInterface::class, DbGoalRepository::class);
    }

    private function registerContractRepositories(): void
    {
        $this->app->bind(ContractRepositoryInterface::class, DbContractRepository::class);
    }

    private function registerWebFormRepositories(): void
    {
        $this->app->bind(WebFormRepositoryInterface::class, DbWebFormRepository::class);
    }

    private function registerLandingPageRepositories(): void
    {
        $this->app->bind(LandingPageRepositoryInterface::class, DbLandingPageRepository::class);
    }

    private function registerWebhookRepositories(): void
    {
        $this->app->bind(WebhookRepositoryInterface::class, DbWebhookRepository::class);
    }

    private function registerPluginRepositories(): void
    {
        $this->app->bind(PluginRepositoryInterface::class, DbPluginRepository::class);
    }

    private function registerVideoRepositories(): void
    {
        $this->app->bind(VideoMeetingRepositoryInterface::class, DbVideoMeetingRepository::class);
    }

    private function registerPlaybookRepositories(): void
    {
        $this->app->bind(PlaybookRepositoryInterface::class, DbPlaybookRepository::class);
    }

    private function registerNotificationRepositories(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, DbNotificationRepository::class);
        $this->app->bind(NotificationPreferenceRepositoryInterface::class, DbNotificationPreferenceRepository::class);
        $this->app->bind(NotificationScheduleRepositoryInterface::class, DbNotificationScheduleRepository::class);
    }

    private function registerUserRepositories(): void
    {
        $this->app->bind(UserRepositoryInterface::class, DbUserRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, DbSessionRepository::class);
    }

    private function registerWizardRepositories(): void
    {
        $this->app->bind(WizardRepositoryInterface::class, DbWizardRepository::class);
    }

    private function registerAnalyticsRepositories(): void
    {
        $this->app->bind(AnalyticsAlertRepositoryInterface::class, DbAnalyticsAlertRepository::class);
        $this->app->bind(AnalyticsAlertHistoryRepositoryInterface::class, DbAnalyticsAlertHistoryRepository::class);
    }

    private function registerIntegrationRepositories(): void
    {
        $this->app->bind(IntegrationConnectionRepositoryInterface::class, DbIntegrationConnectionRepository::class);
        $this->app->bind(IntegrationOAuthServiceInterface::class, IntegrationOAuthService::class);
    }

    private function registerCmsRepositories(): void
    {
        $this->app->bind(CmsPageRepositoryInterface::class, DbCmsPageRepository::class);
        $this->app->bind(CmsTemplateRepositoryInterface::class, DbCmsTemplateRepository::class);
        $this->app->bind(CmsMediaRepositoryInterface::class, DbCmsMediaRepository::class);
        $this->app->bind(CmsFormRepositoryInterface::class, DbCmsFormRepository::class);
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
                $app->make(EventDispatcherInterface::class),
                $app->make(LoggerInterface::class),
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
