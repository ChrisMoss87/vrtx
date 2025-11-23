<script lang="ts">
	import { goto } from '$app/navigation';
	import { authApi } from '$lib/api/auth';
	import { authStore } from '$lib/stores/auth.svelte';

	async function handleLogout() {
		await authApi.logout();
		goto('/login');
	}
</script>

<svelte:head>
	<title>Dashboard - VRTX CRM</title>
</svelte:head>

<div class="min-h-screen ">

	<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
		<h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
		<div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
			<div class="rounded-lg bg-white p-6 shadow">
				<h3 class="text-lg font-medium text-gray-900">Welcome to VRTX CRM</h3>
				<p class="mt-2 text-sm text-gray-600">
					Your multi-tenant CRM platform is ready. Start by exploring the features.
				</p>
			</div>

			<div class="rounded-lg bg-white p-6 shadow">
				<h3 class="text-lg font-medium text-gray-900">Contacts</h3>
				<p class="mt-2 text-sm text-gray-600">Manage your contacts and companies.</p>
				<p class="mt-4 text-2xl font-bold text-blue-600">0</p>
			</div>

			<div class="rounded-lg bg-white p-6 shadow">
				<h3 class="text-lg font-medium text-gray-900">Deals</h3>
				<p class="mt-2 text-sm text-gray-600">Track your sales pipeline.</p>
				<p class="mt-4 text-2xl font-bold text-blue-600">0</p>
			</div>
		</div>

		<div class="mt-8 rounded-lg bg-blue-50 p-6">
			<h3 class="text-lg font-medium text-blue-900">Next Steps</h3>
			<ul class="mt-4 space-y-2 text-sm text-blue-800">
				<li>• Complete DDD implementation for Contact Management</li>
				<li>• Build sales pipeline interface</li>
				<li>• Add tenant registration flow</li>
				<li>• Integrate email communication</li>
			</ul>
		</div>
	</div>
</div>
