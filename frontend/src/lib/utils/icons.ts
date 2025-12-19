/**
 * Utility for mapping icon string names to Lucide icon components
 */
import Building2Icon from '@lucide/svelte/icons/building-2';
import UsersIcon from '@lucide/svelte/icons/users';
import HandshakeIcon from '@lucide/svelte/icons/handshake';
import FileTextIcon from '@lucide/svelte/icons/file-text';
import CheckSquareIcon from '@lucide/svelte/icons/check-square';
import ActivityIcon from '@lucide/svelte/icons/activity';
import FileSignatureIcon from '@lucide/svelte/icons/file-signature';
import HeadsetIcon from '@lucide/svelte/icons/headset';
import PackageIcon from '@lucide/svelte/icons/package';
import BriefcaseIcon from '@lucide/svelte/icons/briefcase';
import LayoutDashboardIcon from '@lucide/svelte/icons/layout-dashboard';
import HomeIcon from '@lucide/svelte/icons/home';
import FolderIcon from '@lucide/svelte/icons/folder';
import SettingsIcon from '@lucide/svelte/icons/settings';
import BoxIcon from '@lucide/svelte/icons/box';
import SquareIcon from '@lucide/svelte/icons/square';
import CircleIcon from '@lucide/svelte/icons/circle';
import StarIcon from '@lucide/svelte/icons/star';
import HeartIcon from '@lucide/svelte/icons/heart';
import ShoppingCartIcon from '@lucide/svelte/icons/shopping-cart';
import MailIcon from '@lucide/svelte/icons/mail';
import PhoneIcon from '@lucide/svelte/icons/phone';
import CalendarIcon from '@lucide/svelte/icons/calendar';
import ClockIcon from '@lucide/svelte/icons/clock';
import TrendingUpIcon from '@lucide/svelte/icons/trending-up';
import DollarSignIcon from '@lucide/svelte/icons/dollar-sign';
import BarChart2Icon from '@lucide/svelte/icons/bar-chart-2';
import PieChartIcon from '@lucide/svelte/icons/pie-chart';
import MapPinIcon from '@lucide/svelte/icons/map-pin';
import TagIcon from '@lucide/svelte/icons/tag';
import ClipboardIcon from '@lucide/svelte/icons/clipboard';
import FileIcon from '@lucide/svelte/icons/file';
import ImageIcon from '@lucide/svelte/icons/image';
import LinkIcon from '@lucide/svelte/icons/link';
import MessageSquareIcon from '@lucide/svelte/icons/message-square';
import type { Component } from 'svelte';

// Map icon string names to their Lucide components
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const iconMap: Record<string, Component<any>> = {
	// Buildings / Organizations
	'building-2': Building2Icon,
	building2: Building2Icon,
	building: Building2Icon,

	// People
	users: UsersIcon,
	contacts: UsersIcon,

	// Deals / Sales
	handshake: HandshakeIcon,
	deals: HandshakeIcon,

	// Documents
	'file-text': FileTextIcon,
	'file-signature': FileSignatureIcon,
	invoices: FileTextIcon,
	quotes: FileSignatureIcon,
	file: FileIcon,

	// Tasks
	'check-square': CheckSquareIcon,
	tasks: CheckSquareIcon,

	// Activities
	activity: ActivityIcon,
	activities: ActivityIcon,

	// Support
	headset: HeadsetIcon,
	cases: HeadsetIcon,
	support: HeadsetIcon,

	// Products
	package: PackageIcon,
	products: PackageIcon,
	box: BoxIcon,

	// General
	briefcase: BriefcaseIcon,
	'layout-dashboard': LayoutDashboardIcon,
	dashboard: LayoutDashboardIcon,
	home: HomeIcon,
	folder: FolderIcon,
	settings: SettingsIcon,

	// Shapes
	square: SquareIcon,
	circle: CircleIcon,

	// Other common icons
	star: StarIcon,
	heart: HeartIcon,
	'shopping-cart': ShoppingCartIcon,
	cart: ShoppingCartIcon,
	mail: MailIcon,
	email: MailIcon,
	phone: PhoneIcon,
	calendar: CalendarIcon,
	clock: ClockIcon,
	'trending-up': TrendingUpIcon,
	'dollar-sign': DollarSignIcon,
	currency: DollarSignIcon,
	'bar-chart-2': BarChart2Icon,
	chart: BarChart2Icon,
	'pie-chart': PieChartIcon,
	'map-pin': MapPinIcon,
	location: MapPinIcon,
	tag: TagIcon,
	clipboard: ClipboardIcon,
	image: ImageIcon,
	link: LinkIcon,
	'message-square': MessageSquareIcon,
	message: MessageSquareIcon
};

/**
 * Get a Lucide icon component from a string name
 * Falls back to FolderIcon if not found
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function getIconComponent(iconName: string | null | undefined): Component<any> {
	if (!iconName) return FolderIcon;

	// Normalize the icon name (lowercase, replace underscores with hyphens)
	const normalized = iconName.toLowerCase().replace(/_/g, '-');

	return iconMap[normalized] || FolderIcon;
}

/**
 * Get all available icon names
 */
export function getAvailableIcons(): string[] {
	return Object.keys(iconMap).filter((key) => !key.includes('-') || iconMap[key] !== iconMap[key.replace(/-/g, '')]);
}
