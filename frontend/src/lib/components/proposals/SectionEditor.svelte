<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Collapsible from '$lib/components/ui/collapsible';
  import type { ProposalSection } from '$lib/api/proposals';

  export let section: ProposalSection;
  export let index: number;
  export let total: number;

  const dispatch = createEventDispatcher<{
    remove: void;
    moveUp: void;
    moveDown: void;
  }>();

  let isOpen = true;
</script>

<Card.Root class="border-l-4 {section.is_visible ? 'border-l-primary' : 'border-l-muted'}">
  <Collapsible.Root bind:open={isOpen}>
    <div class="flex items-center justify-between p-4 border-b">
      <div class="flex items-center gap-3">
        <div class="flex flex-col gap-0.5">
          <button
            type="button"
            class="p-0.5 hover:bg-muted rounded disabled:opacity-30"
            disabled={index === 0}
            on:click={() => dispatch('moveUp')}
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="18 15 12 9 6 15" />
            </svg>
          </button>
          <button
            type="button"
            class="p-0.5 hover:bg-muted rounded disabled:opacity-30"
            disabled={index === total - 1}
            on:click={() => dispatch('moveDown')}
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
        </div>
        <Collapsible.Trigger class="flex items-center gap-2 hover:text-primary transition-colors">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4 transition-transform {isOpen ? 'rotate-90' : ''}"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <polyline points="9 18 15 12 9 6" />
          </svg>
          <span class="font-medium">{section.title || 'Untitled Section'}</span>
        </Collapsible.Trigger>
      </div>
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" bind:checked={section.is_visible} class="rounded" />
          Visible
        </label>
        <Button variant="ghost" size="sm" on:click={() => dispatch('remove')}>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          </svg>
        </Button>
      </div>
    </div>
    <Collapsible.Content>
      <div class="p-4 space-y-4">
        <div class="space-y-2">
          <label class="text-sm font-medium">Section Title</label>
          <Input bind:value={section.title} placeholder="Enter section title" />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Content</label>
          <Textarea
            bind:value={section.content}
            placeholder="Write your section content here... You can use markdown for formatting."
            class="min-h-[200px] font-mono text-sm"
          />
        </div>
        {#if section.media_urls && section.media_urls.length > 0}
          <div class="space-y-2">
            <label class="text-sm font-medium">Attached Media</label>
            <div class="flex gap-2 flex-wrap">
              {#each section.media_urls as url}
                <div class="relative group">
                  <img src={url} alt="Media" class="h-20 w-20 object-cover rounded border" />
                  <button
                    type="button"
                    class="absolute -top-2 -right-2 w-5 h-5 bg-destructive text-destructive-foreground rounded-full hidden group-hover:flex items-center justify-center text-xs"
                    on:click={() => {
                      section.media_urls = section.media_urls?.filter(u => u !== url);
                    }}
                  >
                    ×
                  </button>
                </div>
              {/each}
            </div>
          </div>
        {/if}
        <div class="flex gap-2">
          <Button variant="outline" size="sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
              <circle cx="8.5" cy="8.5" r="1.5" />
              <polyline points="21 15 16 10 5 21" />
            </svg>
            Add Image
          </Button>
          <Button variant="outline" size="sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="23 7 16 12 23 17 23 7" />
              <rect x="1" y="5" width="15" height="14" rx="2" ry="2" />
            </svg>
            Add Video
          </Button>
        </div>
      </div>
    </Collapsible.Content>
  </Collapsible.Root>
</Card.Root>
