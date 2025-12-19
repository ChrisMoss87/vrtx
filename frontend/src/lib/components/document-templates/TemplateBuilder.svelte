<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Select from '$lib/components/ui/select';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import type { DocumentTemplate, MergeFieldVariable, ConditionalBlock } from '$lib/api/document-templates';
  import MergeFieldPicker from './MergeFieldPicker.svelte';

  interface Props {
    template?: Partial<DocumentTemplate>;
    variables?: Record<string, MergeFieldVariable[]>;
    loading?: boolean;
    onSave?: (template: Partial<DocumentTemplate>) => void;
    onPreview?: () => void;
    onCancel?: () => void;
  }

  let {
    template = {},
    variables = {},
    loading = false,
    onSave,
    onPreview,
    onCancel,
  }: Props = $props();

  let name = $state(template.name || '');
  let category = $state(template.category || '');
  let description = $state(template.description || '');
  let content = $state(template.content || '');
  let outputFormat = $state(template.output_format || 'pdf');
  let isShared = $state(template.is_shared || false);
  let conditionalBlocks = $state<ConditionalBlock[]>(template.conditional_blocks || []);

  // Page settings
  let marginTop = $state(template.page_settings?.margin_top || '20mm');
  let marginRight = $state(template.page_settings?.margin_right || '20mm');
  let marginBottom = $state(template.page_settings?.margin_bottom || '20mm');
  let marginLeft = $state(template.page_settings?.margin_left || '20mm');
  let orientation = $state(template.page_settings?.orientation || 'portrait');

  // Header/Footer
  let headerContent = $state(template.header_settings?.content || '');
  let footerContent = $state(template.footer_settings?.content || '');

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

  const operators = [
    { value: '=', label: 'Equals' },
    { value: '!=', label: 'Not Equals' },
    { value: '>', label: 'Greater Than' },
    { value: '<', label: 'Less Than' },
    { value: 'empty', label: 'Is Empty' },
    { value: 'not_empty', label: 'Is Not Empty' },
  ];

  function insertMergeField(field: string) {
    content = content + `{{${field}}}`;
  }

  function handleSave() {
    onSave?.({
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
    conditionalBlocks = conditionalBlocks.filter((_: ConditionalBlock, i: number) => i !== index);
  }

  function updateBlockOperator(index: number, value: string) {
    conditionalBlocks[index].condition.operator = value;
    conditionalBlocks = conditionalBlocks;
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
              <Select.Root type="single" bind:value={category}>
                <Select.Trigger>
                  {categories.find(c => c.value === category)?.label || 'Select category'}
                </Select.Trigger>
                <Select.Content>
                  {#each categories as cat}
                    <Select.Item value={cat.value} label={cat.label}>{cat.label}</Select.Item>
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
                placeholder="Enter your template content with merge fields like {'{'}contact.name{'}'}"
                class="min-h-[400px] font-mono text-sm"
              />
            </div>

            <MergeFieldPicker {variables} onInsert={(field) => insertMergeField(field)} />
          </div>
        </Tabs.Content>

        <Tabs.Content value="settings" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="output">Output Format</Label>
              <Select.Root type="single" bind:value={outputFormat}>
                <Select.Trigger>
                  {outputFormats.find(f => f.value === outputFormat)?.label || 'PDF'}
                </Select.Trigger>
                <Select.Content>
                  {#each outputFormats as format}
                    <Select.Item value={format.value} label={format.label}>{format.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>

            <div class="space-y-2">
              <Label for="orientation">Orientation</Label>
              <Select.Root type="single" bind:value={orientation}>
                <Select.Trigger>
                  {orientation === 'portrait' ? 'Portrait' : 'Landscape'}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="portrait" label="Portrait">Portrait</Select.Item>
                  <Select.Item value="landscape" label="Landscape">Landscape</Select.Item>
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
            <Button variant="outline" onclick={addConditionalBlock}>Add Condition</Button>
          </div>

          {#each conditionalBlocks as block, index}
            <Card.Root class="p-4">
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <code class="text-sm bg-muted px-2 py-1 rounded">{block.placeholder}</code>
                  <Button variant="ghost" size="sm" onclick={() => removeConditionalBlock(index)}>
                    Remove
                  </Button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                  <Input
                    bind:value={block.condition.field}
                    placeholder="Field (e.g., contact.type)"
                  />
                  <Select.Root type="single" value={block.condition.operator} onValueChange={(v) => updateBlockOperator(index, v)}>
                    <Select.Trigger>
                      {operators.find(o => o.value === block.condition.operator)?.label || 'Equals'}
                    </Select.Trigger>
                    <Select.Content>
                      {#each operators as op}
                        <Select.Item value={op.value} label={op.label}>{op.label}</Select.Item>
                      {/each}
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
      <Button variant="outline" onclick={() => onCancel?.()}>Cancel</Button>
      <div class="flex gap-2">
        <Button variant="outline" onclick={() => onPreview?.()}>Preview</Button>
        <Button onclick={handleSave} disabled={loading || !name || !content}>
          {loading ? 'Saving...' : 'Save Template'}
        </Button>
      </div>
    </Card.Footer>
  </Card.Root>
</div>
