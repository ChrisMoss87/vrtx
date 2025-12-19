<script lang="ts">
	interface Props {
		data: number[];
		width?: number;
		height?: number;
		strokeColor?: string;
		fillColor?: string;
		strokeWidth?: number;
		showArea?: boolean;
		className?: string;
	}

	let {
		data,
		width = 100,
		height = 30,
		strokeColor = 'currentColor',
		fillColor,
		strokeWidth = 1.5,
		showArea = false,
		className = ''
	}: Props = $props();

	const path = $derived(() => {
		if (!data || data.length < 2) return '';

		const min = Math.min(...data);
		const max = Math.max(...data);
		const range = max - min || 1;
		const padding = 2;
		const effectiveHeight = height - padding * 2;
		const effectiveWidth = width - padding * 2;
		const stepX = effectiveWidth / (data.length - 1);

		const points = data.map((value, index) => {
			const x = padding + index * stepX;
			const y = padding + effectiveHeight - ((value - min) / range) * effectiveHeight;
			return { x, y };
		});

		// Create smooth curve using quadratic bezier
		let d = `M ${points[0].x} ${points[0].y}`;

		for (let i = 1; i < points.length; i++) {
			const prev = points[i - 1];
			const curr = points[i];
			const cpX = (prev.x + curr.x) / 2;
			d += ` Q ${prev.x + (curr.x - prev.x) / 4} ${prev.y}, ${cpX} ${(prev.y + curr.y) / 2}`;
			d += ` Q ${curr.x - (curr.x - prev.x) / 4} ${curr.y}, ${curr.x} ${curr.y}`;
		}

		return d;
	});

	const areaPath = $derived(() => {
		if (!showArea || !data || data.length < 2) return '';

		const min = Math.min(...data);
		const max = Math.max(...data);
		const range = max - min || 1;
		const padding = 2;
		const effectiveHeight = height - padding * 2;
		const effectiveWidth = width - padding * 2;
		const stepX = effectiveWidth / (data.length - 1);

		const points = data.map((value, index) => {
			const x = padding + index * stepX;
			const y = padding + effectiveHeight - ((value - min) / range) * effectiveHeight;
			return { x, y };
		});

		// Start with line path
		let d = `M ${points[0].x} ${points[0].y}`;

		for (let i = 1; i < points.length; i++) {
			const prev = points[i - 1];
			const curr = points[i];
			const cpX = (prev.x + curr.x) / 2;
			d += ` Q ${prev.x + (curr.x - prev.x) / 4} ${prev.y}, ${cpX} ${(prev.y + curr.y) / 2}`;
			d += ` Q ${curr.x - (curr.x - prev.x) / 4} ${curr.y}, ${curr.x} ${curr.y}`;
		}

		// Close the area
		d += ` L ${points[points.length - 1].x} ${height - padding}`;
		d += ` L ${points[0].x} ${height - padding}`;
		d += ' Z';

		return d;
	});

	const lastPoint = $derived(() => {
		if (!data || data.length < 2) return null;

		const min = Math.min(...data);
		const max = Math.max(...data);
		const range = max - min || 1;
		const padding = 2;
		const effectiveHeight = height - padding * 2;
		const effectiveWidth = width - padding * 2;
		const stepX = effectiveWidth / (data.length - 1);

		const lastValue = data[data.length - 1];
		const x = padding + (data.length - 1) * stepX;
		const y = padding + effectiveHeight - ((lastValue - min) / range) * effectiveHeight;

		return { x, y };
	});
</script>

{#if data && data.length >= 2}
	<svg {width} {height} class={className} viewBox="0 0 {width} {height}">
		{#if showArea && areaPath()}
			<path
				d={areaPath()}
				fill={fillColor || `${strokeColor}20`}
				stroke="none"
			/>
		{/if}
		<path
			d={path()}
			fill="none"
			stroke={strokeColor}
			stroke-width={strokeWidth}
			stroke-linecap="round"
			stroke-linejoin="round"
		/>
		{#if lastPoint()}
			<circle
				cx={lastPoint()?.x}
				cy={lastPoint()?.y}
				r={3}
				fill={strokeColor}
			/>
		{/if}
	</svg>
{/if}
