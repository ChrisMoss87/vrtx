<?php

declare(strict_types=1);

namespace App\Application\Services\Reporting;

use App\Domain\Reporting\DTOs\CreateDashboardDTO;
use App\Domain\Reporting\DTOs\CreateReportDTO;
use App\Domain\Reporting\DTOs\DashboardResponseDTO;
use App\Domain\Reporting\DTOs\ReportResponseDTO;
use App\Domain\Reporting\Entities\Dashboard;
use App\Domain\Reporting\Entities\Report;
use App\Domain\Reporting\Events\DashboardCreated;
use App\Domain\Reporting\Events\DashboardUpdated;
use App\Domain\Reporting\Events\ReportCreated;
use App\Domain\Reporting\Events\ReportExecuted;
use App\Domain\Reporting\Repositories\DashboardRepositoryInterface;
use App\Domain\Reporting\Repositories\ReportRepositoryInterface;
use App\Domain\Reporting\Services\ReportExecutionService;
use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Domain\Shared\ValueObjects\UserId;
use App\Services\Reporting\ReportService;
use Illuminate\Support\Facades\Event;

/**
 * Application service for Reporting bounded context.
 *
 * This service orchestrates use cases and coordinates between
 * domain entities, repositories, and infrastructure services.
 */
class ReportingApplicationService
{
    public function __construct(
        private ReportRepositoryInterface $reportRepository,
        private DashboardRepositoryInterface $dashboardRepository,
        private ReportExecutionService $reportExecutionService,
        private ReportService $reportService, // Infrastructure service for query execution
    ) {}

    // ========== Report Use Cases ==========

    /**
     * Create a new report.
     */
    public function createReport(CreateReportDTO $dto): ReportResponseDTO
    {
        $report = Report::create(
            name: $dto->name,
            moduleId: $dto->moduleId,
            type: $dto->type,
            userId: $dto->userId ? UserId::fromInt($dto->userId) : null,
            description: $dto->description,
            chartType: $dto->chartType,
            filters: $dto->filters,
            grouping: $dto->grouping,
            aggregations: $dto->aggregations,
            sorting: $dto->sorting,
            dateRange: $dto->dateRange,
        );

        $savedReport = $this->reportRepository->save($report);

        // Dispatch domain event
        Event::dispatch(new ReportCreated(
            reportId: $savedReport->getId() ?? 0,
            reportName: $savedReport->name(),
            moduleId: $savedReport->moduleId(),
            reportType: $savedReport->type()->value,
            isPublic: $savedReport->isPublic(),
            userId: $savedReport->userId()?->value(),
        ));

        return ReportResponseDTO::fromEntity($savedReport);
    }

    /**
     * Update an existing report.
     */
    public function updateReport(
        int $reportId,
        string $name,
        ?string $description,
        ReportType $type,
        ?ChartType $chartType,
        array $filters,
        array $grouping,
        array $aggregations,
        array $sorting,
        ?DateRange $dateRange,
        array $config,
    ): ReportResponseDTO {
        $report = $this->reportRepository->findById($reportId);

        if (!$report) {
            throw new \InvalidArgumentException("Report not found: {$reportId}");
        }

        $report->update(
            name: $name,
            description: $description,
            type: $type,
            chartType: $chartType,
            filters: $filters,
            grouping: $grouping,
            aggregations: $aggregations,
            sorting: $sorting,
            dateRange: $dateRange,
            config: $config,
        );

        $savedReport = $this->reportRepository->save($report);

        return ReportResponseDTO::fromEntity($savedReport);
    }

    /**
     * Execute a report and return results.
     */
    public function executeReport(int $reportId, bool $useCache = true): array
    {
        $report = $this->reportRepository->findById($reportId);

        if (!$report) {
            throw new \InvalidArgumentException("Report not found: {$reportId}");
        }

        // Validate report configuration
        $errors = $this->reportExecutionService->validateReportConfig($report);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid report configuration: ' . implode(', ', $errors));
        }

        $startTime = microtime(true);

        // Check if we should use cache
        $usedCache = false;
        if ($this->reportExecutionService->shouldUseCache($report, !$useCache)) {
            $result = $report->cachedResult();
            $usedCache = true;
        } else {
            // Execute report using infrastructure service
            $config = $this->reportExecutionService->buildExecutionConfig($report);
            $result = $this->reportService->executeReportQuery($config);

            // Update cache
            $ttl = $this->reportExecutionService->calculateCacheTTL($report);
            $report->updateCache($result, $ttl);
            $this->reportRepository->save($report);
        }

        $executionTime = microtime(true) - $startTime;

        // Dispatch domain event
        Event::dispatch(new ReportExecuted(
            reportId: $report->getId() ?? 0,
            moduleId: $report->moduleId(),
            reportType: $report->type()->value,
            usedCache: $usedCache,
            resultCount: $result['total'] ?? 0,
            executionTime: $executionTime,
            userId: $report->userId()?->value(),
        ));

