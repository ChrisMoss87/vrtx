import { apiClient } from './client';

// Types
export interface Competitor {
	id: number;
	name: string;
	website: string | null;
	logo_url: string | null;
	description: string | null;
	market_position: string | null;
	pricing_info: string | null;
	is_active: boolean;
	win_rate: number | null;
	total_deals: number;
	won_deals?: number;
	lost_deals?: number;
	sections?: BattlecardSection[];
	objection_count?: number;
	note_count?: number;
	last_updated_at: string | null;
}

export interface BattlecardSection {
	id: number;
	type: string;
	type_label: string;
	content: string;
	content_lines?: string[];
	display_order: number;
}

export interface Battlecard {
	id: number;
	name: string;
	logo_url: string | null;
	website: string | null;
	description: string | null;
	market_position: string | null;
	pricing_info: string | null;
	win_rate: number | null;
	total_deals: number;
	won_deals: number;
	lost_deals: number;
	sections: BattlecardSection[];
	objections: ObjectionHandler[];
	recent_notes: CompetitorNote[];
	last_updated: string | null;
}

export interface ObjectionHandler {
	id: number;
	objection: string;
	counter_script: string;
	effectiveness_score: number | null;
	effectiveness_label: string;
	use_count: number;
	success_count?: number;
	created_by: string | null;
	created_at: string;
}

export interface CompetitorNote {
	id: number;
	content: string;
	source: string | null;
	is_verified: boolean;
	created_by: string | null;
	verified_by?: string | null;
	created_at: string;
}

export interface DealCompetitor {
	id: number;
	competitor_id: number;
	competitor_name: string;
	competitor_logo: string | null;
	is_primary: boolean;
	notes: string | null;
	outcome: 'won' | 'lost' | 'unknown' | null;
	win_rate: number | null;
}

export interface CompetitorAnalytics {
	summary: {
		total_deals: number;
		won: number;
		lost: number;
		win_rate: number | null;
		won_amount: number;
		lost_amount: number;
	};
	by_deal_size: Array<{
		label: string;
		won: number;
		total: number;
		win_rate: number | null;
	}>;
	top_objections: Array<{
		objection: string;
		effectiveness: number | null;
		uses: number;
	}>;
	monthly_trend: Array<{
		month: string;
		won: number;
		lost: number;
		total: number;
		win_rate: number | null;
	}>;
}

// Response types
interface CompetitorListResponse {
	data: Competitor[];
}

interface CompetitorResponse {
	data: Competitor;
}

interface BattlecardResponse {
	data: Battlecard;
}

interface BattlecardSectionResponse {
	data: BattlecardSection;
}

interface ObjectionListResponse {
	data: ObjectionHandler[];
}

interface ObjectionResponse {
	data: ObjectionHandler;
}

interface ObjectionFeedbackResponse {
	data: { effectiveness_score: number | null; use_count: number };
}

interface NoteListResponse {
	data: CompetitorNote[];
}

interface NoteResponse {
	data: CompetitorNote;
}

interface AnalyticsResponse {
	data: CompetitorAnalytics;
}

interface DealCompetitorListResponse {
	data: DealCompetitor[];
}

interface DealCompetitorResponse {
	data: DealCompetitor;
}

// API Functions

// Competitors CRUD
export async function getCompetitors(search?: string, activeOnly = true): Promise<Competitor[]> {
	const params: Record<string, string> = {};
	if (activeOnly) params.active_only = 'true';
	if (search) params.search = search;
	const response = await apiClient.get<CompetitorListResponse>('/competitors', params);
	return response.data;
}

export async function getCompetitor(id: number): Promise<Competitor> {
	const response = await apiClient.get<CompetitorResponse>(`/competitors/${id}`);
	return response.data;
}

export async function createCompetitor(data: {
	name: string;
	website?: string;
	logo_url?: string;
	description?: string;
	market_position?: string;
	pricing_info?: string;
}): Promise<Competitor> {
	const response = await apiClient.post<CompetitorResponse>('/competitors', data);
	return response.data;
}

export async function updateCompetitor(
	id: number,
	data: Partial<Competitor>
): Promise<Competitor> {
	const response = await apiClient.put<CompetitorResponse>(`/competitors/${id}`, data);
	return response.data;
}

export async function deleteCompetitor(id: number): Promise<void> {
	await apiClient.delete(`/competitors/${id}`);
}

