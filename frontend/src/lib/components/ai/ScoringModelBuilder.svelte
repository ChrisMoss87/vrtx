<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { saveScoringModel, type ScoringModel, type ScoringFactor } from '$lib/api/ai';
  import { Loader2, Plus, Trash2, GripVertical } from 'lucide-svelte';

  interface Props {
    model?: Partial<ScoringModel> | null;
    modules?: Array<{ api_name: string; label: string }>;
    onSave?: (model: ScoringModel) => void;
    onCancel?: () => void;
  }

  let {
    model = null,
    modules = [],
    onSave = undefined,
    onCancel = undefined
  }: Props = $props();

  let name = $state(model?.name || '');
  let moduleApiName = $state(model?.module_api_name || '');
  let description = $state(model?.description || '');
  let isActive = $state(model?.is_active ?? true);
  let factors = $state<ScoringFactor[]>(model?.factors || []);

  let saving = $state(false);
  let error = $state<string | null>(null);

  const factorTypes = [
    { value: 'field_value', label: 'Field Value', description: 'Score based on field matching a value' },
    { value: 'field_filled', label: 'Field Filled', description: 'Score if field has any value' },
    { value: 'activity_count', label: 'Activity Count', description: 'Score based on number of activities' },
    { value: 'recency', label: 'Recency', description: 'Score based on days since last update' },
    { value: 'custom', label: 'Custom', description: 'Custom scoring logic' }
  ] as const;

  const operators = [
    { value: 'equals', label: 'Equals' },
    { value: 'not_equals', label: 'Not Equals' },
    { value: 'contains', label: 'Contains' },
    { value: 'greater_than', label: 'Greater Than' },
    { value: 'less_than', label: 'Less Than' },
    { value: 'in', label: 'In List' }
  ];

  function addFactor() {
    factors = [
      ...factors,
      {
        name: '',
        factor_type: 'field_value',
        field_name: '',
        operator: 'equals',
        value: '',
        points: 10,
        weight: 1.0
      }
    ];
  }

  function removeFactor(index: number) {
    factors = factors.filter((_, i) => i !== index);
  }

  function updateFactor(index: number, updates: Partial<ScoringFactor>) {
    factors = factors.map((f, i) => (i === index ? { ...f, ...updates } : f));
  }

  async function handleSave() {
    if (!name.trim() || !moduleApiName) {
      error = 'Name and module are required';
      return;
    }

    saving = true;
    error = null;

    try {
      const result = await saveScoringModel({
        id: model?.id,
        name,
        module_api_name: moduleApiName,
        description: description || undefined,
        is_active: isActive,
        factors
      });

      onSave?.(result.model);
    } catch (e) {
      error = e instanceof Error ? e.message : 'Failed to save model';
    } finally {
      saving = false;
    }
  }

  function handleModuleChange(value: string | undefined) {
    if (value) {
      moduleApiName = value;
    }
  }

  const selectedModuleLabel = $derived(modules.find(m => m.api_name === moduleApiName)?.label || moduleApiName || 'Select module');
</script>

