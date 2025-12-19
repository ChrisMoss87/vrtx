/**
 * Kanban Card Configuration Types
 * These types define how kanban cards should be styled and laid out
 */

export type CardFieldDisplayType = 'title' | 'subtitle' | 'badge' | 'value' | 'text' | 'small';

export interface CardField {
	fieldApiName: string;
	displayAs: CardFieldDisplayType;
	showLabel?: boolean;
}

export interface CardLayout {
	fields: CardField[];
	showFieldLabels?: boolean;
}

export interface CardStyle {
	backgroundColor?: string;
	borderColor?: string;
	accentColor?: string;  // Left border strip
	accentWidth?: number;  // Width of accent strip in pixels (0 to disable)
	titleColor?: string;
	subtitleColor?: string;
	textColor?: string;
}

export interface KanbanCardConfig {
	default: CardStyle;
	fieldOverrides?: {
		[fieldValue: string]: Partial<CardStyle>;
	};
	layout: CardLayout;
}

/**
 * Preset themes for quick setup
 */
export interface CardTheme {
	name: string;
	description: string;
	config: Partial<KanbanCardConfig>;
}

export const PRESET_THEMES: CardTheme[] = [
	{
		name: 'Minimal',
		description: 'Clean and simple with subtle accents',
		config: {
			default: {
				backgroundColor: '#ffffff',
				borderColor: '#e5e7eb',
				accentColor: '#3b82f6',
				accentWidth: 3,
				titleColor: '#111827',
				subtitleColor: '#6b7280',
				textColor: '#374151'
			}
		}
	},
	{
		name: 'Colorful',
		description: 'Bold colors and vibrant accents',
		config: {
			default: {
				backgroundColor: '#fef3c7',
				borderColor: '#f59e0b',
				accentColor: '#f59e0b',
				accentWidth: 4,
				titleColor: '#78350f',
				subtitleColor: '#92400e',
				textColor: '#451a03'
			}
		}
	},
	{
		name: 'Professional',
		description: 'Subtle grays with blue accents',
		config: {
			default: {
				backgroundColor: '#f9fafb',
				borderColor: '#d1d5db',
				accentColor: '#1e40af',
				accentWidth: 3,
				titleColor: '#1f2937',
				subtitleColor: '#4b5563',
				textColor: '#6b7280'
			}
		}
	},
	{
		name: 'Dark',
		description: 'Dark theme with bright accents',
		config: {
			default: {
				backgroundColor: '#1f2937',
				borderColor: '#374151',
				accentColor: '#60a5fa',
				accentWidth: 3,
				titleColor: '#f9fafb',
				subtitleColor: '#d1d5db',
				textColor: '#e5e7eb'
			}
		}
	},
	{
		name: 'Soft',
		description: 'Soft pastels with gentle borders',
		config: {
			default: {
				backgroundColor: '#f0f9ff',
				borderColor: '#bae6fd',
				accentColor: '#0ea5e9',
				accentWidth: 2,
				titleColor: '#0c4a6e',
				subtitleColor: '#075985',
				textColor: '#0369a1'
			}
		}
	}
];

/**
 * Default card configuration
 */
export const DEFAULT_CARD_CONFIG: KanbanCardConfig = {
	default: {
		backgroundColor: '#ffffff',
		borderColor: '#e5e7eb',
		accentColor: '#3b82f6',
		accentWidth: 3,
		titleColor: '#111827',
		subtitleColor: '#6b7280',
		textColor: '#374151'
	},
	layout: {
		fields: [],
		showFieldLabels: false
	}
};

/**
 * Merge card styles, with overrides taking precedence
 */
export function mergeCardStyles(base: CardStyle, override?: Partial<CardStyle>): CardStyle {
	if (!override) return base;

	return {
		backgroundColor: override.backgroundColor ?? base.backgroundColor,
		borderColor: override.borderColor ?? base.borderColor,
		accentColor: override.accentColor ?? base.accentColor,
		accentWidth: override.accentWidth ?? base.accentWidth,
		titleColor: override.titleColor ?? base.titleColor,
		subtitleColor: override.subtitleColor ?? base.subtitleColor,
		textColor: override.textColor ?? base.textColor
	};
}
