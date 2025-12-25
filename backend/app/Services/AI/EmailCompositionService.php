<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmailCompositionService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Generate an email draft
     */
    public function compose(
        string $purpose,
        ?string $recipientName = null,
        ?string $recipientCompany = null,
        ?array $context = [],
        ?string $tone = 'professional',
        ?string $recordModule = null,
        ?int $recordId = null
    ): AiEmailDraft {
        $messages = $this->buildComposePrompt(
            $purpose,
            $recipientName,
            $recipientCompany,
            $context,
            $tone
        );

        $response = $this->aiService->complete(
            $messages,
            'email_composition',
            1500,
            0.7,
            Auth::id(),
            $recordModule,
            $recordId
        );

        $parsed = $this->parseEmailResponse($response['content']);

        return DB::table('ai_email_drafts')->insertGetId([
            'user_id' => Auth::id(),
            'record_module' => $recordModule,
            'record_id' => $recordId,
            'purpose' => $purpose,
            'tone' => $tone,
            'context' => $context,
            'subject' => $parsed['subject'],
            'body' => $parsed['body'],
            'model_used' => $response['model'],
            'tokens_used' => $response['input_tokens'] + $response['output_tokens'],
        ]);
    }

    /**
     * Generate a reply to an email
     */
    public function generateReply(
        int $emailId,
        string $intent,
        ?string $additionalContext = null
    ): AiEmailDraft {
        $email = DB::table('email_messages')->where('id', $emailId)->first();

        $messages = $this->buildReplyPrompt($email, $intent, $additionalContext);

        $response = $this->aiService->complete(
            $messages,
            'email_reply',
            1500,
            0.7,
            Auth::id(),
            'email',
            $emailId
        );

        $parsed = $this->parseEmailResponse($response['content']);

        return DB::table('ai_email_drafts')->insertGetId([
            'user_id' => Auth::id(),
            'record_module' => $email->entity_type,
            'record_id' => $email->entity_id,
            'purpose' => "Reply: {$intent}",
            'tone' => 'professional',
            'context' => [
                'original_email_id' => $emailId,
                'original_subject' => $email->subject,
                'intent' => $intent,
            ],
            'subject' => $parsed['subject'],
            'body' => $parsed['body'],
            'model_used' => $response['model'],
            'tokens_used' => $response['input_tokens'] + $response['output_tokens'],
        ]);
    }

    /**
     * Improve an existing email draft
     */
    public function improve(
        string $currentSubject,
        string $currentBody,
        string $improvement,
        ?string $recordModule = null,
        ?int $recordId = null
    ): AiEmailDraft {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert email writer. Improve the given email based on the requested improvement. Return in the same JSON format: {"subject": "...", "body": "..."}',
            ],
            [
                'role' => 'user',
                'content' => "Current email:\nSubject: {$currentSubject}\n\nBody:\n{$currentBody}\n\nRequested improvement: {$improvement}\n\nReturn the improved email as JSON with \"subject\" and \"body\" fields.",
            ],
        ];

        $response = $this->aiService->complete(
            $messages,
            'email_improvement',
            1500,
            0.7,
            Auth::id(),
            $recordModule,
            $recordId
        );

        $parsed = $this->parseEmailResponse($response['content']);

        return DB::table('ai_email_drafts')->insertGetId([
            'user_id' => Auth::id(),
            'record_module' => $recordModule,
            'record_id' => $recordId,
            'purpose' => "Improvement: {$improvement}",
            'tone' => 'professional',
            'context' => [
                'original_subject' => $currentSubject,
                'improvement' => $improvement,
            ],
            'subject' => $parsed['subject'],
            'body' => $parsed['body'],
            'model_used' => $response['model'],
            'tokens_used' => $response['input_tokens'] + $response['output_tokens'],
        ]);
    }

    /**
     * Generate subject line suggestions
     */
    public function suggestSubjects(
        string $emailBody,
        int $count = 5,
        ?string $recordModule = null,
        ?int $recordId = null
    ): array {
        $messages = [
            [
                'role' => 'system',
                'content' => "You are an expert email marketer. Generate {$count} compelling subject line options for the given email body. Focus on clarity, engagement, and deliverability. Return as a JSON array of strings.",
            ],
            [
                'role' => 'user',
                'content' => "Email body:\n{$emailBody}\n\nGenerate {$count} subject line options as a JSON array.",
            ],
        ];

        $response = $this->aiService->complete(
            $messages,
            'subject_suggestions',
            500,
            0.8,
            Auth::id(),
            $recordModule,
            $recordId
        );

        $suggestions = json_decode($response['content'], true) ?? [];

        // Ensure we have an array
        if (!is_array($suggestions)) {
            $suggestions = [$response['content']];
        }

        return $suggestions;
    }

    /**
     * Analyze email tone and provide feedback
     */
    public function analyzeTone(string $emailBody): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Analyze the tone of the email and provide feedback. Return JSON with: {"tone": "string", "confidence": 0-1, "suggestions": ["array of suggestions"], "readability_score": 1-10}',
            ],
            [
                'role' => 'user',
                'content' => $emailBody,
            ],
        ];

        $response = $this->aiService->complete(
            $messages,
            'tone_analysis',
            500,
            0.3,
            Auth::id()
        );

        return json_decode($response['content'], true) ?? [
            'tone' => 'unknown',
            'confidence' => 0,
            'suggestions' => [],
            'readability_score' => 5,
        ];
    }

    /**
     * Build compose prompt
     */
    protected function buildComposePrompt(
        string $purpose,
        ?string $recipientName,
        ?string $recipientCompany,
        ?array $context,
        string $tone
    ): array {
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = "\n\nAdditional context:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        $recipientInfo = '';
        if ($recipientName) {
            $recipientInfo = "\nRecipient: {$recipientName}";
            if ($recipientCompany) {
                $recipientInfo .= " at {$recipientCompany}";
            }
        }

        return [
            [
                'role' => 'system',
                'content' => "You are an expert business email writer. Write professional, clear, and effective emails. Tone should be: {$tone}. Always return your response as JSON with \"subject\" and \"body\" fields.",
            ],
            [
                'role' => 'user',
                'content' => "Write an email for the following purpose: {$purpose}{$recipientInfo}{$contextStr}\n\nReturn as JSON with \"subject\" and \"body\" fields.",
            ],
        ];
    }

    /**
     * Build reply prompt
     */
    protected function buildReplyPrompt(
        EmailMessage $email,
        string $intent,
        ?string $additionalContext
    ): array {
        $originalContent = "Original email from {$email->from_email}:\nSubject: {$email->subject}\n\n{$email->body_text}";

        $contextStr = $additionalContext ? "\n\nAdditional context: {$additionalContext}" : '';

        return [
            [
                'role' => 'system',
                'content' => 'You are an expert business email writer. Write professional, clear, and effective email replies. Always return your response as JSON with "subject" and "body" fields.',
            ],
            [
                'role' => 'user',
                'content' => "{$originalContent}\n\nWrite a reply with this intent: {$intent}{$contextStr}\n\nReturn as JSON with \"subject\" and \"body\" fields.",
            ],
        ];
    }

    /**
     * Parse email response from AI
     */
    protected function parseEmailResponse(string $content): array
    {
        // Try to parse as JSON first
        $decoded = json_decode($content, true);
        if ($decoded && isset($decoded['subject']) && isset($decoded['body'])) {
            return $decoded;
        }

        // Fallback: try to extract from text
        $subject = '';
        $body = $content;

        // Try to find subject line
        if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $content, $matches)) {
            $subject = trim($matches[1]);
            $body = trim(preg_replace('/Subject:\s*.+?\n/i', '', $content));
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
