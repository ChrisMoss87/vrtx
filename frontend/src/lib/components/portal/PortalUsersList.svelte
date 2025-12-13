<script lang="ts">
  import { onMount } from 'svelte';
  import { portalAdminApi, type PortalUser } from '$lib/api/portal';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Table from '$lib/components/ui/table';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
  import { Search, MoreHorizontal, UserCheck, UserX, Mail, Shield, Eye, Users } from 'lucide-svelte';

  let users = $state<PortalUser[]>([]);
  let loading = $state(true);
  let searchQuery = $state('');
  let currentPage = $state(1);
  let totalPages = $state(1);

  async function loadUsers() {
    loading = true;
    try {
      const response = await portalAdminApi.getUsers({
        search: searchQuery || undefined,
        page: currentPage,
      });
      users = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load portal users:', error);
    } finally {
      loading = false;
    }
  }

  async function toggleUserStatus(user: PortalUser) {
    try {
      if (user.is_active) {
        await portalAdminApi.deactivateUser(user.id);
      } else {
        await portalAdminApi.activateUser(user.id);
      }
      await loadUsers();
    } catch (error) {
      console.error('Failed to update user status:', error);
    }
  }

  function getRoleBadgeVariant(role: string): 'default' | 'secondary' | 'outline' {
    switch (role) {
      case 'admin':
        return 'default';
      case 'member':
        return 'secondary';
      default:
        return 'outline';
    }
  }

  function formatDate(date: string | undefined): string {
    if (!date) return 'Never';
    return new Date(date).toLocaleDateString();
  }

  onMount(() => {
    loadUsers();
  });

  $effect(() => {
    if (searchQuery !== undefined) {
      const timeout = setTimeout(() => {
        currentPage = 1;
        loadUsers();
      }, 300);
      return () => clearTimeout(timeout);
    }
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <div>
        <Card.Title class="flex items-center gap-2">
          <Users class="h-5 w-5" />
          Portal Users
        </Card.Title>
        <Card.Description>Manage customer portal access</Card.Description>
      </div>
    </div>
  </Card.Header>
  <Card.Content>
    <div class="mb-4">
      <div class="relative">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Search users..."
          bind:value={searchQuery}
          class="pl-9"
        />
      </div>
    </div>

    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if users.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        No portal users found
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>User</Table.Head>
            <Table.Head>Role</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head>Last Login</Table.Head>
            <Table.Head>Created</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each users as user}
            <Table.Row>
              <Table.Cell>
                <div>
                  <div class="font-medium">{user.name}</div>
                  <div class="text-sm text-muted-foreground">{user.email}</div>
                </div>
              </Table.Cell>
              <Table.Cell>
                <Badge variant={getRoleBadgeVariant(user.role)}>
                  {user.role}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                {#if user.is_active}
                  <Badge variant="default" class="bg-green-500">Active</Badge>
                {:else}
                  <Badge variant="secondary">Inactive</Badge>
                {/if}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {formatDate(user.last_login_at)}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {formatDate(user.created_at)}
              </Table.Cell>
              <Table.Cell>
                <DropdownMenu.Root>
                  <DropdownMenu.Trigger>
                    {#snippet child({ props })}
                      <Button variant="ghost" size="icon" {...props}>
                        <MoreHorizontal class="h-4 w-4" />
                      </Button>
                    {/snippet}
                  </DropdownMenu.Trigger>
                  <DropdownMenu.Content align="end">
                    <DropdownMenu.Item>
                      <Eye class="mr-2 h-4 w-4" />
                      View Details
                    </DropdownMenu.Item>
                    <DropdownMenu.Item>
                      <Shield class="mr-2 h-4 w-4" />
                      Change Role
                    </DropdownMenu.Item>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Item onclick={() => toggleUserStatus(user)}>
                      {#if user.is_active}
                        <UserX class="mr-2 h-4 w-4" />
                        Deactivate
                      {:else}
                        <UserCheck class="mr-2 h-4 w-4" />
                        Activate
                      {/if}
                    </DropdownMenu.Item>
                  </DropdownMenu.Content>
                </DropdownMenu.Root>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>

      {#if totalPages > 1}
        <div class="mt-4 flex items-center justify-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage === 1}
            onclick={() => { currentPage--; loadUsers(); }}
          >
            Previous
          </Button>
          <span class="text-sm text-muted-foreground">
            Page {currentPage} of {totalPages}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage === totalPages}
            onclick={() => { currentPage++; loadUsers(); }}
          >
            Next
          </Button>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