<Card.Root>
  <Card.Header>
    <Card.Title>{model?.id ? 'Edit' : 'Create'} Scoring Model</Card.Title>
    <Card.Description>
      Define how records should be scored based on their data and activity
    </Card.Description>
  </Card.Header>
  <Card.Content class="space-y-6">
    {#if error}
      <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        {error}
      </div>
    {/if}

    <!-- Basic Info -->
    <div class="grid grid-cols-2 gap-4">
      <div class="space-y-2">
        <Label>Model Name</Label>
        <Input bind:value={name} placeholder="e.g., Lead Quality Score" />
      </div>

      <div class="space-y-2">
        <Label>Module</Label>
        <Select.Root type="single" value={moduleApiName} onValueChange={handleModuleChange}>
          <Select.Trigger>
            <span>{selectedModuleLabel}</span>
          </Select.Trigger>
          <Select.Content>
            {#each modules as mod}
              <Select.Item value={mod.api_name} label={mod.label}>{mod.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>
    </div>

    <div class="space-y-2">
      <Label>Description</Label>
      <Textarea bind:value={description} placeholder="Describe what this scoring model measures..." rows={2} />
    </div>

    <div class="flex items-center justify-between">
      <div>
        <Label>Active</Label>
        <p class="text-sm text-muted-foreground">Enable this model for scoring</p>
      </div>
      <Switch bind:checked={isActive} />
    </div>

    <!-- Factors -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <Label>Scoring Factors</Label>
        <Button variant="outline" size="sm" onclick={addFactor}>
          <Plus class="h-4 w-4 mr-1" />
          Add Factor
        </Button>
      </div>

      {#if factors.length === 0}
        <div class="text-center py-8 border-2 border-dashed rounded-lg">
          <p class="text-muted-foreground">No factors defined yet</p>
          <Button variant="outline" size="sm" onclick={addFactor} class="mt-2">
            <Plus class="h-4 w-4 mr-1" />
            Add First Factor
          </Button>
        </div>
      {:else}
        <div class="space-y-4">
          {#each factors as factor, index}
            <Card.Root class="bg-muted/50">
              <Card.Content class="pt-4">
                <div class="flex items-start gap-2">
                  <div class="flex-shrink-0 pt-2 cursor-move">
                    <GripVertical class="h-4 w-4 text-muted-foreground" />
                  </div>

                  <div class="flex-1 space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                      <div class="space-y-1">
                        <Label class="text-xs">Name</Label>
                        <Input
                          value={factor.name}
                          oninput={(e) => updateFactor(index, { name: e.currentTarget.value })}
                          placeholder="Factor name"
                          class="h-8"
                        />
                      </div>

                      <div class="space-y-1">
                        <Label class="text-xs">Type</Label>
                        <Select.Root
                          type="single"
                          value={factor.factor_type}
                          onValueChange={(v) => v && updateFactor(index, { factor_type: v as ScoringFactor['factor_type'] })}
                        >
                          <Select.Trigger class="h-8">
                            <span>{factorTypes.find(t => t.value === factor.factor_type)?.label || factor.factor_type}</span>
                          </Select.Trigger>
                          <Select.Content>
                            {#each factorTypes as type}
                              <Select.Item value={type.value} label={type.label}>{type.label}</Select.Item>
                            {/each}
                          </Select.Content>
                        </Select.Root>
                      </div>

                      <div class="space-y-1">
                        <Label class="text-xs">Points</Label>
                        <Input
                          type="number"
                          value={factor.points}
                          oninput={(e) => updateFactor(index, { points: parseInt(e.currentTarget.value) || 0 })}
                          min={-100}
                          max={100}
                          class="h-8"
                        />
                      </div>
                    </div>

                    {#if factor.factor_type === 'field_value' || factor.factor_type === 'field_filled'}
                      <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1">
                          <Label class="text-xs">Field Name</Label>
                          <Input
                            value={factor.field_name || ''}
                            oninput={(e) => updateFactor(index, { field_name: e.currentTarget.value })}
                            placeholder="data.field_name"
                            class="h-8"
                          />
                        </div>

                        {#if factor.factor_type === 'field_value'}
                          <div class="space-y-1">
                            <Label class="text-xs">Operator</Label>
                            <Select.Root
                              type="single"
                              value={factor.operator || 'equals'}
                              onValueChange={(v) => v && updateFactor(index, { operator: v })}
                            >
                              <Select.Trigger class="h-8">
                                <span>{operators.find(o => o.value === factor.operator)?.label || factor.operator || 'Equals'}</span>
                              </Select.Trigger>
                              <Select.Content>
                                {#each operators as op}
                                  <Select.Item value={op.value} label={op.label}>{op.label}</Select.Item>
                                {/each}
                              </Select.Content>
                            </Select.Root>
                          </div>

                          <div class="space-y-1">
                            <Label class="text-xs">Value</Label>
                            <Input
                              value={String(factor.value || '')}
                              oninput={(e) => updateFactor(index, { value: e.currentTarget.value })}
                              placeholder="Value to match"
                              class="h-8"
                            />
                          </div>
                        {/if}
                      </div>
                    {/if}

                    {#if factor.factor_type === 'activity_count'}
                      <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                          <Label class="text-xs">Minimum Count</Label>
                          <Input
                            type="number"
                            value={String(factor.value || 0)}
                            oninput={(e) => updateFactor(index, { value: parseInt(e.currentTarget.value) || 0 })}
                            min={0}
                            class="h-8"
                          />
                        </div>
                        <div class="space-y-1">
                          <Label class="text-xs">Weight</Label>
                          <Input
                            type="number"
                            value={factor.weight}
                            oninput={(e) => updateFactor(index, { weight: parseFloat(e.currentTarget.value) || 1 })}
                            min={0}
                            max={10}
                            step={0.1}
                            class="h-8"
                          />
                        </div>
                      </div>
                    {/if}

                    {#if factor.factor_type === 'recency'}
                      <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                          <Label class="text-xs">Days Threshold</Label>
                          <Input
                            type="number"
                            value={String(factor.value || 30)}
                            oninput={(e) => updateFactor(index, { value: parseInt(e.currentTarget.value) || 30 })}
                            min={1}
                            class="h-8"
                          />
                        </div>
                        <div class="space-y-1">
                          <Label class="text-xs">Field (optional)</Label>
                          <Input
                            value={factor.field_name || ''}
                            oninput={(e) => updateFactor(index, { field_name: e.currentTarget.value })}
                            placeholder="Leave empty for updated_at"
                            class="h-8"
                          />
                        </div>
                      </div>
                    {/if}
                  </div>

                  <Button
                    variant="ghost"
                    size="sm"
                    onclick={() => removeFactor(index)}
                    class="text-red-500 hover:text-red-600"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </Card.Content>
            </Card.Root>
          {/each}
        </div>
      {/if}
    </div>
  </Card.Content>
  <Card.Footer class="flex justify-end gap-2">
    {#if onCancel}
      <Button variant="outline" onclick={onCancel}>Cancel</Button>
    {/if}
    <Button onclick={handleSave} disabled={saving}>
      {#if saving}
        <Loader2 class="mr-2 h-4 w-4 animate-spin" />
      {/if}
      {model?.id ? 'Update' : 'Create'} Model
    </Button>
  </Card.Footer>
</Card.Root>
