<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use App\Models\ModuleRecord;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;

/**
 * Action to remove tags from a record.
 */
class RemoveTagAction implements ActionInterface
{
    public const MODE_SPECIFIC = 'specific';
    public const MODE_ALL = 'all';
    public const MODE_MATCHING = 'matching';

    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $mode = $config['mode'] ?? self::MODE_SPECIFIC;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = ModuleRecord::find($recordId);
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $removedTags = [];

        switch ($mode) {
            case self::MODE_ALL:
                $removedTags = $record->tags()->pluck('name')->toArray();
                $record->tags()->detach();
                break;

            case self::MODE_MATCHING:
                $pattern = $config['pattern'] ?? '';
                if (!empty($pattern)) {
                    $tagsToRemove = $record->tags()
                        ->where('name', 'LIKE', "%{$pattern}%")
                        ->get();
                    $removedTags = $tagsToRemove->pluck('name')->toArray();
                    $record->tags()->detach($tagsToRemove->pluck('id'));
                }
                break;

            case self::MODE_SPECIFIC:
            default:
                $tagIds = $config['tag_ids'] ?? [];
                $tagNames = $config['tag_names'] ?? [];

                $tagsToRemove = collect();

                if (!empty($tagIds)) {
                    $tagsToRemove = $tagsToRemove->merge(
                        Tag::whereIn('id', $tagIds)->get()
                    );
                }

                if (!empty($tagNames)) {
                    $tagsToRemove = $tagsToRemove->merge(
                        Tag::whereIn('name', $tagNames)->get()
                    );
                }

                $tagsToRemove = $tagsToRemove->unique('id');

                if ($tagsToRemove->isNotEmpty()) {
                    $removedTags = $tagsToRemove->pluck('name')->toArray();
                    $record->tags()->detach($tagsToRemove->pluck('id'));
                }
                break;
        }

        Log::info('Workflow removed tags from record', [
            'record_id' => $recordId,
            'mode' => $mode,
            'tags_removed' => $removedTags,
        ]);

        return [
            'removed' => true,
            'tags_removed' => $removedTags,
            'tags_removed_count' => count($removedTags),
        ];
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'mode',
                    'label' => 'Removal Mode',
                    'type' => 'select',
                    'required' => true,
                    'default' => self::MODE_SPECIFIC,
                    'options' => [
                        ['value' => self::MODE_SPECIFIC, 'label' => 'Specific Tags'],
                        ['value' => self::MODE_MATCHING, 'label' => 'Tags Matching Pattern'],
                        ['value' => self::MODE_ALL, 'label' => 'Remove All Tags'],
                    ],
                ],
                [
                    'name' => 'tag_ids',
                    'label' => 'Select Tags',
                    'type' => 'tag_multiselect',
                    'required' => false,
                    'description' => 'Select tags to remove',
                    'show_when' => ['mode' => self::MODE_SPECIFIC],
                ],
                [
                    'name' => 'tag_names',
                    'label' => 'Or Enter Tag Names',
                    'type' => 'tag_input',
                    'required' => false,
                    'description' => 'Enter tag names to remove (comma-separated)',
                    'show_when' => ['mode' => self::MODE_SPECIFIC],
                ],
                [
                    'name' => 'pattern',
                    'label' => 'Pattern',
                    'type' => 'text',
                    'required' => false,
                    'description' => 'Remove tags containing this text',
                    'show_when' => ['mode' => self::MODE_MATCHING],
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];
        $mode = $config['mode'] ?? self::MODE_SPECIFIC;

        if ($mode === self::MODE_SPECIFIC) {
            if (empty($config['tag_ids']) && empty($config['tag_names'])) {
                $errors['tag_ids'] = 'At least one tag must be specified';
            }
        }

        if ($mode === self::MODE_MATCHING && empty($config['pattern'])) {
            $errors['pattern'] = 'Pattern is required for matching mode';
        }

        return $errors;
    }
}
