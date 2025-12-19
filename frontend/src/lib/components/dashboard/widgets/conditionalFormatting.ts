/**
 * Conditional formatting utilities for dashboard widgets
 */

export type FormatCondition = {
	type: 'threshold' | 'range' | 'equals' | 'contains';
	value?: number | string;
	min?: number;
	max?: number;
	style: FormatStyle;
};

export type FormatStyle = {
	backgroundColor?: string;
	textColor?: string;
	fontWeight?: 'normal' | 'bold';
	icon?: 'up' | 'down' | 'check' | 'x' | 'warning' | 'info';
};

export type ConditionalRule = {
	field?: string;
	conditions: FormatCondition[];
};

// Preset color schemes
export const presets = {
	// Traffic light: red -> yellow -> green
	trafficLight: (value: number, thresholds: [number, number] = [33, 66]): FormatStyle => {
		if (value >= thresholds[1]) {
			return { backgroundColor: '#dcfce7', textColor: '#166534' }; // green
		}
		if (value >= thresholds[0]) {
			return { backgroundColor: '#fef9c3', textColor: '#854d0e' }; // yellow
		}
		return { backgroundColor: '#fee2e2', textColor: '#991b1b' }; // red
	},

	// Reverse traffic light: green -> yellow -> red (for metrics where lower is better)
	reverseTrafficLight: (value: number, thresholds: [number, number] = [33, 66]): FormatStyle => {
		if (value <= thresholds[0]) {
			return { backgroundColor: '#dcfce7', textColor: '#166534' }; // green
		}
		if (value <= thresholds[1]) {
			return { backgroundColor: '#fef9c3', textColor: '#854d0e' }; // yellow
		}
		return { backgroundColor: '#fee2e2', textColor: '#991b1b' }; // red
	},

	// Progress: empty -> filled based on percentage
	progress: (value: number): FormatStyle => {
		if (value >= 100) {
			return { backgroundColor: '#dcfce7', textColor: '#166534', icon: 'check' };
		}
		if (value >= 75) {
			return { backgroundColor: '#dbeafe', textColor: '#1e40af' };
		}
		if (value >= 50) {
			return { backgroundColor: '#fef9c3', textColor: '#854d0e' };
		}
		if (value >= 25) {
			return { backgroundColor: '#ffedd5', textColor: '#9a3412' };
		}
		return { backgroundColor: '#fee2e2', textColor: '#991b1b' };
	},

	// Trend: positive/negative
	trend: (value: number): FormatStyle => {
		if (value > 0) {
			return { textColor: '#166534', icon: 'up' };
		}
		if (value < 0) {
			return { textColor: '#991b1b', icon: 'down' };
		}
		return { textColor: '#6b7280' };
	},

	// Heat: intensity based on value (0-100 scale)
	heat: (value: number, max: number = 100): FormatStyle => {
		const intensity = Math.min(1, Math.max(0, value / max));
		const hue = 220; // Blue hue
		const saturation = 80;
		const lightness = 95 - intensity * 40; // 95% (light) to 55% (darker)

		return {
			backgroundColor: `hsl(${hue}, ${saturation}%, ${lightness}%)`
		};
	},

	// Status-based formatting
	status: (status: string): FormatStyle => {
		const statusMap: Record<string, FormatStyle> = {
			active: { backgroundColor: '#dcfce7', textColor: '#166534' },
			pending: { backgroundColor: '#fef9c3', textColor: '#854d0e' },
			inactive: { backgroundColor: '#f3f4f6', textColor: '#6b7280' },
			error: { backgroundColor: '#fee2e2', textColor: '#991b1b' },
			warning: { backgroundColor: '#ffedd5', textColor: '#9a3412' },
			success: { backgroundColor: '#dcfce7', textColor: '#166534' },
			info: { backgroundColor: '#dbeafe', textColor: '#1e40af' }
		};

		return statusMap[status.toLowerCase()] || {};
	}
};

/**
 * Apply conditional formatting rules to a value
 */
export function applyConditionalFormat(
	value: number | string,
	rules: FormatCondition[]
): FormatStyle {
	for (const rule of rules) {
		switch (rule.type) {
			case 'threshold':
				if (typeof value === 'number' && typeof rule.value === 'number' && value >= rule.value) {
					return rule.style;
				}
				break;
			case 'range':
				if (
					typeof value === 'number' &&
					rule.min !== undefined &&
					rule.max !== undefined &&
					value >= rule.min &&
					value <= rule.max
				) {
					return rule.style;
				}
				break;
			case 'equals':
				if (value === rule.value) {
					return rule.style;
				}
				break;
			case 'contains':
				if (typeof value === 'string' && typeof rule.value === 'string') {
					if (value.toLowerCase().includes(rule.value.toLowerCase())) {
						return rule.style;
					}
				}
				break;
		}
	}

	return {};
}

/**
 * Generate CSS classes from format style
 */
export function formatStyleToClasses(style: FormatStyle): string {
	const classes: string[] = [];

	if (style.fontWeight === 'bold') {
		classes.push('font-bold');
	}

	return classes.join(' ');
}

/**
 * Generate inline styles from format style
 */
export function formatStyleToInline(style: FormatStyle): string {
	const styles: string[] = [];

	if (style.backgroundColor) {
		styles.push(`background-color: ${style.backgroundColor}`);
	}
	if (style.textColor) {
		styles.push(`color: ${style.textColor}`);
	}

	return styles.join('; ');
}
