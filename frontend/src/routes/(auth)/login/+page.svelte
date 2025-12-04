<script lang="ts">
	import { goto } from '$app/navigation';
	import { authApi } from '$lib/api/auth';
	import { authStore } from '$lib/stores/auth.svelte';
	import GalleryVerticalEndIcon from '@lucide/svelte/icons/gallery-vertical-end';
	import {
		Root as AlertRoot,
		Title as AlertTitle,
		Description as AlertDescription
	} from '$lib/components/ui/alert/index.ts';
	import Placeholder from '$lib/assets/placeholder.jpg';
	import { FieldGroup, Field, FieldLabel } from '$lib/components/ui/field/index.js';
	import { Input } from '$lib/components/ui/input/index.js';
	import { Button } from '$lib/components/ui/button/index.js';
	import TextField from '$lib/components/form/TextField.svelte';
	import { AlertCircleIcon } from 'lucide-svelte';

	let email = $state('');
	let password = $state('');
	let error = $state('');
	let loading = $state(false);

	async function handleSubmit(e: Event) {
		e.preventDefault();
		error = '';
		loading = true;

		try {
			await authApi.login({ email, password });
			goto('/dashboard');
		} catch (err: any) {
			error = err.message || 'Login failed. Please check your credentials.';
		} finally {
			loading = false;
		}
	}
</script>

<svelte:head>
	<title>Login - VRTX CRM</title>
</svelte:head>

<div class="grid min-h-svh lg:grid-cols-2">
	<div class="flex flex-col gap-4 p-6 md:p-10">
		<div class="flex flex-1 items-center justify-center">
			<div class="w-full max-w-xs">
				<form class="flex flex-col gap-6" onsubmit={handleSubmit}>
					{#if error}
						<AlertRoot variant="destructive">
							<AlertCircleIcon />
							<AlertTitle>{error}</AlertTitle>
							<AlertDescription>
								<p>Please verify your email and password and try again.</p>
							</AlertDescription>
						</AlertRoot>
					{/if}
					<FieldGroup>
						<div class="flex flex-col items-center gap-1 text-center">
							<h1 class="text-2xl font-bold">Login to your account</h1>
							<p class="text-sm text-balance text-muted-foreground">
								Enter your email below to login to your account
							</p>
						</div>

						<TextField
							label="Email"
							name="email"
							bind:value={email}
							type="email"
							placeholder="example@vrtx.com"
							required
						/>
						<TextField
							label="Password"
							name="password"
							bind:value={password}
							type="password"
							placeholder="*******"
							required
						/>

						<Field>
							<Button type="submit">Login</Button>
						</Field>
					</FieldGroup>
				</form>
			</div>
		</div>
		<!--                TODO ADD FORGOTTEN PASSWORD   -->
		<a href="##" class="ml-auto text-sm underline-offset-4 hover:underline">
			Forgot your password?
		</a>
	</div>

	<div class="relative hidden bg-muted lg:block">
		<img
			src={Placeholder}
			alt="placeholder"
			class="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
		/>
	</div>
</div>
