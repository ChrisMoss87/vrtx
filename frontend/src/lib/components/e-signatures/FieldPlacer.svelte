<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import * as Select from '$lib/components/ui/select';

  interface SignerData {
    name: string;
    email: string;
    role?: string;
    order?: number;
  }

  interface Props {
    documentUrl?: string | null;
    signers?: SignerData[];
    fields?: Array<{
      signer_index: number;
      type: string;
      page: number;
      x: number;
      y: number;
      width: number;
      height: number;
      required: boolean;
      label?: string;
    }>;
  }

  let {
    documentUrl = null,
    signers = [],
    fields = $bindable([]),
  }: Props = $props();

  let selectedSigner = $state(0);
  let selectedFieldType = $state('signature');
  let currentPage = $state(1);
  let totalPages = $state(1);
  let dragging = $state(false);
  let dragStart = $state({ x: 0, y: 0 });

  const fieldTypes = [
    { value: 'signature', label: 'Signature', width: 200, height: 60 },
    { value: 'initials', label: 'Initials', width: 80, height: 40 },
    { value: 'date', label: 'Date Signed', width: 120, height: 30 },
    { value: 'text', label: 'Text Field', width: 150, height: 30 },
    { value: 'checkbox', label: 'Checkbox', width: 24, height: 24 },
    { value: 'name', label: 'Full Name', width: 180, height: 30 },
    { value: 'email', label: 'Email', width: 200, height: 30 },
    { value: 'company', label: 'Company', width: 180, height: 30 },
    { value: 'title', label: 'Title', width: 150, height: 30 },
  ];

  const signerColors = [
    'border-blue-500 bg-blue-500/10',
    'border-green-500 bg-green-500/10',
    'border-purple-500 bg-purple-500/10',
    'border-orange-500 bg-orange-500/10',
    'border-pink-500 bg-pink-500/10',
    'border-cyan-500 bg-cyan-500/10',
  ];

  const signerBgColors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-orange-500',
    'bg-pink-500',
    'bg-cyan-500',
  ];

  function getFieldType(type: string) {
    return fieldTypes.find(f => f.value === type);
  }

  function handleDocumentClick(event: MouseEvent) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const fieldType = getFieldType(selectedFieldType);

    if (fieldType) {
      fields = [
        ...fields,
        {
          signer_index: selectedSigner,
          type: selectedFieldType,
          page: currentPage,
          x: x - fieldType.width / 2,
          y: y - fieldType.height / 2,
          width: fieldType.width,
          height: fieldType.height,
          required: true,
        },
      ];
    }
  }

  function removeField(index: number) {
    fields = fields.filter((_: typeof fields[0], i: number) => i !== index);
  }

  function startDrag(event: MouseEvent, index: number) {
    event.stopPropagation();
    dragging = true;
    dragStart = { x: event.clientX - fields[index].x, y: event.clientY - fields[index].y };

    const handleMove = (e: MouseEvent) => {
      if (dragging) {
        fields[index].x = e.clientX - dragStart.x;
        fields[index].y = e.clientY - dragStart.y;
        fields = fields;
      }
    };

    const handleUp = () => {
      dragging = false;
      window.removeEventListener('mousemove', handleMove);
      window.removeEventListener('mouseup', handleUp);
    };

    window.addEventListener('mousemove', handleMove);
    window.addEventListener('mouseup', handleUp);
  }

  const pageFields = $derived(fields.filter(f => f.page === currentPage));

  function getSignerLabel(index: number): string {
    return signers[index]?.name || `Signer ${index + 1}`;
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div>
      <h4 class="font-medium">Place Signature Fields</h4>
      <p class="text-sm text-muted-foreground">Click on the document to add fields</p>
    </div>
  </div>

  <div class="flex gap-4">
    <!-- Toolbar -->
    <div class="w-64 space-y-4">
      <div class="space-y-2">
        <label class="text-sm font-medium">Assign to Signer</label>
        <Select.Root type="single" value={selectedSigner.toString()} onValueChange={(v) => selectedSigner = parseInt(v || '0')}>
          <Select.Trigger>
            {getSignerLabel(selectedSigner)}
          </Select.Trigger>
          <Select.Content>
            {#each signers as signer, index}
              <Select.Item value={index.toString()} label={signer.name || `Signer ${index + 1}`}>
                <span class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full {signerBgColors[index % signerBgColors.length]}"></span>
                  {signer.name || `Signer ${index + 1}`}
                </span>
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium">Field Type</label>
        <div class="grid grid-cols-2 gap-2">
          {#each fieldTypes as ft}
            <button
              type="button"
              class="px-3 py-2 text-xs text-left rounded border transition-colors {selectedFieldType === ft.value ? 'border-primary bg-primary/10' : 'border-muted hover:border-primary/50'}"
              onclick={() => selectedFieldType = ft.value}
            >
              {ft.label}
            </button>
          {/each}
        </div>
      </div>

      <div class="pt-4 border-t">
        <h5 class="text-sm font-medium mb-2">Placed Fields ({fields.length})</h5>
        <div class="space-y-1 max-h-48 overflow-y-auto">
          {#each fields as field, index}
            <div class="flex items-center justify-between text-xs p-2 rounded bg-muted">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {signerBgColors[field.signer_index % signerBgColors.length]}"></span>
                {getFieldType(field.type)?.label}
              </span>
              <button
                type="button"
                class="text-destructive hover:text-destructive/80"
                onclick={() => removeField(index)}
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </button>
            </div>
          {/each}
        </div>
      </div>
    </div>

    <!-- Document Preview -->
    <div class="flex-1">
      <div class="border rounded-lg overflow-hidden bg-gray-100">
        <!-- Page Navigation -->
        <div class="flex items-center justify-between p-2 bg-muted border-b">
          <Button variant="outline" size="sm" disabled={currentPage === 1} onclick={() => currentPage--}>
            Previous
          </Button>
          <span class="text-sm">Page {currentPage} of {totalPages}</span>
          <Button variant="outline" size="sm" disabled={currentPage === totalPages} onclick={() => currentPage++}>
            Next
          </Button>
        </div>

        <!-- Document Area -->
        <div
          class="relative bg-white mx-auto my-4 shadow-lg cursor-crosshair"
          style="width: 612px; height: 792px;"
          onclick={handleDocumentClick}
          role="button"
          tabindex="0"
          onkeypress={(e) => e.key === 'Enter' && handleDocumentClick(e as unknown as MouseEvent)}
        >
          {#if documentUrl}
            <img src={documentUrl} alt="Document page {currentPage}" class="w-full h-full object-contain" />
          {:else}
            <div class="absolute inset-0 flex items-center justify-center text-muted-foreground">
              <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                  <polyline points="14 2 14 8 20 8" />
                </svg>
                <p>Document preview</p>
              </div>
            </div>
          {/if}

          <!-- Placed Fields -->
          {#each pageFields as field, _index}
            <div
              class="absolute border-2 rounded cursor-move {signerColors[field.signer_index % signerColors.length]}"
              style="left: {field.x}px; top: {field.y}px; width: {field.width}px; height: {field.height}px;"
              onmousedown={(e) => startDrag(e, fields.indexOf(field))}
              role="button"
              tabindex="0"
            >
              <div class="absolute -top-5 left-0 text-xs font-medium whitespace-nowrap">
                {getFieldType(field.type)?.label}
              </div>
              <button
                type="button"
                class="absolute -top-2 -right-2 w-4 h-4 bg-destructive text-destructive-foreground rounded-full flex items-center justify-center text-xs"
                onclick={(e) => { e.stopPropagation(); removeField(fields.indexOf(field)); }}
              >
                ×
              </button>
            </div>
          {/each}
        </div>
      </div>
    </div>
  </div>
</div>
