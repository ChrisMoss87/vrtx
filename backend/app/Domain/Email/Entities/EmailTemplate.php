<?php

declare(strict_types=1);

namespace App\Domain\Email\Entities;

class EmailTemplate
{
    private ?int $id = null;
    private string $name;
    private ?string $subject;
    private ?string $bodyHtml;
    private ?string $bodyText;
    private ?int $moduleId;
    private ?int $folderId;
    private bool $isShared;
    private bool $isActive;
    private array $variables;
    private ?int $createdBy;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->subject = null;
        $this->bodyHtml = null;
        $this->bodyText = null;
        $this->moduleId = null;
        $this->folderId = null;
        $this->isShared = false;
        $this->isActive = true;
        $this->variables = [];
        $this->createdBy = null;
    }

    public static function create(string $name, ?int $createdBy = null): self
    {
        $template = new self($name);
        $template->createdBy = $createdBy;
        return $template;
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        ?int $moduleId,
        ?int $folderId,
        bool $isShared,
        bool $isActive,
        array $variables,
        ?int $createdBy,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $template = new self($name);
        $template->id = $id;
        $template->subject = $subject;
        $template->bodyHtml = $bodyHtml;
        $template->bodyText = $bodyText;
        $template->moduleId = $moduleId;
        $template->folderId = $folderId;
        $template->isShared = $isShared;
        $template->isActive = $isActive;
        $template->variables = $variables;
        $template->createdBy = $createdBy;
        $template->createdAt = $createdAt;
        $template->updatedAt = $updatedAt;
        return $template;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSubject(): ?string { return $this->subject; }
    public function getBodyHtml(): ?string { return $this->bodyHtml; }
    public function getBodyText(): ?string { return $this->bodyText; }
    public function getModuleId(): ?int { return $this->moduleId; }
    public function isShared(): bool { return $this->isShared; }
    public function isActive(): bool { return $this->isActive; }
    public function getVariables(): array { return $this->variables; }

    public function update(string $name, ?string $subject, ?string $bodyHtml, ?string $bodyText = null): void
    {
        $this->name = $name;
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
        $this->bodyText = $bodyText ?? ($bodyHtml ? strip_tags($bodyHtml) : null);
        $this->extractVariables();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function share(): void
    {
        $this->isShared = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unshare(): void
    {
        $this->isShared = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function extractVariables(): void
    {
        $pattern = '/\{\{([^}]+)\}\}/';
        $matches = [];
        preg_match_all($pattern, $this->bodyHtml ?? '', $matches);
        preg_match_all($pattern, $this->subject ?? '', $subjectMatches);
        $this->variables = array_unique(array_merge($matches[1] ?? [], $subjectMatches[1] ?? []));
    }

    /**
     * Render the email template with data.
     *
     * SECURITY: All variable values are HTML-escaped to prevent XSS attacks.
     * If you need to insert trusted HTML content, use the {{{variable}}} syntax
     * (triple braces) which bypasses escaping.
     *
     * @param array<string, mixed> $data Template variables and their values
     * @return array{subject: string, body_html: string, body_text: string}
     */
    public function render(array $data): array
    {
        $subject = $this->subject ?? '';
        $body = $this->bodyHtml ?? '';

        foreach ($data as $key => $value) {
            $stringValue = (string) $value;

            // Regular placeholder - HTML escape for safety
            $placeholder = '{{' . $key . '}}';
            $escapedValue = htmlspecialchars($stringValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = str_replace($placeholder, $escapedValue, $body);

            // Subject line doesn't need HTML escaping (plain text)
            $subject = str_replace($placeholder, $stringValue, $subject);

            // Triple-brace placeholder for trusted HTML content (no escaping)
            $rawPlaceholder = '{{{' . $key . '}}}';
            $body = str_replace($rawPlaceholder, $stringValue, $body);
        }

        return [
            'subject' => $subject,
            'body_html' => $body,
            'body_text' => strip_tags($body),
        ];
    }
}
