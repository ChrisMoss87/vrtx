<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Progress } from '$lib/components/ui/progress';
	import {
		Upload,
		ArrowLeft,
		ArrowRight,
		Check,
		X,
		FileSpreadsheet,
		Columns,
		Settings,
		CheckCircle,
		Play,
		AlertTriangle
	} from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { Import, ImportUploadResponse, ImportOptions } from '$lib/api/imports';
	import {
		uploadImportFile,
		configureImport,
		validateImport,
		executeImport
	} from '$lib/api/imports';
	import { toast } from 'svelte-sonner';
	import FileUploadStep from './FileUploadStep.svelte';
	import ColumnMappingStep from './ColumnMappingStep.svelte';
	import ImportOptionsStep from './ImportOptionsStep.svelte';
	import ValidationStep from './ValidationStep.svelte';
	import ImportProgressStep from './ImportProgressStep.svelte';

	interface Props {
		moduleApiName: string;
		moduleName: string;
		moduleFields: Field[];
		onComplete?: (importData: Import) => void;
		onCancel?: () => void;
	}

	let {
		moduleApiName,
		moduleName,
		moduleFields,
		onComplete,
		onCancel
	}: Props = $props();

	const dispatch = createEventDispatcher();

	// Wizard state
	let currentStep = $state(0);
	let isLoading = $state(false);

	// Data from each step
	let uploadedFile = $state<File | null>(null);
	let uploadResponse = $state<ImportUploadResponse | null>(null);
	let columnMapping = $state<Record<string, string | null>>({});
	let importOptions = $state<ImportOptions>({
		duplicate_handling: 'skip',
		skip_empty_rows: true
	});
	let importData = $state<Import | null>(null);

	const steps = [
		{ id: 'upload', label: 'Upload File', icon: Upload },
		{ id: 'mapping', label: 'Map Columns', icon: Columns },
		{ id: 'options', label: 'Options', icon: Settings },
		{ id: 'validate', label: 'Validate', icon: CheckCircle },
		{ id: 'import', label: 'Import', icon: Play }
	];

	function canProceed(): boolean {
		switch (currentStep) {
			case 0: // Upload
				return uploadResponse !== null;
			case 1: // Mapping
				// At least one field should be mapped
				return Object.values(columnMapping).some((v) => v !== null);
			case 2: // Options
				return true;
			case 3: // Validate
				return importData?.status === 'validated';
			case 4: // Import
				return importData?.status === 'completed';
			default:
				return false;
		}
	}

	async function handleFileUpload(file: File) {
		isLoading = true;
		try {
			uploadedFile = file;
			uploadResponse = await uploadImportFile(moduleApiName, file);
			importData = uploadResponse.import;
			columnMapping = uploadResponse.suggested_mapping || {};
			toast.success('File uploaded successfully');
		} catch (error) {
			console.error('Upload failed:', error);
			toast.error('Failed to upload file');
			uploadedFile = null;
		} finally {
			isLoading = false;
		}
	}

	async function handleConfigureAndValidate() {
		if (!importData) return;

		isLoading = true;
		try {
			// First configure the import
			await configureImport(moduleApiName, importData.id, {
				column_mapping: columnMapping,
				import_options: importOptions
			});

			// Then validate
			const result = await validateImport(moduleApiName, importData.id);
			importData = result.import;

			// Poll for validation completion
			await pollImportStatus('validated');

			toast.success('Validation complete');
		} catch (error) {
			console.error('Configuration/validation failed:', error);
			toast.error('Failed to validate import');
		} finally {
			isLoading = false;
		}
	}

	async function handleExecuteImport() {
		if (!importData) return;

		isLoading = true;
		try {
			const result = await executeImport(moduleApiName, importData.id);
			importData = result.import;

			// Poll for import completion
			await pollImportStatus('completed');

			toast.success('Import completed successfully');
			onComplete?.(importData!);
		} catch (error) {
			console.error('Import failed:', error);
			toast.error('Import failed');
		} finally {
			isLoading = false;
		}
	}

	async function pollImportStatus(targetStatus: string, maxAttempts = 60) {
		const { getImport } = await import('$lib/api/imports');

		for (let i = 0; i < maxAttempts; i++) {
			const response = await getImport(moduleApiName, importData!.id);
			importData = response.import;

			if (importData.status === targetStatus) {
				return;
			}

			if (importData.status === 'failed' || importData.status === 'cancelled') {
				throw new Error(importData.error_message || 'Import failed');
			}

			// Wait 1 second before next poll
			await new Promise((resolve) => setTimeout(resolve, 1000));
		}

		throw new Error('Timeout waiting for import to complete');
	}

	function goToStep(step: number) {
		if (step < currentStep) {
			currentStep = step;
		} else if (step === currentStep + 1 && canProceed()) {
			currentStep = step;
		}
	}

	async function handleNext() {
		if (!canProceed()) return;

		if (currentStep === 2) {
			// After options, configure and validate
			await handleConfigureAndValidate();
			if (importData?.status === 'validated') {
				currentStep++;
			}
		} else if (currentStep === 3) {
			// After validation, execute import
			await handleExecuteImport();
			currentStep++;
		} else {
			currentStep++;
		}
	}

	function handleBack() {
		if (currentStep > 0) {
			currentStep--;
		}
	}

	function handleCancel() {
		onCancel?.();
		dispatch('cancel');
	}
