<?php

declare(strict_types=1);

namespace App\Services\Proposal;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    public function create(array $data): Proposal
    {
        $data['created_by'] = Auth::id();

        // Generate proposal number
        if (empty($data['proposal_number'])) {
            $data['proposal_number'] = $this->generateProposalNumber();
        }

        $proposal = DB::table('proposals')->insertGetId($data);

        // Create sections from template if provided
        if (!empty($data['template_id'])) {
            $this->applyTemplate($proposal, ProposalTemplate::find($data['template_id']));
        }

        // Create custom sections if provided
        if (!empty($data['sections'])) {
            foreach ($data['sections'] as $index => $sectionData) {
                $sectionData['display_order'] = $sectionData['display_order'] ?? $index;
                $proposal->sections()->create($sectionData);
            }
        }

        // Create pricing items if provided
        if (!empty($data['pricing_items'])) {
            foreach ($data['pricing_items'] as $index => $itemData) {
                $itemData['display_order'] = $itemData['display_order'] ?? $index;
                $proposal->pricingItems()->create($itemData);
            }
        }

        $proposal->calculateTotal();

        return $proposal->load(['sections', 'pricingItems']);
    }

    public function update(Proposal $proposal, array $data): Proposal
    {
        $proposal->update($data);

        // Update sections if provided
        if (isset($data['sections'])) {
            foreach ($data['sections'] as $sectionData) {
                if (isset($sectionData['id'])) {
                    $section = $proposal->sections()->find($sectionData['id']);
                    if ($section) {
                        $section->update($sectionData);
                    }
                } else {
                    $proposal->sections()->create($sectionData);
                }
            }
        }

        // Update pricing items if provided
        if (isset($data['pricing_items'])) {
            foreach ($data['pricing_items'] as $itemData) {
                if (isset($itemData['id'])) {
                    $item = $proposal->pricingItems()->find($itemData['id']);
                    if ($item) {
                        $item->update($itemData);
                    }
                } else {
                    $proposal->pricingItems()->create($itemData);
                }
            }

            $proposal->calculateTotal();
        }

        $proposal->version++;
        $proposal->save();

        return $proposal->fresh(['sections', 'pricingItems']);
    }

    public function delete(Proposal $proposal): bool
    {
        return $proposal->delete();
    }

    public function duplicate(Proposal $proposal): Proposal
    {
        return $proposal->duplicate(Auth::id());
    }

    public function send(Proposal $proposal, string $email, ?string $message = null): void
    {
        $proposal->send($email);

        // In production, send email notification
        // Mail::to($email)->send(new ProposalMail($proposal, $message));
    }

    public function accept(Proposal $proposal, string $acceptedBy, ?string $signature = null): void
    {
        if (!$proposal->canBeSent()) {
            throw new \Exception('This proposal cannot be accepted.');
        }

        if ($proposal->isExpired()) {
            throw new \Exception('This proposal has expired.');
        }

        $proposal->accept($acceptedBy, $signature, request()->ip());

        // In production, send confirmation and notify team
    }

    public function reject(Proposal $proposal, string $rejectedBy, ?string $reason = null): void
    {
        $proposal->reject($rejectedBy, $reason);

        // In production, notify team
    }

    public function recordView(Proposal $proposal, ?string $email = null, ?string $name = null): ProposalView
    {
        $sessionId = Str::random(32);
        return $proposal->recordView($sessionId, $email, $name);
    }

    public function updateViewSession(ProposalView $view, array $data): void
    {
        if (isset($data['sections_viewed'])) {
            foreach ($data['sections_viewed'] as $sectionId => $seconds) {
                $view->trackSectionView($sectionId, $seconds);
            }
        }

        if (isset($data['ended'])) {
            $view->endSession();
        }
    }

    public function addComment(Proposal $proposal, array $data): ProposalComment
    {
        $data['proposal_id'] = $proposal->id;

        return DB::table('proposal_comments')->insertGetId($data);
    }

    public function resolveComment(ProposalComment $comment, int $userId): void
    {
        $comment->resolve($userId);
    }

    public function addSection(Proposal $proposal, array $data): ProposalSection
    {
        $maxOrder = $proposal->sections()->max('display_order') ?? 0;
        $data['display_order'] = $data['display_order'] ?? ($maxOrder + 1);

        return $proposal->sections()->create($data);
    }

    public function updateSection(ProposalSection $section, array $data): ProposalSection
    {
        $section->update($data);
        return $section->fresh();
    }

    public function deleteSection(ProposalSection $section): bool
    {
        return $section->delete();
    }

    public function reorderSections(Proposal $proposal, array $order): void
    {
        foreach ($order as $index => $sectionId) {
            $proposal->sections()
                ->where('id', $sectionId)
                ->update(['display_order' => $index]);
        }
    }

    public function addPricingItem(Proposal $proposal, array $data): ProposalPricingItem
    {
        $maxOrder = $proposal->pricingItems()->max('display_order') ?? 0;
        $data['display_order'] = $data['display_order'] ?? ($maxOrder + 1);

        $item = $proposal->pricingItems()->create($data);
        $proposal->calculateTotal();

        return $item;
    }

    public function updatePricingItem(ProposalPricingItem $item, array $data): ProposalPricingItem
    {
        $item->update($data);
        return $item->fresh();
    }

    public function deletePricingItem(ProposalPricingItem $item): bool
    {
        $result = $item->delete();
        return $result;
    }

    public function toggleOptionalItem(ProposalPricingItem $item): void
    {
        $item->toggleSelection();
    }

    public function getAnalytics(Proposal $proposal): array
    {
        $views = $proposal->views;

        return [
            'total_views' => $proposal->view_count,
            'unique_viewers' => $views->unique('viewer_email')->count(),
            'total_time_spent' => $proposal->total_time_spent,
            'average_time_per_view' => $proposal->view_count > 0
                ? round($proposal->total_time_spent / $proposal->view_count)
                : 0,
            'first_viewed_at' => $proposal->first_viewed_at,
            'last_viewed_at' => $proposal->last_viewed_at,
            'section_engagement' => $this->calculateSectionEngagement($views),
            'device_breakdown' => $views->groupBy('device_type')->map->count(),
            'view_history' => $views->map(fn ($v) => [
                'viewer_email' => $v->viewer_email,
                'viewer_name' => $v->viewer_name,
                'started_at' => $v->started_at,
                'time_spent' => $v->time_spent,
                'device_type' => $v->device_type,
            ]),
        ];
    }

    protected function calculateSectionEngagement($views): array
    {
        $sectionTimes = [];

        foreach ($views as $view) {
            foreach ($view->sections_viewed ?? [] as $sectionId => $seconds) {
                if (!isset($sectionTimes[$sectionId])) {
                    $sectionTimes[$sectionId] = 0;
                }
                $sectionTimes[$sectionId] += $seconds;
            }
        }

        arsort($sectionTimes);

        return $sectionTimes;
    }

    protected function applyTemplate(Proposal $proposal, ?ProposalTemplate $template): void
    {
        if (!$template) {
            return;
        }

        $proposal->styling = $template->styling;
        $proposal->save();

        // Create sections from template
        if (!empty($template->default_sections)) {
            foreach ($template->default_sections as $index => $sectionData) {
                $proposal->sections()->create([
                    'section_type' => $sectionData['type'] ?? ProposalSection::TYPE_CUSTOM,
                    'title' => $sectionData['title'] ?? 'Section ' . ($index + 1),
                    'content' => $sectionData['content'] ?? '',
                    'settings' => $sectionData['settings'] ?? null,
                    'display_order' => $index,
                ]);
            }
        }
    }

    protected function generateProposalNumber(): string
    {
        $prefix = 'PROP';
        $date = now()->format('Ymd');
        $sequence = Proposal::whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function getContentBlocks(?string $category = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ProposalContentBlock::active();

        if ($category) {
            $query->category($category);
        }

        return $query->get();
    }
}
