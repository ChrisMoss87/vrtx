import { apiClient } from './client';

// Types
export interface AiSettings {
  id: number;
  is_enabled: boolean;
  provider: 'openai' | 'anthropic';
  model: string;
  has_api_key: boolean;
  max_tokens: number;
  temperature: number;
  monthly_budget_cents: number | null;
  monthly_usage_cents: number;
  budget_reset_day: number;
  features: Record<string, boolean>;
}

export interface AiUsage {
  current_month: {
    used_cents: number;
    budget_cents: number | null;
    remaining_cents: number | null;
    is_exceeded: boolean;
  };
  statistics: {
    total_requests: number;
    total_tokens: number;
    total_cost_cents: number;
    by_feature: Record<string, { requests: number; tokens: number; cost_cents: number }>;
  };
}

export interface AiPrompt {
  id: number;
  name: string;
  slug: string;
  category: string;
  system_prompt: string;
  user_prompt_template: string;
  variables: string[];
  is_active: boolean;
}

export interface EmailDraft {
  id: number;
  purpose: string;
  tone: string;
  subject: string;
  body: string;
  context: Record<string, unknown>;
  record_module: string | null;
  record_id: number | null;
  model_used: string;
  tokens_used: number;
  is_used: boolean;
  used_at: string | null;
  created_at: string;
}

export interface ToneAnalysis {
  tone: string;
  confidence: number;
  suggestions: string[];
  readability_score: number;
}

export interface ScoringModel {
  id: number;
  name: string;
  module_api_name: string;
  description: string | null;
  is_active: boolean;
  factors: ScoringFactor[];
  created_at: string;
  updated_at: string;
}

export interface ScoringFactor {
  id?: number;
  name: string;
  factor_type: 'field_value' | 'field_filled' | 'activity_count' | 'recency' | 'custom';
  field_name: string | null;
  operator: string | null;
  value: unknown;
  points: number;
  weight: number;
}

export interface LeadScore {
  id: number;
  record_module: string;
  record_id: number;
  score: number;
  grade: 'A' | 'B' | 'C' | 'D' | 'F';
  breakdown: Record<string, number>;
  explanations: string[];
  ai_insights: string | null;
  model_used: string | null;
  last_calculated_at: string | null;
}

export interface ScoreHistory {
  id: number;
  score: number;
  grade: string;
  change_reason: string;
  created_at: string;
}

export interface SentimentScore {
  id: number;
  entity_type: string;
  entity_id: number;
  record_module: string | null;
  record_id: number | null;
  score: number;
  category: 'positive' | 'neutral' | 'negative';
  emotion: string;
  confidence: number;
  details: Record<string, unknown> | null;
  color: string;
  icon: string;
  model_used: string;
  analyzed_at: string;
}

export interface SentimentSummary {
  has_data: boolean;
  average_score: number | null;
  overall_sentiment: string | null;
  trend: number | null;
  is_improving?: boolean;
  is_declining?: boolean;
  breakdown: {
    positive: number;
    neutral: number;
    negative: number;
  };
  last_analyzed_at?: string;
}

export interface SentimentAlert {
  id: number;
  record_module: string;
  record_id: number;
  alert_type: string;
  message: string;
  severity: 'low' | 'medium' | 'high';
  is_read: boolean;
  created_at: string;
}

// AI Settings
export async function getAiSettings() {
  return apiClient.get<{
    settings: AiSettings | null;
    is_configured: boolean;
    available_providers: Record<string, string>;
    available_models: Record<string, Record<string, { name: string; input_cost: number; output_cost: number }>>;
  }>('/ai/settings');
}

export async function updateAiSettings(data: Partial<{
  is_enabled: boolean;
  provider: string;
  model: string;
  api_key: string;
  max_tokens: number;
  temperature: number;
  monthly_budget_cents: number | null;
  budget_reset_day: number;
  features: Record<string, boolean>;
}>) {
  return apiClient.put<{ message: string; settings: AiSettings }>('/ai/settings', data);
}

export async function getAiUsage(startDate?: string, endDate?: string) {
  return apiClient.get<AiUsage>('/ai/usage', {
    params: { start_date: startDate, end_date: endDate }
  });
}

export async function testAiConnection() {
  return apiClient.post<{ success: boolean; message: string; model?: string; response?: string }>('/ai/test-connection');
}

// AI Prompts
export async function getAiPrompts() {
  return apiClient.get<{ prompts: AiPrompt[] }>('/ai/prompts');
}

export async function saveAiPrompt(data: Partial<AiPrompt>) {
  return apiClient.post<{ message: string; prompt: AiPrompt }>('/ai/prompts', data);
}

export async function deleteAiPrompt(id: number) {
  return apiClient.delete<{ message: string }>(`/ai/prompts/${id}`);
}

// Email Composition
export async function composeEmail(data: {
  purpose: string;
  recipient_name?: string;
  recipient_company?: string;
  context?: Record<string, unknown>;
  tone?: 'professional' | 'friendly' | 'formal' | 'casual' | 'urgent';
  record_module?: string;
  record_id?: number;
}) {
  return apiClient.post<{ draft: EmailDraft }>('/ai/email/compose', data);
}

export async function generateEmailReply(data: {
  email_id: number;
  intent: string;
  additional_context?: string;
}) {
  return apiClient.post<{ draft: EmailDraft }>('/ai/email/reply', data);
}

export async function improveEmail(data: {
  subject: string;
  body: string;
  improvement: string;
  record_module?: string;
  record_id?: number;
}) {
  return apiClient.post<{ draft: EmailDraft }>('/ai/email/improve', data);
}

