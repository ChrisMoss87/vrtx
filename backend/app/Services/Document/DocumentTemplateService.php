<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Infrastructure\Services\Pdf\ChromePdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateService
{
    public function __construct(
        protected MergeFieldService $mergeFieldService,
        protected ChromePdfService $pdfService
    ) {}

    public function findById(int $id): ?object
    {
        return DB::table('document_templates')->where('id', $id)->first();
    }

    public function create(array $data): object
    {
        $data['created_by'] = Auth::id();
        $data['merge_fields'] = json_encode($this->extractMergeFields($data['content'] ?? ''));
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // Encode JSON fields
        foreach (['page_settings', 'header_settings', 'footer_settings', 'conditional_blocks'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        $id = DB::table('document_templates')->insertGetId($data);

        return $this->findById($id);
    }

    public function updateById(int $id, array $data): object
    {
        $data['updated_by'] = Auth::id();
        $data['updated_at'] = now();

        if (isset($data['content'])) {
            $data['merge_fields'] = json_encode($this->extractMergeFields($data['content']));
        }

        // Encode JSON fields
        foreach (['page_settings', 'header_settings', 'footer_settings', 'conditional_blocks'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        // Increment version
        DB::table('document_templates')->where('id', $id)->increment('version');
        DB::table('document_templates')->where('id', $id)->update($data);

        return $this->findById($id);
    }

    public function deleteById(int $id): bool
    {
        return DB::table('document_templates')->where('id', $id)->delete() > 0;
    }

    public function duplicateById(int $id): object
    {
        $template = $this->findById($id);

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

        $newData = (array) $template;
        unset($newData['id']);
        $newData['name'] = $template->name . ' (Copy)';
        $newData['created_by'] = Auth::id();
        $newData['created_at'] = now();
        $newData['updated_at'] = now();
        $newData['version'] = 1;

        $newId = DB::table('document_templates')->insertGetId($newData);

        return $this->findById($newId);
    }

    public function generateById(int $id, string $recordType, int $recordId): object
    {
        $template = $this->findById($id);

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

        // Get the record data
        $data = $this->mergeFieldService->getRecordData($recordType, $recordId);

        // Merge the content
        $mergedContent = $this->mergeFieldService->merge($template->content, $data);

        // Apply conditional blocks
        $conditionalBlocks = is_string($template->conditional_blocks)
            ? json_decode($template->conditional_blocks, true)
            : ($template->conditional_blocks ?? []);
        $mergedContent = $this->applyConditionalBlocks($mergedContent, $conditionalBlocks, $data);

        // Create the generated document
        $documentId = DB::table('generated_documents')->insertGetId([
            'template_id' => $template->id,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'name' => $this->generateDocumentNameFromTemplate($template, $data),
            'output_format' => $template->output_format ?? 'pdf',
            'merged_data' => json_encode($data),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $document = DB::table('generated_documents')->where('id', $documentId)->first();

        // Generate the actual file (PDF, DOCX, etc.)
        $this->generateFileFromTemplate($document, $mergedContent, $template);

        return DB::table('generated_documents')->where('id', $documentId)->first();
    }

    public function previewById(int $id, string $recordType, int $recordId): string
    {
        $template = $this->findById($id);

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

        $data = $this->mergeFieldService->getRecordData($recordType, $recordId);
        $mergedContent = $this->mergeFieldService->merge($template->content, $data);

        $conditionalBlocks = is_string($template->conditional_blocks)
            ? json_decode($template->conditional_blocks, true)
            : ($template->conditional_blocks ?? []);
        $mergedContent = $this->applyConditionalBlocks($mergedContent, $conditionalBlocks, $data);

        return $mergedContent;
    }

    public function previewWithSampleDataById(int $id): string
    {
        $template = $this->findById($id);

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

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

    protected function generateDocumentNameFromTemplate(object $template, array $data): string
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

    protected function generateFileFromTemplate(object $document, string $content, object $template): void
    {
        $filename = Str::slug($document->name) . '-' . $document->id;
        $outputFormat = $template->output_format ?? 'pdf';

        switch ($outputFormat) {
            case 'pdf':
                $this->generatePdfFromTemplate($document, $content, $template, $filename);
                break;
            case 'docx':
                $this->generateDocxFromTemplate($document, $content, $template, $filename);
                break;
            case 'html':
                $this->generateHtmlFromTemplate($document, $content, $template, $filename);
                break;
        }
    }

    protected function generatePdfFromTemplate(object $document, string $content, object $template, string $filename): void
    {
        // Apply template styling
        $html = $this->buildHtmlDocumentFromTemplate($content, $template);

        $pageSettings = is_string($template->page_settings)
            ? json_decode($template->page_settings, true)
            : ($template->page_settings ?? []);

        // Generate PDF using headless Chrome
        $pdfContent = $this->pdfService->generateFromHtml($html, [
            'paper_size' => $pageSettings['paper_size'] ?? 'A4',
            'landscape' => ($pageSettings['orientation'] ?? 'portrait') === 'landscape',
            'print_background' => true,
        ]);

        $path = "documents/{$filename}.pdf";
        Storage::put($path, $pdfContent);

        DB::table('generated_documents')->where('id', $document->id)->update([
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'file_size' => strlen($pdfContent),
            'updated_at' => now(),
        ]);
    }

    protected function generateDocxFromTemplate(object $document, string $content, object $template, string $filename): void
    {
        // Store as HTML for now - in production, use PhpWord or similar
        $html = $this->buildHtmlDocumentFromTemplate($content, $template);

        $path = "documents/{$filename}.html";
        Storage::put($path, $html);

        DB::table('generated_documents')->where('id', $document->id)->update([
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'file_size' => strlen($html),
            'updated_at' => now(),
        ]);
    }

    protected function generateHtmlFromTemplate(object $document, string $content, object $template, string $filename): void
    {
        $html = $this->buildHtmlDocumentFromTemplate($content, $template);

        $path = "documents/{$filename}.html";
        Storage::put($path, $html);

        DB::table('generated_documents')->where('id', $document->id)->update([
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'file_size' => strlen($html),
            'updated_at' => now(),
        ]);
    }

    protected function buildHtmlDocumentFromTemplate(string $content, object $template): string
    {
        $pageSettings = is_string($template->page_settings)
            ? json_decode($template->page_settings, true)
            : ($template->page_settings ?? []);
        $headerSettings = is_string($template->header_settings)
            ? json_decode($template->header_settings, true)
            : ($template->header_settings ?? []);
        $footerSettings = is_string($template->footer_settings)
            ? json_decode($template->footer_settings, true)
            : ($template->footer_settings ?? []);

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
