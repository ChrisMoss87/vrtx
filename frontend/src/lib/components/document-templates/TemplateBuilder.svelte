<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Select from '$lib/components/ui/select';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import type { DocumentTemplate, MergeFieldVariable, ConditionalBlock } from '$lib/api/document-templates';
  import MergeFieldPicker from './MergeFieldPicker.svelte';

  export let template: Partial<DocumentTemplate> = {};
  export let variables: Record<string, MergeFieldVariable[]> = {};
  export let loading = false;

  const dispatch = createEventDispatcher<{
    save: Partial<DocumentTemplate>;
    preview: void;
    cancel: void;
  }>();

  let name = template.name || '';
  let category = template.category || '';
  let description = template.description || '';
  let content = template.content || '';
  let outputFormat = template.output_format || 'pdf';
  let isShared = template.is_shared || false;
  let conditionalBlocks: ConditionalBlock[] = template.conditional_blocks || [];

  // Page settings
  let marginTop = template.page_settings?.margin_top || '20mm';
  let marginRight = template.page_settings?.margin_right || '20mm';
  let marginBottom = template.page_settings?.margin_bottom || '20mm';
  let marginLeft = template.page_settings?.margin_left || '20mm';
  let orientation = template.page_settings?.orientation || 'portrait';

  // Header/Footer
  let headerContent = template.header_settings?.content || '';
  let footerContent = template.footer_settings?.content || '';

  const categories = [
    { value: 'contract', label: 'Contract' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'letter', label: 'Letter' },
    { value: 'agreement', label: 'Agreement' },
    { value: 'quote', label: 'Quote' },
    { value: 'invoice', label: 'Invoice' },
    { value: 'other', label: 'Other' },
  ];

  const outputFormats = [
    { value: 'pdf', label: 'PDF' },
    { value: 'docx', label: 'Word Document' },
    { value: 'html', label: 'HTML' },
  ];

  function insertMergeField(field: string) {
    content = content + `{{${field}}}`;
  }

  function handleSave() {
    dispatch('save', {
      ...template,
      name,
      category: category || null,
      description: description || null,
      content,
      output_format: outputFormat as 'pdf' | 'docx' | 'html',
      is_shared: isShared,
      conditional_blocks: conditionalBlocks.length > 0 ? conditionalBlocks : null,
      page_settings: {
        margin_top: marginTop,
        margin_right: marginRight,
        margin_bottom: marginBottom,
        margin_left: marginLeft,
        orientation: orientation as 'portrait' | 'landscape',
      },
      header_settings: headerContent ? { content: headerContent } : null,
      footer_settings: footerContent ? { content: footerContent } : null,
    });
  }

  function addConditionalBlock() {
    conditionalBlocks = [
      ...conditionalBlocks,
      {
        placeholder: `{{#condition_${conditionalBlocks.length + 1}}}`,
        condition: { field: '', operator: '=', value: '' },
        if_content: '',
        else_content: '',
      },
    ];
  }

  function removeConditionalBlock(index: number) {
    conditionalBlocks = conditionalBlocks.filter((_, i) => i !== index);
  }
</script>