export async function suggestSubjects(data: {
  body: string;
  count?: number;
  record_module?: string;
  record_id?: number;
}) {
  return apiClient.post<{ suggestions: string[] }>('/ai/email/suggest-subjects', data);
}

export async function analyzeEmailTone(body: string) {
  return apiClient.post<{ analysis: ToneAnalysis }>('/ai/email/analyze-tone', { body });
}

export async function getEmailDrafts(recordModule?: string, recordId?: number) {
  return apiClient.get<{ drafts: EmailDraft[] }>('/ai/email/drafts', {
    params: { record_module: recordModule, record_id: recordId }
  });
}

export async function getEmailDraft(id: number) {
  return apiClient.get<{ draft: EmailDraft }>(`/ai/email/drafts/${id}`);
}

export async function deleteEmailDraft(id: number) {
  return apiClient.delete<{ message: string }>(`/ai/email/drafts/${id}`);
}

export async function markDraftUsed(id: number) {
  return apiClient.post<{ message: string }>(`/ai/email/drafts/${id}/mark-used`);
}

// Lead Scoring
export async function getScoringModels(module?: string) {
  return apiClient.get<{ models: ScoringModel[] }>('/ai/scoring/models', {
    params: { module }
  });
}

export async function getScoringModel(id: number) {
  return apiClient.get<{ model: ScoringModel }>(`/ai/scoring/models/${id}`);
}

export async function saveScoringModel(data: Partial<ScoringModel>) {
  return apiClient.post<{ message: string; model: ScoringModel }>('/ai/scoring/models', data);
}

export async function deleteScoringModel(id: number) {
  return apiClient.delete<{ message: string }>(`/ai/scoring/models/${id}`);
}

export async function scoreRecord(module: string, recordId: number, useAi = false) {
  return apiClient.post<{ score: LeadScore }>('/ai/scoring/score-record', {
    module,
    record_id: recordId,
    use_ai: useAi
  });
}

export async function batchScoreRecords(module: string, recordIds?: number[]) {
  return apiClient.post<{ message: string; scored_count: number }>('/ai/scoring/batch-score', {
    module,
    record_ids: recordIds
  });
}

export async function getRecordScore(module: string, recordId: number) {
  return apiClient.get<{ score: LeadScore | null; message?: string }>(`/ai/scoring/records/${module}/${recordId}`);
}

export async function getScoreHistory(module: string, recordId: number) {
  return apiClient.get<{ history: ScoreHistory[] }>(`/ai/scoring/records/${module}/${recordId}/history`);
}

export async function getScoringStats(module: string) {
  return apiClient.get<{ distribution: Record<string, number>; average_score: number }>(`/ai/scoring/stats/${module}`);
}

export async function getTopScoredRecords(module: string, limit = 10) {
  return apiClient.get<{ records: Array<{ score: LeadScore; record: { id: number; data: Record<string, unknown> } | null }> }>(
    `/ai/scoring/top/${module}`,
    { params: { limit } }
  );
}

export async function getRecordsByGrade(module: string, grade: 'A' | 'B' | 'C' | 'D' | 'F') {
  return apiClient.get<{ records: Array<{ score: LeadScore; record: { id: number; data: Record<string, unknown> } | null }> }>(
    `/ai/scoring/grade/${module}/${grade}`
  );
}

// Sentiment Analysis
export async function analyzeSentiment(data: {
  text: string;
  entity_type: string;
  entity_id: number;
  record_module?: string;
  record_id?: number;
}) {
  return apiClient.post<{ sentiment: SentimentScore }>('/ai/sentiment/analyze', data);
}

export async function analyzeEmailSentiment(emailId: number) {
  return apiClient.post<{ sentiment: SentimentScore }>(`/ai/sentiment/analyze-email/${emailId}`);
}

export async function getSentimentSummary(module: string, recordId: number) {
  return apiClient.get<{ summary: SentimentSummary }>(`/ai/sentiment/summary/${module}/${recordId}`);
}

export async function getSentimentTimeline(module: string, recordId: number, limit = 20) {
  return apiClient.get<{ timeline: SentimentScore[] }>(`/ai/sentiment/timeline/${module}/${recordId}`, {
    params: { limit }
  });
}

export async function getSentimentAlerts() {
  return apiClient.get<{ alerts: SentimentAlert[] }>('/ai/sentiment/alerts');
}

export async function markAlertRead(id: number) {
  return apiClient.post<{ message: string }>(`/ai/sentiment/alerts/${id}/read`);
}

export async function dismissAlert(id: number) {
  return apiClient.post<{ message: string }>(`/ai/sentiment/alerts/${id}/dismiss`);
}

export async function getDecliningRecords(module: string) {
  return apiClient.get<{ records: Array<{ record_module: string; record_id: number; average_score: number; overall_sentiment: string; trend: number }> }>(
    `/ai/sentiment/declining/${module}`
  );
}

export async function getNegativeRecords(module: string) {
  return apiClient.get<{ records: Array<{ record_module: string; record_id: number; average_score: number; overall_sentiment: string; trend: number }> }>(
    `/ai/sentiment/negative/${module}`
  );
}

export async function getSentimentDistribution(module: string) {
  return apiClient.get<{ distribution: { positive: number; neutral: number; negative: number } }>(`/ai/sentiment/distribution/${module}`);
}

export async function batchAnalyzeEmails(module: string, recordId: number) {
  return apiClient.post<{ message: string; analyzed_count: number }>('/ai/sentiment/batch-analyze-emails', {
    module,
    record_id: recordId
  });
}