// Battlecard
export async function getBattlecard(competitorId: number): Promise<Battlecard> {
	const response = await apiClient.get<BattlecardResponse>(`/competitors/${competitorId}/battlecard`);
	return response.data;
}

export async function updateBattlecardSection(
	competitorId: number,
	sectionId: number,
	content: string
): Promise<BattlecardSection> {
	const response = await apiClient.put<BattlecardSectionResponse>(
		`/competitors/${competitorId}/battlecard/sections/${sectionId}`,
		{ content }
	);
	return response.data;
}

export async function createBattlecardSection(
	competitorId: number,
	type: string,
	content: string
): Promise<BattlecardSection> {
	const response = await apiClient.post<BattlecardSectionResponse>(
		`/competitors/${competitorId}/battlecard/sections`,
		{ type, content }
	);
	return response.data;
}

// Objections
export async function getObjections(competitorId: number): Promise<ObjectionHandler[]> {
	const response = await apiClient.get<ObjectionListResponse>(`/competitors/${competitorId}/objections`);
	return response.data;
}

export async function createObjection(
	competitorId: number,
	objection: string,
	counterScript: string
): Promise<ObjectionHandler> {
	const response = await apiClient.post<ObjectionResponse>(`/competitors/${competitorId}/objections`, {
		objection,
		counter_script: counterScript
	});
	return response.data;
}

export async function updateObjection(
	competitorId: number,
	objectionId: number,
	data: { objection?: string; counter_script?: string }
): Promise<ObjectionHandler> {
	const response = await apiClient.put<ObjectionResponse>(
		`/competitors/${competitorId}/objections/${objectionId}`,
		data
	);
	return response.data;
}

export async function recordObjectionFeedback(
	competitorId: number,
	objectionId: number,
	wasSuccessful: boolean,
	dealId?: number,
	feedback?: string
): Promise<{ effectiveness_score: number | null; use_count: number }> {
	const response = await apiClient.post<ObjectionFeedbackResponse>(
		`/competitors/${competitorId}/objections/${objectionId}/feedback`,
		{
			was_successful: wasSuccessful,
			deal_id: dealId,
			feedback
		}
	);
	return response.data;
}

// Notes
export async function getCompetitorNotes(competitorId: number): Promise<CompetitorNote[]> {
	const response = await apiClient.get<NoteListResponse>(`/competitors/${competitorId}/notes`);
	return response.data;
}

export async function addCompetitorNote(
	competitorId: number,
	content: string,
	source?: string
): Promise<CompetitorNote> {
	const response = await apiClient.post<NoteResponse>(`/competitors/${competitorId}/notes`, {
		content,
		source
	});
	return response.data;
}

// Analytics
export async function getCompetitorAnalytics(competitorId: number): Promise<CompetitorAnalytics> {
	const response = await apiClient.get<AnalyticsResponse>(`/competitors/${competitorId}/analytics`);
	return response.data;
}

export async function compareCompetitors(ids: number[]): Promise<Competitor[]> {
	const response = await apiClient.get<CompetitorListResponse>('/competitors/comparison', {
		ids: ids.join(',')
	} as Record<string, string>);
	return response.data;
}

// Deal-Competitor linking
export async function getDealCompetitors(dealId: number): Promise<DealCompetitor[]> {
	const response = await apiClient.get<DealCompetitorListResponse>(`/deals/${dealId}/competitors`);
	return response.data;
}

export async function addCompetitorToDeal(
	dealId: number,
	competitorId: number,
	isPrimary = false,
	notes?: string
): Promise<DealCompetitor> {
	const response = await apiClient.post<DealCompetitorResponse>(`/deals/${dealId}/competitors`, {
		competitor_id: competitorId,
		is_primary: isPrimary,
		notes
	});
	return response.data;
}

export async function removeCompetitorFromDeal(dealId: number, competitorId: number): Promise<void> {
	await apiClient.delete(`/deals/${dealId}/competitors/${competitorId}`);
}

export async function updateDealCompetitorOutcome(
	dealId: number,
	competitorId: number,
	outcome: 'won' | 'lost' | 'unknown'
): Promise<DealCompetitor> {
	const response = await apiClient.put<DealCompetitorResponse>(
		`/deals/${dealId}/competitors/${competitorId}/outcome`,
		{ outcome }
	);
	return response.data;
}