<div class="space-y-6">
  <Card.Root>
    <Card.Header>
      <Card.Title>{template.id ? 'Edit Template' : 'Create Template'}</Card.Title>
      <Card.Description>
        Design your document template with merge fields and conditional content
      </Card.Description>
    </Card.Header>
    <Card.Content>
      <Tabs.Root value="content">
        <Tabs.List class="mb-4">
          <Tabs.Trigger value="content">Content</Tabs.Trigger>
          <Tabs.Trigger value="settings">Settings</Tabs.Trigger>
          <Tabs.Trigger value="header-footer">Header/Footer</Tabs.Trigger>
          <Tabs.Trigger value="conditions">Conditions</Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="content" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="name">Template Name</Label>
              <Input id="name" bind:value={name} placeholder="Enter template name" />
            </div>

            <div class="space-y-2">
              <Label for="category">Category</Label>
              <Select.Root
                selected={{ value: category, label: categories.find(c => c.value === category)?.label || 'Select category' }}
                onSelectedChange={(v) => category = v?.value || ''}
              >
                <Select.Trigger>
                  <Select.Value placeholder="Select category" />
                </Select.Trigger>
                <Select.Content>
                  {#each categories as cat}
                    <Select.Item value={cat.value}>{cat.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Input id="description" bind:value={description} placeholder="Template description" />
          </div>

          <div class="grid grid-cols-[1fr_300px] gap-4">
            <div class="space-y-2">
              <Label for="content">Template Content</Label>
              <Textarea
                id="content"
                bind:value={content}
                placeholder="Enter your template content with merge fields like {{contact.name}}"
                class="min-h-[400px] font-mono text-sm"
              />
            </div>

            <MergeFieldPicker {variables} on:insert={(e) => insertMergeField(e.detail)} />
          </div>
        </Tabs.Content>

        <Tabs.Content value="settings" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="output">Output Format</Label>
              <Select.Root
                selected={{ value: outputFormat, label: outputFormats.find(f => f.value === outputFormat)?.label || 'PDF' }}
                onSelectedChange={(v) => outputFormat = v?.value || 'pdf'}
              >
                <Select.Trigger>
                  <Select.Value placeholder="Select format" />
                </Select.Trigger>
                <Select.Content>
                  {#each outputFormats as format}
                    <Select.Item value={format.value}>{format.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>

            <div class="space-y-2">
              <Label for="orientation">Orientation</Label>
              <Select.Root
                selected={{ value: orientation, label: orientation === 'portrait' ? 'Portrait' : 'Landscape' }}
                onSelectedChange={(v) => orientation = v?.value || 'portrait'}
              >
                <Select.Trigger>
                  <Select.Value placeholder="Select orientation" />
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="portrait">Portrait</Select.Item>
                  <Select.Item value="landscape">Landscape</Select.Item>
                </Select.Content>
              </Select.Root>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Page Margins</Label>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1">
                <Label for="marginTop" class="text-xs text-muted-foreground">Top</Label>
                <Input id="marginTop" bind:value={marginTop} placeholder="20mm" />
              </div>
              <div class="space-y-1">
                <Label for="marginRight" class="text-xs text-muted-foreground">Right</Label>
                <Input id="marginRight" bind:value={marginRight} placeholder="20mm" />
              </div>
              <div class="space-y-1">
                <Label for="marginBottom" class="text-xs text-muted-foreground">Bottom</Label>
                <Input id="marginBottom" bind:value={marginBottom} placeholder="20mm" />
              </div>
              <div class="space-y-1">
                <Label for="marginLeft" class="text-xs text-muted-foreground">Left</Label>
                <Input id="marginLeft" bind:value={marginLeft} placeholder="20mm" />
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <input type="checkbox" id="shared" bind:checked={isShared} class="rounded" />
            <Label for="shared">Share with team</Label>
          </div>
        </Tabs.Content>

        <Tabs.Content value="header-footer" class="space-y-4">
          <div class="space-y-2">
            <Label for="header">Header Content</Label>
            <Textarea
              id="header"
              bind:value={headerContent}
              placeholder="HTML content for header (supports merge fields)"
              class="min-h-[150px] font-mono text-sm"
            />
          </div>

          <div class="space-y-2">
            <Label for="footer">Footer Content</Label>
            <Textarea
              id="footer"
              bind:value={footerContent}
              placeholder="HTML content for footer (supports merge fields)"
              class="min-h-[150px] font-mono text-sm"
            />
          </div>
        </Tabs.Content>

        <Tabs.Content value="conditions" class="space-y-4">
          <div class="flex justify-between items-center">
            <div>
              <h4 class="font-medium">Conditional Blocks</h4>
              <p class="text-sm text-muted-foreground">Show different content based on record data</p>
            </div>
            <Button variant="outline" on:click={addConditionalBlock}>Add Condition</Button>
          </div>

          {#each conditionalBlocks as block, index}
            <Card.Root class="p-4">
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <code class="text-sm bg-muted px-2 py-1 rounded">{block.placeholder}</code>
                  <Button variant="ghost" size="sm" on:click={() => removeConditionalBlock(index)}>
                    Remove
                  </Button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                  <Input
                    bind:value={block.condition.field}
                    placeholder="Field (e.g., contact.type)"
                  />
                  <Select.Root
                    selected={{ value: block.condition.operator, label: block.condition.operator }}
                    onSelectedChange={(v) => block.condition.operator = v?.value || '='}
                  >
                    <Select.Trigger>
                      <Select.Value placeholder="Operator" />
                    </Select.Trigger>
                    <Select.Content>
                      <Select.Item value="=">Equals</Select.Item>
                      <Select.Item value="!=">Not Equals</Select.Item>
                      <Select.Item value=">">Greater Than</Select.Item>
                      <Select.Item value="<">Less Than</Select.Item>
                      <Select.Item value="empty">Is Empty</Select.Item>
                      <Select.Item value="not_empty">Is Not Empty</Select.Item>
                    </Select.Content>
                  </Select.Root>
                  <Input
                    bind:value={block.condition.value}
                    placeholder="Value"
                  />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <Label class="text-xs">If True</Label>
                    <Textarea bind:value={block.if_content} placeholder="Content if condition is true" />
                  </div>
                  <div class="space-y-1">
                    <Label class="text-xs">If False</Label>
                    <Textarea bind:value={block.else_content} placeholder="Content if condition is false" />
                  </div>
                </div>
              </div>
            </Card.Root>
          {/each}

          {#if conditionalBlocks.length === 0}
            <p class="text-center text-muted-foreground py-8">
              No conditional blocks added yet
            </p>
          {/if}
        </Tabs.Content>
      </Tabs.Root>
    </Card.Content>
    <Card.Footer class="flex justify-between">
      <Button variant="outline" on:click={() => dispatch('cancel')}>Cancel</Button>
      <div class="flex gap-2">
        <Button variant="outline" on:click={() => dispatch('preview')}>Preview</Button>
        <Button on:click={handleSave} disabled={loading || !name || !content}>
          {loading ? 'Saving...' : 'Save Template'}
        </Button>
      </div>
    </Card.Footer>
  </Card.Root>
</div>