</script>

<Card.Root class="w-full max-w-4xl mx-auto">
	<Card.Header>
		<div class="flex items-center justify-between">
			<div>
				<Card.Title class="text-xl">Import {moduleName}</Card.Title>
				<Card.Description>
					Import records from a CSV or Excel file into {moduleName}
				</Card.Description>
			</div>
			<Button variant="ghost" size="icon" onclick={handleCancel}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Step indicators -->
		<div class="flex items-center justify-between mt-6">
			{#each steps as step, index}
				{@const StepIcon = step.icon}
				{@const isActive = index === currentStep}
				{@const isComplete = index < currentStep}
				{@const isClickable = index < currentStep}
				<button
					type="button"
					class="flex flex-col items-center gap-2 flex-1"
					class:cursor-pointer={isClickable}
					class:cursor-default={!isClickable}
					onclick={() => isClickable && goToStep(index)}
					disabled={!isClickable}
				>
					<div
						class="w-10 h-10 rounded-full flex items-center justify-center transition-colors"
						class:bg-primary={isActive || isComplete}
						class:text-primary-foreground={isActive || isComplete}
						class:bg-muted={!isActive && !isComplete}
						class:text-muted-foreground={!isActive && !isComplete}
					>
						{#if isComplete}
							<Check class="h-5 w-5" />
						{:else}
							<StepIcon class="h-5 w-5" />
						{/if}
					</div>
					<span
						class="text-xs font-medium"
						class:text-primary={isActive}
						class:text-muted-foreground={!isActive && !isComplete}
					>
						{step.label}
					</span>
				</button>
				{#if index < steps.length - 1}
					<div
						class="h-0.5 flex-1 mx-2 -mt-6"
						class:bg-primary={index < currentStep}
						class:bg-muted={index >= currentStep}
					></div>
				{/if}
			{/each}
		</div>
	</Card.Header>

	<Card.Content class="min-h-[400px]">
		{#if currentStep === 0}
			<FileUploadStep
				{moduleApiName}
				{isLoading}
				uploadedFile={uploadedFile}
				previewData={uploadResponse?.preview}
				onUpload={handleFileUpload}
			/>
		{:else if currentStep === 1}
			<ColumnMappingStep
				headers={uploadResponse?.preview?.headers || []}
				previewRows={uploadResponse?.preview?.preview_rows || []}
				{moduleFields}
				bind:mapping={columnMapping}
			/>
		{:else if currentStep === 2}
			<ImportOptionsStep
				{moduleFields}
				bind:options={importOptions}
			/>
		{:else if currentStep === 3}
			<ValidationStep
				importData={importData}
				{isLoading}
			/>
		{:else if currentStep === 4}
			<ImportProgressStep
				importData={importData}
			/>
		{/if}
	</Card.Content>

	<Card.Footer class="flex justify-between">
		<div>
			{#if currentStep > 0 && currentStep < 4}
				<Button variant="outline" onclick={handleBack} disabled={isLoading}>
					<ArrowLeft class="mr-2 h-4 w-4" />
					Back
				</Button>
			{/if}
		</div>

		<div class="flex gap-2">
			<Button variant="ghost" onclick={handleCancel}>
				Cancel
			</Button>
			{#if currentStep < 4}
				<Button
					onclick={handleNext}
					disabled={!canProceed() || isLoading}
				>
					{#if isLoading}
						<span class="animate-spin mr-2">...</span>
					{/if}
					{#if currentStep === 2}
						Validate
					{:else if currentStep === 3}
						Start Import
					{:else}
						Next
					{/if}
					<ArrowRight class="ml-2 h-4 w-4" />
				</Button>
			{:else}
				<Button onclick={handleCancel}>
					Done
				</Button>
			{/if}
		</div>
	</Card.Footer>
</Card.Root>
