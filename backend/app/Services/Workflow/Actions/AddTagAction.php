<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Action to add tags to a record.
 */
class AddTagAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $recordId = $context['record']['id'] ?? null;
        $tagIds = $config['tag_ids'] ?? [];
        $tagNames = $config['tag_names'] ?? [];
        $createMissing = $config['create_missing'] ?? true;

        if (!$recordId) {
            throw new \InvalidArgumentException('Record ID is required');
        }

        $record = DB::table('module_records')->where('id', $recordId)->first();
        if (!$record) {
            throw new \InvalidArgumentException("Record not found: {$recordId}");
        }

        $tagsToAdd = [];

        // Collect tags by ID
        if (!empty($tagIds)) {
            $existingTags = DB::table('tags')->whereIn('id', $tagIds)->pluck('id')->toArray();
            $tagsToAdd = array_merge($tagsToAdd, $existingTags);
        }

        // Collect or create tags by name
        if (!empty($tagNames)) {
            foreach ($tagNames as $name) {
                $name = trim($name);
                if (empty($name)) {
                    continue;
                }

                $tag = DB::table('tags')->where('name', $name)->first();

                if (!$tag && $createMissing) {
                    $tagId = DB::table('tags')->insertGetId([
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'color' => $this->generateRandomColor(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $tagsToAdd[] = $tagId;
                } elseif ($tag) {
                    $tagsToAdd[] = $tag->id;
                }
            }
        }

        // Remove duplicates
        $tagsToAdd = array_unique($tagsToAdd);

        if (empty($tagsToAdd)) {
            return [
                'added' => false,
                'message' => 'No tags to add',
                'tags_added' => [],
            ];
        }

        // Add tags to record (sync without detaching existing)
        $existingTags = DB::table('taggables')
            ->where('taggable_id', $recordId)
            ->where('taggable_type', 'App\\Models\\ModuleRecord')
            ->pluck('tag_id')
            ->toArray();

        $newTags = array_diff($tagsToAdd, $existingTags);

        if (!empty($newTags)) {
            $inserts = [];
            foreach ($newTags as $tagId) {
                $inserts[] = [
                    'tag_id' => $tagId,
                    'taggable_id' => $recordId,
                    'taggable_type' => 'App\\Models\\ModuleRecord',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('taggables')->insert($inserts);
        }

        $addedTagNames = DB::table('tags')->whereIn('id', $newTags)->pluck('name')->toArray();

        Log::info('Workflow added tags to record', [
            'record_id' => $recordId,
            'tags_added' => $addedTagNames,
        ]);

        return [
            'added' => true,
            'tags_added' => $addedTagNames,
            'tags_added_count' => count($newTags),
            'tags_already_present' => count($tagsToAdd) - count($newTags),
        ];
    }

    /**
     * Generate a random hex color for new tags.
     */
    protected function generateRandomColor(): string
    {
        $colors = [
            '#3B82F6', // blue
            '#10B981', // green
            '#F59E0B', // amber
            '#EF4444', // red
            '#8B5CF6', // purple
            '#EC4899', // pink
            '#06B6D4', // cyan
            '#F97316', // orange
        ];

        return $colors[array_rand($colors)];
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'tag_ids',
                    'label' => 'Select Tags',
                    'type' => 'tag_multiselect',
                    'required' => false,
                    'description' => 'Select existing tags to add',
                ],
                [
                    'name' => 'tag_names',
                    'label' => 'Or Enter Tag Names',
                    'type' => 'tag_input',
                    'required' => false,
                    'description' => 'Enter tag names (comma-separated)',
                ],
                [
                    'name' => 'create_missing',
                    'label' => 'Create Missing Tags',
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Create tags that do not exist',
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['tag_ids']) && empty($config['tag_names'])) {
            $errors['tag_ids'] = 'At least one tag must be specified';
        }

        return $errors;
    }
}
