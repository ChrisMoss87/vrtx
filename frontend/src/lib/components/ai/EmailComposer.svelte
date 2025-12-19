<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Badge } from '$lib/components/ui/badge';
  import {
    composeEmail,
    improveEmail,
    suggestSubjects,
    analyzeEmailTone,
    type EmailDraft,
    type ToneAnalysis
  } from '$lib/api/ai';
  import { Loader2, Sparkles, Wand2, RefreshCw, Copy, Check } from 'lucide-svelte';

  interface Props {
    recordModule?: string;
    recordId?: number;
    recipientName?: string;
    recipientCompany?: string;
    onUseEmail?: (subject: string, body: string) => void;
  }

  let {
    recordModule = undefined,
    recordId = undefined,
    recipientName = undefined,
    recipientCompany = undefined,
    onUseEmail = undefined
  }: Props = $props();

  let purpose = $state('');
  let context = $state('');
  let tone = $state<'professional' | 'friendly' | 'formal' | 'casual' | 'urgent'>('professional');

  let generatedSubject = $state('');
  let generatedBody = $state('');
  let improvement = $state('');

  let loading = $state(false);
  let improving = $state(false);
  let suggestingSubjects = $state(false);
  let analyzingTone = $state(false);
  let copied = $state(false);

  let subjectSuggestions = $state<string[]>([]);
  let toneAnalysis = $state<ToneAnalysis | null>(null);

  async function handleCompose() {
    if (!purpose.trim()) return;

    loading = true;
    try {
      const result = await composeEmail({
        purpose,
        recipient_name: recipientName,
        recipient_company: recipientCompany,
        context: context ? { notes: context } : undefined,
        tone,
        record_module: recordModule,
        record_id: recordId
      });

      generatedSubject = result.draft.subject;
      generatedBody = result.draft.body;
      toneAnalysis = null;
      subjectSuggestions = [];
    } catch (error) {
      console.error('Failed to compose email:', error);
    } finally {
      loading = false;
    }
  }

  async function handleImprove() {
    if (!improvement.trim() || !generatedBody) return;

    improving = true;
    try {
      const result = await improveEmail({
        subject: generatedSubject,
        body: generatedBody,
        improvement,
        record_module: recordModule,
        record_id: recordId
      });

      generatedSubject = result.draft.subject;
      generatedBody = result.draft.body;
      improvement = '';
      toneAnalysis = null;
    } catch (error) {
      console.error('Failed to improve email:', error);
    } finally {
      improving = false;
    }
  }

  async function handleSuggestSubjects() {
    if (!generatedBody) return;

    suggestingSubjects = true;
    try {
      const result = await suggestSubjects({
        body: generatedBody,
        count: 5,
        record_module: recordModule,
        record_id: recordId
      });
      subjectSuggestions = result.suggestions;
    } catch (error) {
      console.error('Failed to suggest subjects:', error);
    } finally {
      suggestingSubjects = false;
    }
  }

  async function handleAnalyzeTone() {
    if (!generatedBody) return;

    analyzingTone = true;
    try {
      const result = await analyzeEmailTone(generatedBody);
      toneAnalysis = result.analysis;
    } catch (error) {
      console.error('Failed to analyze tone:', error);
    } finally {
      analyzingTone = false;
    }
  }

  function handleCopy() {
    navigator.clipboard.writeText(`Subject: ${generatedSubject}\n\n${generatedBody}`);
    copied = true;
    setTimeout(() => (copied = false), 2000);
  }

  function handleUse() {
    if (onUseEmail && generatedSubject && generatedBody) {
      onUseEmail(generatedSubject, generatedBody);
    }
  }

  function selectSuggestion(suggestion: string) {
    generatedSubject = suggestion;
  }

  function handleToneChange(value: string | undefined) {
    if (value) {
      tone = value as typeof tone;
    }
  }

  const tones = [
    { value: 'professional', label: 'Professional' },
    { value: 'friendly', label: 'Friendly' },
    { value: 'formal', label: 'Formal' },
    { value: 'casual', label: 'Casual' },
    { value: 'urgent', label: 'Urgent' }
  ] as const;

  const selectedToneLabel = $derived(tones.find(t => t.value === tone)?.label || tone);
</script>

