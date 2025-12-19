<script lang="ts">
  import { PortalUsersList, InviteUserModal, InvitationsList, AnnouncementManager, PortalAnalytics } from '$lib/components/portal';
  import { Button } from '$lib/components/ui/button';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Users, Mail, Megaphone, BarChart3, UserPlus, Globe } from 'lucide-svelte';

  let activeTab = $state('users');
  let showInviteModal = $state(false);
  let refreshKey = $state(0);

  function handleInvited() {
    refreshKey++;
  }
</script>

<svelte:head>
  <title>Customer Portal | Admin</title>
</svelte:head>

<div class="container py-6 space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold flex items-center gap-2">
        <Globe class="h-6 w-6" />
        Customer Portal
      </h1>
      <p class="text-muted-foreground">
        Manage customer portal access, users, and announcements
      </p>
    </div>
    <Button onclick={() => (showInviteModal = true)}>
      <UserPlus class="mr-2 h-4 w-4" />
      Invite User
    </Button>
  </div>

  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="users">
        <Users class="mr-2 h-4 w-4" />
        Users
      </Tabs.Trigger>
      <Tabs.Trigger value="invitations">
        <Mail class="mr-2 h-4 w-4" />
        Invitations
      </Tabs.Trigger>
      <Tabs.Trigger value="announcements">
        <Megaphone class="mr-2 h-4 w-4" />
        Announcements
      </Tabs.Trigger>
      <Tabs.Trigger value="analytics">
        <BarChart3 class="mr-2 h-4 w-4" />
        Analytics
      </Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="users" class="mt-6">
      {#key refreshKey}
        <PortalUsersList />
      {/key}
    </Tabs.Content>

    <Tabs.Content value="invitations" class="mt-6">
      {#key refreshKey}
        <InvitationsList />
      {/key}
    </Tabs.Content>

    <Tabs.Content value="announcements" class="mt-6">
      <AnnouncementManager />
    </Tabs.Content>

    <Tabs.Content value="analytics" class="mt-6">
      <PortalAnalytics />
    </Tabs.Content>
  </Tabs.Root>
</div>

<InviteUserModal
  bind:open={showInviteModal}
  onClose={() => (showInviteModal = false)}
  onInvited={handleInvited}
/>
