<script lang="ts">
  import { onMount, createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Input } from '$lib/components/ui/input';

  export let width = 400;
  export let height = 150;

  const dispatch = createEventDispatcher<{
    sign: string;
    cancel: void;
  }>();

  let canvas: HTMLCanvasElement;
  let ctx: CanvasRenderingContext2D | null = null;
  let isDrawing = $state(false);
  let lastX = $state(0);
  let lastY = $state(0);
  let signatureMode = $state<'draw' | 'type' | 'upload'>('draw');
  let typedName = $state('');
  let uploadedImage = $state('');

  const fonts = [
    { name: 'Script', font: "'Dancing Script', cursive" },
    { name: 'Formal', font: "'Great Vibes', cursive" },
    { name: 'Simple', font: "'Caveat', cursive" },
    { name: 'Classic', font: "'Pacifico', cursive" },
  ];
  let selectedFont = $state(fonts[0]);

  onMount(() => {
    ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.strokeStyle = '#000';
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
    }
  });

  function startDrawing(e: MouseEvent | TouchEvent) {
    isDrawing = true;
    const pos = getPosition(e);
    lastX = pos.x;
    lastY = pos.y;
  }

  function draw(e: MouseEvent | TouchEvent) {
    if (!isDrawing || !ctx) return;
    e.preventDefault();

    const pos = getPosition(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    lastX = pos.x;
    lastY = pos.y;
  }

  function stopDrawing() {
    isDrawing = false;
  }

  function getPosition(e: MouseEvent | TouchEvent): { x: number; y: number } {
    const rect = canvas.getBoundingClientRect();
    if ('touches' in e) {
      return {
        x: e.touches[0].clientX - rect.left,
        y: e.touches[0].clientY - rect.top,
      };
    }
    return {
      x: e.clientX - rect.left,
      y: e.clientY - rect.top,
    };
  }

  function clearCanvas() {
    if (ctx) {
      ctx.clearRect(0, 0, width, height);
    }
    typedName = '';
    uploadedImage = '';
  }

  function handleSign() {
    let signatureData = '';

    if (signatureMode === 'draw') {
      signatureData = canvas.toDataURL('image/png');
    } else if (signatureMode === 'type' && typedName) {
      // Create a canvas with the typed signature
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = width;
      tempCanvas.height = height;
      const tempCtx = tempCanvas.getContext('2d');
      if (tempCtx) {
        tempCtx.fillStyle = '#fff';
        tempCtx.fillRect(0, 0, width, height);
        tempCtx.font = `48px ${selectedFont.font}`;
        tempCtx.fillStyle = '#000';
        tempCtx.textAlign = 'center';
        tempCtx.textBaseline = 'middle';
        tempCtx.fillText(typedName, width / 2, height / 2);
        signatureData = tempCanvas.toDataURL('image/png');
      }
    } else if (signatureMode === 'upload' && uploadedImage) {
      signatureData = uploadedImage;
    }

    if (signatureData) {
      dispatch('sign', signatureData);
    }
  }

  function handleFileUpload(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        uploadedImage = e.target?.result as string;
      };
      reader.readAsDataURL(file);
    }
  }

  function isCanvasEmpty(): boolean {
    if (!ctx) return true;
    const imageData = ctx.getImageData(0, 0, width, height);
    return !imageData.data.some((channel, index) => {
      // Check alpha channel (every 4th value starting from index 3)
      return index % 4 === 3 && channel !== 0;
    });
  }

  const hasSignature = $derived(
    (signatureMode === 'draw' && ctx && !isCanvasEmpty()) ||
    (signatureMode === 'type' && typedName.length > 0) ||
    (signatureMode === 'upload' && uploadedImage)
  );
</script>

<svelte:head>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script&family=Great+Vibes&family=Caveat&family=Pacifico&display=swap" rel="stylesheet">
</svelte:head>

<div class="space-y-4">
  <Tabs.Root value={signatureMode} onValueChange={(v) => signatureMode = v as 'draw' | 'type' | 'upload'}>
    <Tabs.List class="w-full">
      <Tabs.Trigger value="draw" class="flex-1">Draw</Tabs.Trigger>
      <Tabs.Trigger value="type" class="flex-1">Type</Tabs.Trigger>
      <Tabs.Trigger value="upload" class="flex-1">Upload</Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="draw" class="mt-4">
      <div class="border rounded-lg overflow-hidden bg-white">
        <canvas
          bind:this={canvas}
          {width}
          {height}
          class="touch-none cursor-crosshair"
          onmousedown={startDrawing}
          onmousemove={draw}
          onmouseup={stopDrawing}
          onmouseleave={stopDrawing}
          ontouchstart={startDrawing}
          ontouchmove={draw}
          ontouchend={stopDrawing}
        ></canvas>
      </div>
      <p class="text-xs text-muted-foreground mt-2 text-center">
        Draw your signature above
      </p>
    </Tabs.Content>

    <Tabs.Content value="type" class="mt-4 space-y-4">
      <Input
        bind:value={typedName}
        placeholder="Type your full name"
        class="text-lg"
      />

      <div class="space-y-2">
        <label class="text-sm font-medium">Select Style</label>
        <div class="grid grid-cols-2 gap-2">
          {#each fonts as font}
            <button
              type="button"
              class="p-3 border rounded-lg text-center transition-colors {selectedFont.name === font.name ? 'border-primary bg-primary/5' : 'hover:border-primary/50'}"
              style="font-family: {font.font}; font-size: 24px;"
              onclick={() => selectedFont = font}
            >
              {typedName || 'Your Name'}
            </button>
          {/each}
        </div>
      </div>

      {#if typedName}
        <div class="border rounded-lg p-4 bg-white">
          <p class="text-center" style="font-family: {selectedFont.font}; font-size: 48px;">
            {typedName}
          </p>
        </div>
      {/if}
    </Tabs.Content>

    <Tabs.Content value="upload" class="mt-4">
      {#if uploadedImage}
        <div class="border rounded-lg p-4 bg-white">
          <img src={uploadedImage} alt="Uploaded signature" class="max-h-32 mx-auto" />
        </div>
        <Button variant="outline" class="w-full mt-2" onclick={() => uploadedImage = ''}>
          Remove Image
        </Button>
      {:else}
        <label
          class="flex flex-col items-center justify-center h-32 border-2 border-dashed rounded-lg cursor-pointer hover:border-primary transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-muted-foreground mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="17 8 12 3 7 8" />
            <line x1="12" y1="3" x2="12" y2="15" />
          </svg>
          <span class="text-sm text-muted-foreground">Upload signature image</span>
          <input type="file" accept="image/*" class="hidden" onchange={handleFileUpload} />
        </label>
      {/if}
    </Tabs.Content>
  </Tabs.Root>

  <div class="flex justify-between">
    <Button variant="outline" onclick={clearCanvas}>Clear</Button>
    <div class="flex gap-2">
      <Button variant="outline" onclick={() => dispatch('cancel')}>Cancel</Button>
      <Button onclick={handleSign} disabled={!hasSignature}>
        Apply Signature
      </Button>
    </div>
  </div>
</div>