<div class="space-y-6">
  <!-- Compose Section -->
  <Card.Root>
    <Card.Header>
      <Card.Title class="flex items-center gap-2">
        <Sparkles class="h-5 w-5" />
        AI Email Composer
      </Card.Title>
      <Card.Description>
        Describe what you want to say and let AI write the email for you
      </Card.Description>
    </Card.Header>
    <Card.Content class="space-y-4">
      <div class="space-y-2">
        <Label>What do you want to say?</Label>
        <Textarea
          bind:value={purpose}
          placeholder="e.g., Follow up on our meeting yesterday, thank them for their time, and propose next steps for the partnership"
          rows={3}
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label>Tone</Label>
          <Select.Root type="single" value={tone} onValueChange={handleToneChange}>
            <Select.Trigger>
              <span>{selectedToneLabel}</span>
            </Select.Trigger>
            <Select.Content>
              {#each tones as t}
                <Select.Item value={t.value} label={t.label}>{t.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>

        <div class="space-y-2">
          <Label>Additional Context (optional)</Label>
          <Input
            bind:value={context}
            placeholder="Any extra context..."
          />
        </div>
      </div>
    </Card.Content>
    <Card.Footer>
      <Button onclick={handleCompose} disabled={loading || !purpose.trim()} class="w-full">
        {#if loading}
          <Loader2 class="mr-2 h-4 w-4 animate-spin" />
          Generating...
        {:else}
          <Sparkles class="mr-2 h-4 w-4" />
          Generate Email
        {/if}
      </Button>
    </Card.Footer>
  </Card.Root>

  <!-- Generated Email -->
  {#if generatedSubject || generatedBody}
    <Card.Root>
      <Card.Header>
        <Card.Title>Generated Email</Card.Title>
      </Card.Header>
      <Card.Content class="space-y-4">
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <Label>Subject</Label>
            <Button
              variant="ghost"
              size="sm"
              onclick={handleSuggestSubjects}
              disabled={suggestingSubjects}
            >
              {#if suggestingSubjects}
                <Loader2 class="mr-1 h-3 w-3 animate-spin" />
              {:else}
                <RefreshCw class="mr-1 h-3 w-3" />
              {/if}
              Suggest Alternatives
            </Button>
          </div>
          <Input bind:value={generatedSubject} />

          {#if subjectSuggestions.length > 0}
            <div class="flex flex-wrap gap-2 mt-2">
              {#each subjectSuggestions as suggestion}
                <Badge
                  variant="outline"
                  class="cursor-pointer hover:bg-accent"
                  onclick={() => selectSuggestion(suggestion)}
                >
                  {suggestion}
                </Badge>
              {/each}
            </div>
          {/if}
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <Label>Body</Label>
            <Button
              variant="ghost"
              size="sm"
              onclick={handleAnalyzeTone}
              disabled={analyzingTone}
            >
              {#if analyzingTone}
                <Loader2 class="mr-1 h-3 w-3 animate-spin" />
              {:else}
                <Wand2 class="mr-1 h-3 w-3" />
              {/if}
              Analyze Tone
            </Button>
          </div>
          <Textarea bind:value={generatedBody} rows={10} />
        </div>

        {#if toneAnalysis}
          <div class="p-4 bg-muted rounded-lg space-y-2">
            <div class="flex items-center gap-2">
              <span class="font-medium">Tone:</span>
              <Badge>{toneAnalysis.tone}</Badge>
              <span class="text-sm text-muted-foreground">
                ({Math.round(toneAnalysis.confidence * 100)}% confident)
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-medium">Readability:</span>
              <span>{toneAnalysis.readability_score}/10</span>
            </div>
            {#if toneAnalysis.suggestions.length > 0}
              <div>
                <span class="font-medium">Suggestions:</span>
                <ul class="list-disc list-inside text-sm text-muted-foreground">
                  {#each toneAnalysis.suggestions as suggestion}
                    <li>{suggestion}</li>
                  {/each}
                </ul>
              </div>
            {/if}
          </div>
        {/if}

        <!-- Improve Section -->
        <div class="border-t pt-4 space-y-2">
          <Label>Want to improve it?</Label>
          <div class="flex gap-2">
            <Input
              bind:value={improvement}
              placeholder="e.g., Make it more concise, Add a call to action, Sound more enthusiastic"
              class="flex-1"
            />
            <Button onclick={handleImprove} disabled={improving || !improvement.trim()}>
              {#if improving}
                <Loader2 class="mr-2 h-4 w-4 animate-spin" />
              {:else}
                <Wand2 class="mr-2 h-4 w-4" />
              {/if}
              Improve
            </Button>
          </div>
        </div>
      </Card.Content>
      <Card.Footer class="flex justify-end gap-2">
        <Button variant="outline" onclick={handleCopy}>
          {#if copied}
            <Check class="mr-2 h-4 w-4" />
            Copied!
          {:else}
            <Copy class="mr-2 h-4 w-4" />
            Copy
          {/if}
        </Button>
        {#if onUseEmail}
          <Button onclick={handleUse}>Use This Email</Button>
        {/if}
      </Card.Footer>
    </Card.Root>
  {/if}
</div>
