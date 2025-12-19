<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import * as Popover from '$lib/components/ui/popover';
	import {
		Search,
		Box,
		Users,
		Building2,
		Briefcase,
		DollarSign,
		ShoppingCart,
		FileText,
		Mail,
		Phone,
		Calendar,
		Clock,
		Star,
		Heart,
		Tag,
		Folder,
		Settings,
		Target,
		TrendingUp,
		BarChart3,
		PieChart,
		Activity,
		Zap,
		Award,
		Gift,
		Package,
		Truck,
		MapPin,
		Globe,
		Link,
		Bookmark,
		Flag,
		Bell,
		MessageSquare,
		Send,
		Inbox,
		Archive,
		Clipboard,
		CheckSquare,
		ListTodo,
		Layers,
		Grid3X3,
		LayoutGrid,
		Wallet,
		CreditCard,
		Receipt,
		Calculator,
		Percent,
		Hash,
		AtSign,
		Key,
		Lock,
		Shield,
		Eye,
		Lightbulb,
		Sparkles,
		Rocket,
		Plane,
		Car,
		Home,
		Store,
		Factory,
		Landmark,
		GraduationCap,
		BookOpen,
		Newspaper,
		Camera,
		Image,
		Video,
		Music,
		Headphones,
		Mic,
		Monitor,
		Smartphone,
		Tablet,
		Laptop,
		Server,
		Database,
		Cloud,
		Wifi,
		Radio,
		Tv,
		Gamepad2,
		Puzzle,
		Wrench,
		Hammer,
		Paintbrush,
		Palette,
		Scissors,
		Ruler,
		Compass,
		Map,
		Navigation,
		Sun,
		Moon,
		CloudRain,
		Thermometer,
		Flame,
		Droplet,
		Wind,
		Leaf,
		TreePine,
		Flower2,
		Bug,
		Fish,
		Bird,
		Cat,
		Dog,
		Smile,
		Frown,
		Meh,
		Coffee,
		Pizza,
		Apple,
		Wine,
		Beer,
		Utensils,
		ChefHat,
		Cake,
		IceCream2,
		Candy,
		Cookie,
		Croissant,
		type Icon as IconType
	} from 'lucide-svelte';
	import type { ComponentType } from 'svelte';

	interface Props {
		value: string;
		onchange: (value: string) => void;
	}

	let { value, onchange }: Props = $props();
	let searchQuery = $state('');
	let open = $state(false);

	// Map of icon names to components
	const ICONS: Record<string, ComponentType> = {
		Box,
		Users,
		Building2,
		Briefcase,
		DollarSign,
		ShoppingCart,
		FileText,
		Mail,
		Phone,
		Calendar,
		Clock,
		Star,
		Heart,
		Tag,
		Folder,
		Settings,
		Target,
		TrendingUp,
		BarChart3,
		PieChart,
		Activity,
		Zap,
		Award,
		Gift,
		Package,
		Truck,
		MapPin,
		Globe,
		Link,
		Bookmark,
		Flag,
		Bell,
		MessageSquare,
		Send,
		Inbox,
		Archive,
		Clipboard,
		CheckSquare,
		ListTodo,
		Layers,
		Grid3X3,
		LayoutGrid,
		Wallet,
		CreditCard,
		Receipt,
		Calculator,
		Percent,
		Hash,
		AtSign,
		Key,
		Lock,
		Shield,
		Eye,
		Lightbulb,
		Sparkles,
		Rocket,
		Plane,
		Car,
		Home,
		Store,
		Factory,
		Landmark,
		GraduationCap,
		BookOpen,
		Newspaper,
		Camera,
		Image,
		Video,
		Music,
		Headphones,
		Mic,
		Monitor,
		Smartphone,
		Tablet,
		Laptop,
		Server,
		Database,
		Cloud,
		Wifi,
		Radio,
		Tv,
		Gamepad2,
		Puzzle,
		Wrench,
		Hammer,
		Paintbrush,
		Palette,
		Scissors,
		Ruler,
		Compass,
		Map,
		Navigation,
		Sun,
		Moon,
		CloudRain,
		Thermometer,
		Flame,
		Droplet,
		Wind,
		Leaf,
		TreePine,
		Flower2,
		Bug,
		Fish,
		Bird,
		Cat,
		Dog,
		Smile,
		Frown,
		Meh,
		Coffee,
		Pizza,
		Apple,
		Wine,
		Beer,
		Utensils,
		ChefHat,
		Cake,
		IceCream2,
		Candy,
		Cookie,
		Croissant
	};

	const iconNames = Object.keys(ICONS);

	let filteredIcons = $derived(
		searchQuery
			? iconNames.filter((name) => name.toLowerCase().includes(searchQuery.toLowerCase()))
			: iconNames
	);

	function selectIcon(name: string) {
		onchange(name);
		open = false;
	}

	function getIconComponent(name: string): ComponentType | null {
		return ICONS[name] || null;
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
		<Button {...props} variant="outline" class="h-11 w-full justify-start gap-3">
			{#if value && ICONS[value]}
				{@const SelectedIcon = ICONS[value]}
				<div class="flex h-7 w-7 items-center justify-center rounded bg-primary/10 text-primary">
					<SelectedIcon class="h-4 w-4" />
				</div>
				<span>{value}</span>
			{:else}
				<div class="flex h-7 w-7 items-center justify-center rounded bg-muted">
					<Box class="h-4 w-4 text-muted-foreground" />
				</div>
				<span class="text-muted-foreground">Select an icon...</span>
			{/if}
		</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-80 p-0" align="start">
		<div class="border-b p-3">
			<div class="relative">
				<Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					type="text"
					placeholder="Search icons..."
					bind:value={searchQuery}
					class="h-9 pl-9"
				/>
			</div>
		</div>
		<ScrollArea class="h-64">
			<div class="grid grid-cols-6 gap-1 p-2">
				{#each filteredIcons as iconName (iconName)}
					{@const IconComponent = ICONS[iconName]}
					<button
						type="button"
						onclick={() => selectIcon(iconName)}
						class="flex h-10 w-10 items-center justify-center rounded-md transition-colors
							{value === iconName
								? 'bg-primary text-primary-foreground'
								: 'hover:bg-accent'}"
						title={iconName}
					>
						<IconComponent class="h-5 w-5" />
					</button>
				{/each}
			</div>
			{#if filteredIcons.length === 0}
				<div class="py-8 text-center text-sm text-muted-foreground">
					No icons found
				</div>
			{/if}
		</ScrollArea>
	</Popover.Content>
</Popover.Root>