        return $result;
    }

    /**
     * Get a report by ID.
     */
    public function getReport(int $reportId): ReportResponseDTO
    {
        $report = $this->reportRepository->findById($reportId);

        if (!$report) {
            throw new \InvalidArgumentException("Report not found: {$reportId}");
        }

        return ReportResponseDTO::fromEntity($report);
    }

    /**
     * Get all reports for a module.
     *
     * @return array<ReportResponseDTO>
     */
    public function getReportsForModule(int $moduleId): array
    {
        $reports = $this->reportRepository->findByModule($moduleId);

        return array_map(
            fn(Report $r) => ReportResponseDTO::fromEntity($r),
            $reports
        );
    }

    /**
     * Delete a report.
     */
    public function deleteReport(int $reportId): bool
    {
        return $this->reportRepository->delete($reportId);
    }

    // ========== Dashboard Use Cases ==========

    /**
     * Create a new dashboard.
     */
    public function createDashboard(CreateDashboardDTO $dto): DashboardResponseDTO
    {
        $dashboard = Dashboard::create(
            name: $dto->name,
            userId: $dto->userId ? UserId::fromInt($dto->userId) : null,
            description: $dto->description,
            isDefault: $dto->isDefault,
            isPublic: $dto->isPublic,
        );

        // If this is set as default, unset other defaults for this user
        if ($dto->isDefault && $dto->userId) {
            $this->dashboardRepository->unsetDefaultForUser($dto->userId);
        }

        $savedDashboard = $this->dashboardRepository->save($dashboard);

        // Dispatch domain event
        Event::dispatch(new DashboardCreated(
            dashboardId: $savedDashboard->getId() ?? 0,
            dashboardName: $savedDashboard->name(),
            isDefault: $savedDashboard->isDefault(),
            isPublic: $savedDashboard->isPublic(),
            userId: $savedDashboard->userId()?->value(),
        ));

        return DashboardResponseDTO::fromEntity($savedDashboard);
    }

    /**
     * Update an existing dashboard.
     */
    public function updateDashboard(
        int $dashboardId,
        string $name,
        ?string $description,
        array $settings,
        array $filters,
        int $refreshInterval,
    ): DashboardResponseDTO {
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if (!$dashboard) {
            throw new \InvalidArgumentException("Dashboard not found: {$dashboardId}");
        }

        $dashboard->update(
            name: $name,
            description: $description,
            settings: $settings,
            filters: $filters,
            refreshInterval: $refreshInterval,
        );

        $savedDashboard = $this->dashboardRepository->save($dashboard);

        // Dispatch domain event
        Event::dispatch(new DashboardUpdated(
            dashboardId: $savedDashboard->getId() ?? 0,
            dashboardName: $savedDashboard->name(),
            changedFields: ['name', 'description', 'settings', 'filters', 'refresh_interval'],
            userId: $savedDashboard->userId()?->value(),
        ));

        return DashboardResponseDTO::fromEntity($savedDashboard);
    }

    /**
     * Get a dashboard by ID.
     */
    public function getDashboard(int $dashboardId, bool $includeWidgets = false): DashboardResponseDTO
    {
        $dashboard = $this->dashboardRepository->findById($dashboardId, $includeWidgets);

        if (!$dashboard) {
            throw new \InvalidArgumentException("Dashboard not found: {$dashboardId}");
        }

        return DashboardResponseDTO::fromEntity($dashboard, $includeWidgets);
    }

    /**
     * Get all dashboards accessible by a user.
     *
     * @return array<DashboardResponseDTO>
     */
    public function getDashboardsForUser(int $userId): array
    {
        $dashboards = $this->dashboardRepository->findAccessibleByUser($userId);

        return array_map(
            fn(Dashboard $d) => DashboardResponseDTO::fromEntity($d),
            $dashboards
        );
    }

    /**
     * Set a dashboard as default for a user.
     */
    public function setDefaultDashboard(int $dashboardId, int $userId): DashboardResponseDTO
    {
        $dashboard = $this->dashboardRepository->findById($dashboardId);

        if (!$dashboard) {
            throw new \InvalidArgumentException("Dashboard not found: {$dashboardId}");
        }

        // Unset other defaults for this user
        $this->dashboardRepository->unsetDefaultForUser($userId, $dashboardId);

        $dashboard->setAsDefault();
        $savedDashboard = $this->dashboardRepository->save($dashboard);

        return DashboardResponseDTO::fromEntity($savedDashboard);
    }

    /**
     * Delete a dashboard.
     */
    public function deleteDashboard(int $dashboardId): bool
    {
        return $this->dashboardRepository->delete($dashboardId);
    }
}
