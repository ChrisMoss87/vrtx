import { apiClient } from './client';

// Types
export interface CallProvider {
  id: number;
  name: string;
  provider: 'twilio' | 'vonage' | 'ringcentral' | 'aircall';
  phone_number: string | null;
  webhook_url: string | null;
  is_active: boolean;
  is_verified: boolean;
  recording_enabled: boolean;
  transcription_enabled: boolean;
  settings: Record<string, unknown> | null;
  last_synced_at: string | null;
  created_at: string;
}

export interface Call {
  id: number;
  provider_id: number;
  external_call_id: string;
  direction: 'inbound' | 'outbound';
  status: 'initiated' | 'ringing' | 'in_progress' | 'completed' | 'busy' | 'no_answer' | 'canceled' | 'failed' | 'voicemail' | 'on_hold';
  from_number: string;
  to_number: string;
  user_id: number | null;
  contact_id: number | null;
  contact_module: string | null;
  duration_seconds: number | null;
  ring_duration_seconds: number | null;
  started_at: string | null;
  answered_at: string | null;
  ended_at: string | null;
  recording_url: string | null;
  recording_sid: string | null;
  recording_duration_seconds: number | null;
  recording_status: string | null;
  notes: string | null;
  outcome: string | null;
  custom_fields: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  formatted_duration: string;
  has_recording: boolean;
  has_transcription: boolean;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  provider?: CallProvider;
  transcription?: CallTranscription;
}

export interface CallTranscription {
  id: number;
  call_id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  full_text: string | null;
  segments: TranscriptionSegment[] | null;
  language: string | null;
  confidence: number | null;
  provider: string;
  summary: string | null;
  key_points: string[] | null;
  action_items: string[] | null;
  sentiment: 'positive' | 'negative' | 'neutral' | null;
  entities: Record<string, string[]> | null;
  word_count: number | null;
  processed_at: string | null;
  error_message: string | null;
}

export interface TranscriptionSegment {
  start: number;
  end: number;
  text: string;
  speaker: string;
}

export interface CallQueue {
  id: number;
  name: string;
  description: string | null;
  provider_id: number;
  phone_number: string | null;
  routing_strategy: 'round_robin' | 'longest_idle' | 'skills_based' | 'random';
  max_wait_time_seconds: number | null;
  max_queue_size: number | null;
  welcome_message: string | null;
  hold_music_url: string | null;
  voicemail_greeting: string | null;
  voicemail_enabled: boolean;
  business_hours: BusinessHours | null;
  after_hours_message: string | null;
  is_active: boolean;
  online_agent_count: number;
  is_within_business_hours: boolean;
  members: CallQueueMember[];
  provider?: CallProvider;
  stats?: CallQueueStats;
}

export interface BusinessHours {
  [day: string]: {
    enabled: boolean;
    start: string;
    end: string;
  };
}

