<script lang="ts">
  import { CategoryManager } from '$lib/components/support';
  import * as Tabs from '$lib/components/ui/tabs';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Switch } from '$lib/components/ui/switch';
  import { Settings, Tag, Users, FileText } from 'lucide-svelte';

  let activeTab = $state('categories');
</script>

<svelte:head>
  <title>Support Settings | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-2xl font-semibold">Support Settings</h1>
    <p class="text-muted-foreground">Configure support ticketing and knowledge base</p>
  </div>

  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="categories">
        <Tag class="mr-2 h-4 w-4" />
        Categories
      </Tabs.Trigger>
      <Tabs.Trigger value="teams">
        <Users class="mr-2 h-4 w-4" />
        Teams
      </Tabs.Trigger>
      <Tabs.Trigger value="kb">
        <FileText class="mr-2 h-4 w-4" />
        Knowledge Base
      </Tabs.Trigger>
      <Tabs.Trigger value="settings">
        <Settings class="mr-2 h-4 w-4" />
        Settings
      </Tabs.Trigger>
    </Tabs.List>

    <div class="mt-6">
      <Tabs.Content value="categories">
        <CategoryManager />
      </Tabs.Content>

      <Tabs.Content value="teams">
        <Card.Root>
          <Card.Header>
            <Card.Title>Support Teams</Card.Title>
            <Card.Description>
              Organize support agents into teams for better ticket routing
            </Card.Description>
          </Card.Header>
          <Card.Content>
            <div class="text-center py-8 text-muted-foreground">
              Team management coming soon
            </div>
          </Card.Content>
        </Card.Root>
      </Tabs.Content>

      <Tabs.Content value="kb">
        <Card.Root>
          <Card.Header>
            <Card.Title>Knowledge Base Management</Card.Title>
            <Card.Description>
              Create and manage help articles and categories
            </Card.Description>
          </Card.Header>
          <Card.Content>
            <div class="text-center py-8 text-muted-foreground">
              <p class="mb-4">Manage your knowledge base articles and categories</p>
              <Button href="/support/knowledge-base">
                View Knowledge Base
              </Button>
            </div>
          </Card.Content>
        </Card.Root>
      </Tabs.Content>

      <Tabs.Content value="settings">
        <div class="space-y-6">
          <Card.Root>
            <Card.Header>
              <Card.Title>General Settings</Card.Title>
            </Card.Header>
            <Card.Content class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <Label>Auto-assign tickets</Label>
                  <p class="text-sm text-muted-foreground">
                    Automatically assign new tickets to available agents
                  </p>
                </div>
                <Switch />
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <Label>Email notifications</Label>
                  <p class="text-sm text-muted-foreground">
                    Send email notifications for ticket updates
                  </p>
                </div>
                <Switch checked />
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <Label>Customer satisfaction surveys</Label>
                  <p class="text-sm text-muted-foreground">
                    Send CSAT surveys after ticket resolution
                  </p>
                </div>
                <Switch checked />
              </div>
            </Card.Content>
          </Card.Root>

          <Card.Root>
            <Card.Header>
              <Card.Title>SLA Defaults</Card.Title>
            </Card.Header>
            <Card.Content class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label>Default Response Time (hours)</Label>
                  <Input type="number" value="4" min="1" />
                </div>
                <div class="space-y-2">
                  <Label>Default Resolution Time (hours)</Label>
                  <Input type="number" value="24" min="1" />
                </div>
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <Label>Business hours only</Label>
                  <p class="text-sm text-muted-foreground">
                    Calculate SLA based on business hours only
                  </p>
                </div>
                <Switch checked />
              </div>
            </Card.Content>
          </Card.Root>
        </div>
      </Tabs.Content>
    </div>
  </Tabs.Root>
</div>
