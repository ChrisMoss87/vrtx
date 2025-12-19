<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVariable;
use App\Models\GeneratedDocument;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DocumentTemplateService
{
    protected MergeFieldService $mergeFieldService;

    public function __construct(MergeFieldService $mergeFieldService)
    {
        $this->mergeFieldService = $mergeFieldService;
    }

    public function create(array $data): DocumentTemplate
    {
        $data['created_by'] = Auth::id();
        $data['merge_fields'] = $this->extractMergeFields($data['content'] ?? '');

        return DocumentTemplate::create($data);
    }

    public function update(DocumentTemplate $template, array $data): DocumentTemplate
    {
        $data['updated_by'] = Auth::id();

        if (isset($data['content'])) {
            $data['merge_fields'] = $this->extractMergeFields($data['content']);
        }

        $template->update($data);
        $template->incrementVersion();

        return $template->fresh();
    }

    public function delete(DocumentTemplate $template): bool
    {
        return $template->delete();
    }

    public function duplicate(DocumentTemplate $template): DocumentTemplate
    {
        return $template->duplicate(Auth::id());
    }

    public function generate(DocumentTemplate $template, string $recordType, int $recordId): GeneratedDocument
    {
        // Get the record data
        $data = $this->mergeFieldService->getRecordData($recordType, $recordId);

        // Merge the content
        $mergedContent = $this->mergeFieldService->merge($template->content, $data);

        // Apply conditional blocks
        $mergedContent = $this->applyConditionalBlocks($mergedContent, $template->conditional_blocks ?? [], $data);

        // Create the generated document
        $document = GeneratedDocument::create([
            'template_id' => $template->id,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'name' => $this->generateDocumentName($template, $data),
            'output_format' => $template->output_format,
            'merged_data' => $data,
            'created_by' => Auth::id(),
        ]);

        // Generate the actual file (PDF, DOCX, etc.)
        $this->generateFile($document, $mergedContent, $template);

        return $document;
    }

    public function preview(DocumentTemplate $template, string $recordType, int $recordId): string
    {
        $data = $this->mergeFieldService->getRecordData($recordType, $recordId);
        $mergedContent = $this->mergeFieldService->merge($template->content, $data);
        $mergedContent = $this->applyConditionalBlocks($mergedContent, $template->conditional_blocks ?? [], $data);

        return $mergedContent;
    }

    public function previewWithSampleData(DocumentTemplate $template): string
    {
        $data = $this->mergeFieldService->getSampleData();
        $mergedContent = $this->mergeFieldService->merge($template->content, $data);

        return $mergedContent;
    }

    protected function extractMergeFields(string $content): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

        return array_unique($matches[1] ?? []);
    }

    protected function applyConditionalBlocks(string $content, array $conditionalBlocks, array $data): string
    {
        foreach ($conditionalBlocks as $block) {
            $condition = $block['condition'] ?? null;
            $ifContent = $block['if_content'] ?? '';
            $elseContent = $block['else_content'] ?? '';
            $placeholder = $block['placeholder'] ?? null;

            if (!$condition || !$placeholder) {
                continue;
            }

            $showIf = $this->evaluateCondition($condition, $data);
            $replacement = $showIf ? $ifContent : $elseContent;

            $content = str_replace($placeholder, $replacement, $content);
        }

        return $content;
    }

    protected function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $fieldValue = data_get($data, $field);

        return match ($operator) {
            '=' => $fieldValue == $value,
            '!=' => $fieldValue != $value,
            '>' => $fieldValue > $value,
            '>=' => $fieldValue >= $value,
            '<' => $fieldValue < $value,
            '<=' => $fieldValue <= $value,
            'empty' => empty($fieldValue),
            'not_empty' => !empty($fieldValue),
            'contains' => str_contains((string) $fieldValue, (string) $value),
            default => false,
        };
    }

    protected function generateDocumentName(DocumentTemplate $template, array $data): string
    {
        $name = $template->name;

        // Add record identifier if available
        if (isset($data['contact']['name'])) {
            $name .= ' - ' . $data['contact']['name'];
        } elseif (isset($data['company']['name'])) {
            $name .= ' - ' . $data['company']['name'];
        }

        // Add date
        $name .= ' - ' . now()->format('Y-m-d');

        return $name;
    }

    protected function generateFile(GeneratedDocument $document, string $content, DocumentTemplate $template): void
    {
        $filename = Str::slug($document->name) . '-' . $document->id;

        switch ($template->output_format) {
            case DocumentTemplate::OUTPUT_PDF:
                $this->generatePdf($document, $content, $template, $filename);
                break;
            case DocumentTemplate::OUTPUT_DOCX:
                $this->generateDocx($document, $content, $template, $filename);
                break;
            case DocumentTemplate::OUTPUT_HTML:
                $this->generateHtml($document, $content, $template, $filename);
                break;
        }
    }

    protected function generatePdf(GeneratedDocument $document, string $content, DocumentTemplate $template, string $filename): void
    {
        // Apply template styling
        $html = $this->buildHtmlDocument($content, $template);

        // Store the HTML for now - in production, use DomPDF or similar
        $path = "documents/{$filename}.html";
        \Storage::put($path, $html);

        $document->file_path = $path;
        $document->file_url = \Storage::url($path);
        $document->file_size = strlen($html);
        $document->save();
    }

    protected function generateDocx(GeneratedDocument $document, string $content, DocumentTemplate $template, string $filename): void
    {
        // Store as HTML for now - in production, use PhpWord or similar
        $html = $this->buildHtmlDocument($content, $template);

        $path = "documents/{$filename}.html";
        \Storage::put($path, $html);

        $document->file_path = $path;
        $document->file_url = \Storage::url($path);
        $document->file_size = strlen($html);
        $document->save();
    }

    protected function generateHtml(GeneratedDocument $document, string $content, DocumentTemplate $template, string $filename): void
    {
        $html = $this->buildHtmlDocument($content, $template);

        $path = "documents/{$filename}.html";
        \Storage::put($path, $html);

        $document->file_path = $path;
        $document->file_url = \Storage::url($path);
        $document->file_size = strlen($html);
        $document->save();
    }

    protected function buildHtmlDocument(string $content, DocumentTemplate $template): string
    {
        $pageSettings = $template->page_settings ?? [];
        $headerSettings = $template->header_settings ?? [];
        $footerSettings = $template->footer_settings ?? [];

        $header = $headerSettings['content'] ?? '';
        $footer = $footerSettings['content'] ?? '';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: {$this->getMargin($pageSettings)};
            line-height: 1.6;
        }
        .header { margin-bottom: 20px; }
        .footer { margin-top: 20px; }
        .content { }
    </style>
</head>
<body>
    <div class="header">{$header}</div>
    <div class="content">{$content}</div>
    <div class="footer">{$footer}</div>
</body>
</html>
HTML;

        return $html;
    }

    protected function getMargin(array $pageSettings): string
    {
        $top = $pageSettings['margin_top'] ?? '20mm';
        $right = $pageSettings['margin_right'] ?? '20mm';
        $bottom = $pageSettings['margin_bottom'] ?? '20mm';
        $left = $pageSettings['margin_left'] ?? '20mm';

        return "{$top} {$right} {$bottom} {$left}";
    }

    public function getAvailableVariables(): array
    {
        return DocumentTemplateVariable::getGroupedVariables();
    }
}
