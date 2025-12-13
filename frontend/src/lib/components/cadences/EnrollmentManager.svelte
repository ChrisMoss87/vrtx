<script lang="ts">
	import type { CadenceEnrollment, EnrollmentStatus } from '$lib/api/cadences';
	import {
		getEnrollments,
		unenrollRecord,
		pauseEnrollment,
		resumeEnrollment
	} from '$lib/api/cadences';
	import { Button } from '$lib/components/ui/button';
	import * as Table from '$lib/components/ui/table';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import {
		MoreHorizontal,
		Pause,
		Play,
		UserMinus,
		Users,
		RefreshCw
	} from 'lucide-svelte';

	interface Props {
		cadenceId: number;
	}

	let { cadenceId }: Props = $props();

	let loading = $state(true);
	let enrollments = $state<CadenceEnrollment[]>([]);
	let statusFilter = $state<EnrollmentStatus | ''>('');
	let currentPage = $state(1);
	let totalPages = $state(1);
	let total = $state(0);

	const statusColors: Record<EnrollmentStatus, string> = {
		active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
		paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
		completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
		replied: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
		bounced: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
		unsubscribed: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
		meeting_booked: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300',
		manually_removed: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'
	};

	const statusLabels: Record<EnrollmentStatus, string> = {
		active: 'Active',
		paused: 'Paused',
		completed: 'Completed',
		replied: 'Replied',
		bounced: 'Bounced',
		unsubscribed: 'Unsubscribed',
		meeting_booked: 'Meeting Booked',
		manually_removed: 'Removed'
	};

	async function loadEnrollments() {
		loading = true;
		try {
			const result = await getEnrollments(cadenceId, {
				status: statusFilter || undefined,
				page: currentPage,
				per_page: 20
			});
			enrollments = result.data;
			totalPages = result.meta.last_page;
			total = result.meta.total;
		} catch (error) {
			console.error('Failed to load enrollments:', error);
			toast.error('Failed to load enrollments');
		} finally {
			loading = false;
		}
	}

	function handleStatusChange(value: string | undefined) {
		statusFilter = (value as EnrollmentStatus) || '';
		currentPage = 1;
		loadEnrollments();
	}

	async function handlePause(enrollment: CadenceEnrollment) {
		try {
			await pauseEnrollment(cadenceId, enrollment.id);
			toast.success('Enrollment paused');
			loadEnrollments();
		} catch (error) {
			console.error('Failed to pause:', error);
			toast.error('Failed to pause enrollment');
		}
	}

	async function handleResume(enrollment: CadenceEnrollment) {
		try {
			await resumeEnrollment(cadenceId, enrollment.id);
			toast.success('Enrollment resumed');
			loadEnrollments();
		} catch (error) {
			console.error('Failed to resume:', error);
			toast.error('Failed to resume enrollment');
		}
	}

	async function handleUnenroll(enrollment: CadenceEnrollment) {
		try {
			await unenrollRecord(cadenceId, enrollment.id);
			toast.success('Record unenrolled');
			loadEnrollments();
		} catch (error) {
			console.error('Failed to unenroll:', error);
			toast.error('Failed to unenroll record');
		}
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleDateString();
	}

	function formatDateTime(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleString();
	}

	$effect(() => {
		loadEnrollments();
	});
</script>

<div class="space-y-4">
	<!-- Filters -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Select.Root type="single" value={statusFilter} onValueChange={handleStatusChange}>
				<Select.Trigger class="w-[160px]">
					<span>{statusFilter ? statusLabels[statusFilter] : 'All Statuses'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">All Statuses</Select.Item>
					{#each Object.entries(statusLabels) as [value, label]}
						<Select.Item {value}>{label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
		<Button variant="outline" size="sm" onclick={loadEnrollments}>
			<RefreshCw class="mr-2 h-4 w-4" />
			Refresh
		</Button>
	</div>

	<!-- Enrollments Table -->
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if enrollments.length === 0}
		<div class="rounded-lg border border-dashed p-8 text-center">
			<Users class="mx-auto h-12 w-12 text-muted-foreground" />
			<h3 class="mt-4 text-lg font-medium">No enrollments</h3>
			<p class="mt-1 text-sm text-muted-foreground">
				{statusFilter ? 'No enrollments match this filter' : 'No records have been enrolled in this cadence yet'}
			</p>
		</div>
	{:else}
		<Table.Root>
			<Table.Header>
				<Table.Row>
					<Table.Head>Record ID</Table.Head>
					<Table.Head>Status</Table.Head>
					<Table.Head>Current Step</Table.Head>
					<Table.Head>Enrolled</Table.Head>
					<Table.Head>Next Step</Table.Head>
					<Table.Head class="w-[50px]"></Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each enrollments as enrollment}
					<Table.Row>
						<Table.Cell class="font-medium">#{enrollment.record_id}</Table.Cell>
						<Table.Cell>
							<Badge class={statusColors[enrollment.status]}>
								{statusLabels[enrollment.status]}
							</Badge>
						</Table.Cell>
						<Table.Cell>
							{#if enrollment.current_step}
								Step {enrollment.current_step.step_order}
							{:else}
								-
							{/if}
						</Table.Cell>
						<Table.Cell>{formatDate(enrollment.enrolled_at)}</Table.Cell>
						<Table.Cell>
							{#if enrollment.next_step_at && enrollment.status === 'active'}
								{formatDateTime(enrollment.next_step_at)}
							{:else}
								-
							{/if}
						</Table.Cell>
						<Table.Cell>
							{#if enrollment.status === 'active' || enrollment.status === 'paused'}
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button variant="ghost" size="icon" {...props}>
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										{#if enrollment.status === 'active'}
											<DropdownMenu.Item onclick={() => handlePause(enrollment)}>
												<Pause class="mr-2 h-4 w-4" />
												Pause
											</DropdownMenu.Item>
										{:else if enrollment.status === 'paused'}
											<DropdownMenu.Item onclick={() => handleResume(enrollment)}>
												<Play class="mr-2 h-4 w-4" />
												Resume
											</DropdownMenu.Item>
										{/if}
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive"
											onclick={() => handleUnenroll(enrollment)}
										>
											<UserMinus class="mr-2 h-4 w-4" />
											Unenroll
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							{/if}
						</Table.Cell>
					</Table.Row>
				{/each}
			</Table.Body>
		</Table.Root>

		<!-- Pagination -->
		{#if totalPages > 1}
			<div class="flex items-center justify-between">
				<p class="text-sm text-muted-foreground">
					Showing {(currentPage - 1) * 20 + 1} to {Math.min(currentPage * 20, total)} of {total} enrollments
				</p>
				<div class="flex gap-2">
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === 1}
						onclick={() => {
							currentPage--;
							loadEnrollments();
						}}
					>
						Previous
					</Button>
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === totalPages}
						onclick={() => {
							currentPage++;
							loadEnrollments();
						}}
					>
						Next
					</Button>
				</div>
			</div>
		{/if}
	{/if}
</div>
