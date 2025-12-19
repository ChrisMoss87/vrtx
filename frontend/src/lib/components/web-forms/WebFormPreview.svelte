<script lang="ts">
	import { type WebFormField, type WebFormStyling, FIELD_TYPES } from '$lib/api/web-forms';

	interface Props {
		name: string;
		fields: WebFormField[];
		styling: WebFormStyling;
		submitText?: string;
	}

	let { name, fields, styling, submitText = 'Submit' }: Props = $props();
</script>

<div
	class="rounded-lg"
	style:background-color={styling.background_color}
	style:color={styling.text_color}
	style:padding={styling.padding}
	style:font-family={styling.font_family}
	style:font-size={styling.font_size}
	style:max-width={styling.max_width}
>
	{#if name}
		<h2 class="mb-4 text-xl font-semibold">{name}</h2>
	{/if}

	<form class="space-y-4" onsubmit={(e: Event) => e.preventDefault()}>
		{#each fields as field}
			{#if field.field_type !== 'hidden'}
				<div class="space-y-1">
					<label
						class="block text-sm font-medium"
						style:color={styling.label_color}
						for={`preview-${field.display_order}`}
					>
						{field.label || '(No label)'}
						{#if field.is_required}
							<span class="text-red-500">*</span>
						{/if}
					</label>

					{#if field.field_type === 'textarea'}
						<textarea
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
							rows="3"
						></textarea>
					{:else if field.field_type === 'select'}
						<select
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
						>
							<option value="">Select...</option>
							{#each field.options ?? [] as option}
								<option value={option.value}>{option.label || option.value}</option>
							{/each}
						</select>
					{:else if field.field_type === 'radio'}
						<div class="space-y-2">
							{#each field.options ?? [] as option, i}
								<label class="flex items-center gap-2">
									<input
										type="radio"
										name={`preview-radio-${field.display_order}`}
										value={option.value}
									/>
									<span>{option.label || option.value}</span>
								</label>
							{/each}
						</div>
					{:else if field.field_type === 'checkbox'}
						{#if (field.options ?? []).length > 0}
							<div class="space-y-2">
								{#each field.options ?? [] as option}
									<label class="flex items-center gap-2">
										<input type="checkbox" value={option.value} />
										<span>{option.label || option.value}</span>
									</label>
								{/each}
							</div>
						{:else}
							<label class="flex items-center gap-2">
								<input type="checkbox" />
								<span>{field.label}</span>
							</label>
						{/if}
					{:else if field.field_type === 'date'}
						<input
							type="date"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
						/>
					{:else if field.field_type === 'datetime'}
						<input
							type="datetime-local"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
						/>
					{:else if field.field_type === 'number' || field.field_type === 'currency'}
						<input
							type="number"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
							step={field.field_type === 'currency' ? '0.01' : undefined}
						/>
					{:else if field.field_type === 'file'}
						<input
							type="file"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
						/>
					{:else if field.field_type === 'email'}
						<input
							type="email"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
						/>
					{:else if field.field_type === 'phone'}
						<input
							type="tel"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
						/>
					{:else if field.field_type === 'url'}
						<input
							type="url"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
						/>
					{:else}
						<input
							type="text"
							id={`preview-${field.display_order}`}
							class="w-full px-3 py-2"
							style:border="1px solid {styling.border_color}"
							style:border-radius={styling.border_radius}
							style:font-family="inherit"
							style:font-size="inherit"
							placeholder={field.placeholder}
						/>
					{/if}
				</div>
			{/if}
		{/each}

		{#if fields.length === 0}
			<div
				class="py-8 text-center"
				style:border="2px dashed {styling.border_color}"
				style:border-radius={styling.border_radius}
			>
				<p class="text-muted-foreground">Add fields to see preview</p>
			</div>
		{/if}

		<button
			type="submit"
			class="px-6 py-2 font-medium text-white"
			style:background-color={styling.primary_color}
			style:border-radius={styling.border_radius}
		>
			{submitText}
		</button>
	</form>
</div>
