<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchIndex extends Model
{
    public $timestamps = false;

    protected $table = 'search_index';

    protected $fillable = [
        'module_id',
        'module_api_name',
        'record_id',
        'searchable_content',
        'primary_value',
        'secondary_value',
        'metadata',
        'indexed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'indexed_at' => 'datetime',
    ];

    /**
     * Get the module.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the record.
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }

    /**
     * Index a module record.
     */
    public static function indexRecord(ModuleRecord $record): self
    {
        $module = $record->module;
        $fields = $module->fields;

        // Build searchable content from all text-based fields
        $searchableContent = [];
        $primaryValue = null;
        $secondaryValue = null;
        $metadata = [];

        foreach ($fields as $field) {
            $value = $record->data[$field->api_name] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            // Add to searchable content for text-based fields
            if (in_array($field->type, ['text', 'textarea', 'email', 'phone', 'url', 'rich_text'])) {
                if (is_string($value)) {
                    // Strip HTML tags for rich text
                    $cleanValue = strip_tags($value);
                    $searchableContent[] = $cleanValue;
                }
            }

            // Check if this is the primary display field
            if ($field->is_primary || $field->api_name === 'name' || $field->api_name === 'title') {
                $primaryValue = $primaryValue ?? (string) $value;
            }

            // Look for email as secondary
            if ($field->type === 'email' && !$secondaryValue) {
                $secondaryValue = (string) $value;
            }

            // Add important fields to metadata
            if (in_array($field->type, ['email', 'phone', 'select', 'lookup'])) {
                $metadata[$field->api_name] = $value;
            }
        }

        // If no primary value found, use first text field or record ID
        if (!$primaryValue) {
            $primaryValue = $searchableContent[0] ?? "Record #{$record->id}";
        }

        return static::updateOrCreate(
            [
                'module_id' => $module->id,
                'record_id' => $record->id,
            ],
            [
                'module_api_name' => $module->api_name,
                'searchable_content' => implode(' ', $searchableContent),
                'primary_value' => substr($primaryValue, 0, 255),
                'secondary_value' => $secondaryValue ? substr($secondaryValue, 0, 255) : null,
                'metadata' => $metadata ?: null,
                'indexed_at' => now(),
            ]
        );
    }

    /**
     * Remove record from index.
     */
    public static function removeRecord(int $moduleId, int $recordId): void
    {
        static::where('module_id', $moduleId)
            ->where('record_id', $recordId)
            ->delete();
    }

    /**
     * Reindex all records for a module.
     */
    public static function reindexModule(Module $module): int
    {
        // Clear existing index entries for this module
        static::where('module_id', $module->id)->delete();

        $count = 0;
        $module->records()->chunk(100, function ($records) use (&$count) {
            foreach ($records as $record) {
                static::indexRecord($record);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Search using PostgreSQL full-text search.
     */
    public static function search(string $query, ?array $moduleApiNames = null, int $limit = 20)
    {
        $searchQuery = static::query()
            ->selectRaw("
                search_index.*,
                ts_rank(to_tsvector('english', searchable_content), plainto_tsquery('english', ?)) as relevance
            ", [$query])
            ->whereRaw("to_tsvector('english', searchable_content) @@ plainto_tsquery('english', ?)", [$query]);

        if ($moduleApiNames) {
            $searchQuery->whereIn('module_api_name', $moduleApiNames);
        }

        return $searchQuery
            ->orderByDesc('relevance')
            ->limit($limit)
            ->get();
    }

    /**
     * Simple search using ILIKE for partial matching.
     */
    public static function simpleSearch(string $query, ?array $moduleApiNames = null, int $limit = 20)
    {
        $searchTerm = '%' . strtolower($query) . '%';

        $searchQuery = static::query()
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(searchable_content) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(primary_value) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(secondary_value) LIKE ?', [$searchTerm]);
            });

        if ($moduleApiNames) {
            $searchQuery->whereIn('module_api_name', $moduleApiNames);
        }

        return $searchQuery
            ->orderBy('primary_value')
            ->limit($limit)
            ->get();
    }
}