export interface CallQueueMember {
  id: number;
  queue_id: number;
  user_id: number;
  priority: number;
  is_active: boolean;
  status: 'online' | 'offline' | 'busy' | 'break';
  last_call_at: string | null;
  calls_handled_today: number;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

export interface CallQueueStats {
  total_members: number;
  online_members: number;
  busy_members: number;
  today_calls: number;
  today_answered: number;
  today_missed: number;
  avg_wait_time: number;
  avg_duration: number;
}

export interface CallStats {
  total: number;
  inbound: number;
  outbound: number;
  completed: number;
  missed: number;
  total_duration: number;
  avg_duration: number;
  with_recording: number;
}

export interface PhoneNumber {
  sid: string;
  phone_number: string;
  friendly_name: string;
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
}

// API Response wrapper type
interface ApiResponse<T> {
  data: T;
  message?: string;
}

// Provider API
export const callProviderApi = {
  list: async (): Promise<CallProvider[]> => {
    const response = await apiClient.get<ApiResponse<CallProvider[]>>('/calls/providers');
    return response.data;
  },

  get: async (id: number): Promise<CallProvider> => {
    const response = await apiClient.get<ApiResponse<CallProvider>>(`/calls/providers/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    provider: 'twilio' | 'vonage' | 'ringcentral' | 'aircall';
    api_key?: string;
    api_secret?: string;
    auth_token?: string;
    account_sid?: string;
    phone_number?: string;
    webhook_url?: string;
    recording_enabled?: boolean;
    transcription_enabled?: boolean;
    settings?: Record<string, unknown>;
  }): Promise<CallProvider> => {
    const response = await apiClient.post<ApiResponse<CallProvider>>('/calls/providers', data);
    return response.data;
  },

  update: async (
    id: number,
    data: Partial<{
      name: string;
      api_key: string;
      api_secret: string;
      auth_token: string;
      account_sid: string;
      phone_number: string;
      webhook_url: string;
      recording_enabled: boolean;
      transcription_enabled: boolean;
      settings: Record<string, unknown>;
    }>
  ): Promise<CallProvider> => {
    const response = await apiClient.put<ApiResponse<CallProvider>>(`/calls/providers/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete<void>(`/calls/providers/${id}`);
  },

  verify: async (id: number): Promise<{ balance: string; currency: string; account_status: string }> => {
    const response = await apiClient.post<ApiResponse<{ balance: string; currency: string; account_status: string }>>(`/calls/providers/${id}/verify`);
    return response.data;
  },

  toggleActive: async (id: number): Promise<{ is_active: boolean }> => {
    const response = await apiClient.post<ApiResponse<{ is_active: boolean }>>(`/calls/providers/${id}/toggle-active`);
    return response.data;
  },

  listPhoneNumbers: async (id: number): Promise<PhoneNumber[]> => {
    const response = await apiClient.get<ApiResponse<PhoneNumber[]>>(`/calls/providers/${id}/phone-numbers`);
    return response.data;
  },

  syncPhoneNumber: async (id: number, phone_number: string): Promise<void> => {
    await apiClient.post<void>(`/calls/providers/${id}/sync-phone-number`, { phone_number });
  },
};

// Queue API
export const callQueueApi = {
  list: async (): Promise<CallQueue[]> => {
    const response = await apiClient.get<ApiResponse<CallQueue[]>>('/calls/queues');
    return response.data;
  },

  get: async (id: number): Promise<CallQueue> => {
    const response = await apiClient.get<ApiResponse<CallQueue>>(`/calls/queues/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    description?: string;
    provider_id: number;
    phone_number?: string;
    routing_strategy: 'round_robin' | 'longest_idle' | 'skills_based' | 'random';
    max_wait_time_seconds?: number;
    max_queue_size?: number;
    welcome_message?: string;
    hold_music_url?: string;
    voicemail_greeting?: string;
    voicemail_enabled?: boolean;
    business_hours?: BusinessHours;
    after_hours_message?: string;
  }): Promise<CallQueue> => {
    const response = await apiClient.post<ApiResponse<CallQueue>>('/calls/queues', data);
    return response.data;
  },

  update: async (id: number, data: Partial<Omit<CallQueue, 'id' | 'members' | 'provider' | 'stats'>>): Promise<CallQueue> => {
    const response = await apiClient.put<ApiResponse<CallQueue>>(`/calls/queues/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete<void>(`/calls/queues/${id}`);
  },

  toggleActive: async (id: number): Promise<{ is_active: boolean }> => {
    const response = await apiClient.post<ApiResponse<{ is_active: boolean }>>(`/calls/queues/${id}/toggle-active`);
    return response.data;
  },

  getStats: async (id: number): Promise<CallQueueStats> => {
    const response = await apiClient.get<ApiResponse<CallQueueStats>>(`/calls/queues/${id}/stats`);
    return response.data;
  },

  resetDailyStats: async (id: number): Promise<void> => {
    await apiClient.post<void>(`/calls/queues/${id}/reset-daily-stats`);
  },

  addMember: async (queueId: number, data: { user_id: number; priority?: number }): Promise<CallQueueMember> => {
    const response = await apiClient.post<ApiResponse<CallQueueMember>>(`/calls/queues/${queueId}/members`, data);
    return response.data;
  },

  removeMember: async (queueId: number, userId: number): Promise<void> => {
    await apiClient.delete<void>(`/calls/queues/${queueId}/members/${userId}`);
  },

  updateMember: async (
    queueId: number,
    userId: number,
    data: { priority?: number; is_active?: boolean }
  ): Promise<CallQueueMember> => {
    const response = await apiClient.put<ApiResponse<CallQueueMember>>(`/calls/queues/${queueId}/members/${userId}`, data);
    return response.data;
  },

  setMemberStatus: async (
    queueId: number,
    userId: number,
    status: 'online' | 'offline' | 'busy' | 'break'
  ): Promise<void> => {
    await apiClient.put<void>(`/calls/queues/${queueId}/members/${userId}/status`, { status });
  },

  getMyStatus: async (): Promise<CallQueueMember[]> => {
    const response = await apiClient.get<ApiResponse<CallQueueMember[]>>('/calls/queues/my-status');
    return response.data;
  },

  setMyStatus: async (data: { queue_id?: number; status: 'online' | 'offline' | 'busy' | 'break' }): Promise<void> => {
    await apiClient.put<void>('/calls/queues/my-status', data);
  },
};

// Call API
interface CallListResponse {
  data: Call[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export const callApi = {
  list: async (params?: {
    direction?: 'inbound' | 'outbound';
    status?: string;
    user_id?: number;
    contact_id?: number;
    date_from?: string;
    date_to?: string;
    with_recording?: boolean;
    page?: number;
    per_page?: number;
  }): Promise<CallListResponse> => {
    return apiClient.get<CallListResponse>('/calls', { params });
  },

  get: async (id: number): Promise<Call> => {
    const response = await apiClient.get<ApiResponse<Call>>(`/calls/${id}`);
    return response.data;
  },

  initiate: async (data: {
    provider_id: number;
    to_number: string;
    from_number?: string;
    contact_id?: number;
    contact_module?: string;
    metadata?: Record<string, unknown>;
  }): Promise<{ call_id: number; external_call_id: string }> => {
    const response = await apiClient.post<ApiResponse<{ call_id: number; external_call_id: string }>>('/calls/initiate', data);
    return response.data;
  },

  end: async (id: number): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/end`);
  },

  transfer: async (id: number, to_number: string): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/transfer`, { to_number });
  },

  hold: async (id: number): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/hold`);
  },

  mute: async (id: number, muted: boolean = true): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/mute`, { muted });
  },

  logOutcome: async (
    id: number,
    data: {
      outcome: 'connected' | 'voicemail' | 'no_answer' | 'busy' | 'wrong_number' | 'callback_scheduled' | 'not_interested' | 'qualified' | 'other';
      notes?: string;
    }
  ): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/log-outcome`, data);
  },

  linkContact: async (id: number, contact_id: number, contact_module: string): Promise<void> => {
    await apiClient.post<void>(`/calls/${id}/link-contact`, { contact_id, contact_module });
  },

  transcribe: async (id: number): Promise<CallTranscription> => {
    const response = await apiClient.post<ApiResponse<CallTranscription>>(`/calls/${id}/transcribe`);
    return response.data;
  },

  getTranscription: async (id: number): Promise<CallTranscription> => {
    const response = await apiClient.get<ApiResponse<CallTranscription>>(`/calls/${id}/transcription`);
    return response.data;
  },

  getStats: async (period?: 'today' | 'week' | 'month'): Promise<CallStats> => {
    const response = await apiClient.get<ApiResponse<CallStats>>('/calls/stats', { params: { period } });
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete<void>(`/calls/${id}`);
  },
};

// Helper functions
export function formatDuration(seconds: number | null): string {
  if (!seconds) return '0:00';
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

export function getCallStatusColor(status: Call['status']): string {
  switch (status) {
    case 'completed':
      return 'text-green-600';
    case 'in_progress':
      return 'text-blue-600';
    case 'ringing':
      return 'text-yellow-600';
    case 'busy':
    case 'no_answer':
    case 'canceled':
    case 'failed':
      return 'text-red-600';
    case 'voicemail':
      return 'text-purple-600';
    case 'on_hold':
      return 'text-orange-600';
    default:
      return 'text-gray-600';
  }
}

export function getCallStatusLabel(status: Call['status']): string {
  switch (status) {
    case 'in_progress':
      return 'In Progress';
    case 'no_answer':
      return 'No Answer';
    case 'on_hold':
      return 'On Hold';
    default:
      return status.charAt(0).toUpperCase() + status.slice(1);
  }
}

export function getMemberStatusColor(status: CallQueueMember['status']): string {
  switch (status) {
    case 'online':
      return 'bg-green-500';
    case 'busy':
      return 'bg-red-500';
    case 'break':
      return 'bg-yellow-500';
    default:
      return 'bg-gray-400';
  }
}
