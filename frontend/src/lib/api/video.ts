import { apiClient } from './client';

// Types
export interface VideoProvider {
  id: number;
  name: string;
  provider: 'zoom' | 'teams' | 'google_meet' | 'webex';
  is_active: boolean;
  is_verified: boolean;
  settings: Record<string, unknown> | null;
  scopes: string[] | null;
  last_synced_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface VideoMeeting {
  id: number;
  provider_id: number;
  external_meeting_id: string | null;
  host_id: number;
  title: string;
  description: string | null;
  status: 'scheduled' | 'started' | 'ended' | 'canceled';
  scheduled_at: string;
  started_at: string | null;
  ended_at: string | null;
  duration_minutes: number;
  actual_duration_seconds: number | null;
  join_url: string | null;
  host_url: string | null;
  waiting_room_enabled: boolean;
  recording_enabled: boolean;
  recording_auto_start: boolean;
  recording_url: string | null;
  recording_status: string | null;
  meeting_type: 'instant' | 'scheduled' | 'recurring';
  recurrence_type: 'daily' | 'weekly' | 'monthly' | null;
  recurrence_settings: Record<string, unknown> | null;
  deal_id: number | null;
  deal_module: string | null;
  custom_fields: Record<string, unknown> | null;
  provider?: VideoProvider;
  host?: { id: number; name: string; email: string };
  participants?: VideoMeetingParticipant[];
  recordings?: VideoMeetingRecording[];
  created_at: string;
  updated_at: string;
}

export interface VideoMeetingParticipant {
  id: number;
  meeting_id: number;
  user_id: number | null;
  email: string;
  name: string;
  role: 'host' | 'co-host' | 'attendee';
  status: 'invited' | 'joined' | 'left' | 'no_show';
  joined_at: string | null;
  left_at: string | null;
  duration_seconds: number | null;
  device_type: string | null;
  audio_enabled: boolean;
  video_enabled: boolean;
  screen_shared: boolean;
  attentiveness_score: number | null;
  user?: { id: number; name: string; email: string };
  created_at: string;
  updated_at: string;
}

export interface VideoMeetingRecording {
  id: number;
  meeting_id: number;
  external_recording_id: string | null;
  type: 'video' | 'audio' | 'transcript' | 'chat';
  status: 'processing' | 'completed' | 'failed';
  file_url: string | null;
  download_url: string | null;
  play_url: string | null;
  file_size: number | null;
  duration_seconds: number | null;
  format: string | null;
  recording_start: string | null;
  recording_end: string | null;
  expires_at: string | null;
  transcript_text: string | null;
  transcript_segments: Array<{
    start: number;
    end: number;
    speaker: string;
    text: string;
  }> | null;
  meeting?: VideoMeeting;
  created_at: string;
  updated_at: string;
}

export interface MeetingStats {
  total_meetings: number;
  completed_meetings: number;
  canceled_meetings: number;
  completion_rate: number;
  total_duration_hours: number;
  avg_duration_minutes: number;
  avg_participants: number;
}

interface ApiResponse<T> {
  data: T;
  message?: string;
}

interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// Provider API
export const videoProviderApi = {
  list: async (): Promise<VideoProvider[]> => {
    const response = await apiClient.get<ApiResponse<VideoProvider[]>>('/video/providers');
    return response.data;
  },

  get: async (id: number): Promise<VideoProvider> => {
    const response = await apiClient.get<ApiResponse<VideoProvider>>(`/video/providers/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    provider: VideoProvider['provider'];
    api_key?: string;
    api_secret?: string;
    client_id?: string;
    client_secret?: string;
    webhook_secret?: string;
    settings?: Record<string, unknown>;
    scopes?: string[];
  }): Promise<VideoProvider> => {
    const response = await apiClient.post<ApiResponse<VideoProvider>>('/video/providers', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    api_key: string;
    api_secret: string;
    client_id: string;
    client_secret: string;
    webhook_secret: string;
    settings: Record<string, unknown>;
    scopes: string[];
  }>): Promise<VideoProvider> => {
    const response = await apiClient.put<ApiResponse<VideoProvider>>(`/video/providers/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/video/providers/${id}`);
  },

  verify: async (id: number): Promise<{ verified: boolean; message: string }> => {
    const response = await apiClient.post<{ verified: boolean; message: string; data: VideoProvider }>(
      `/video/providers/${id}/verify`
    );
    return { verified: response.verified, message: response.message || '' };
  },

  toggleActive: async (id: number): Promise<VideoProvider> => {
    const response = await apiClient.post<ApiResponse<VideoProvider>>(`/video/providers/${id}/toggle-active`);
    return response.data;
  },

  getOAuthUrl: async (id: number): Promise<string> => {
    const response = await apiClient.get<{ oauth_url: string }>(`/video/providers/${id}/oauth-url`);
    return response.oauth_url;
  },
};

// Meeting API
export const videoMeetingApi = {
  list: async (params?: {
    status?: VideoMeeting['status'];
    host_id?: number;
    start_date?: string;
    end_date?: string;
    my_meetings?: boolean;
    deal_id?: number;
    deal_module?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<VideoMeeting>> => {
    const response = await apiClient.get<PaginatedResponse<VideoMeeting>>('/video/meetings', { params });
    return response;
  },

  get: async (id: number): Promise<VideoMeeting> => {
    const response = await apiClient.get<ApiResponse<VideoMeeting>>(`/video/meetings/${id}`);
    return response.data;
  },

  create: async (data: {
    provider_id: number;
    title: string;
    description?: string;
    scheduled_at: string;
    duration_minutes?: number;
    waiting_room_enabled?: boolean;
    recording_enabled?: boolean;
    recording_auto_start?: boolean;
    meeting_type?: VideoMeeting['meeting_type'];
    recurrence_type?: VideoMeeting['recurrence_type'];
    recurrence_settings?: Record<string, unknown>;
    deal_id?: number;
    deal_module?: string;
    participants?: Array<{
      email: string;
      name?: string;
      role?: 'attendee' | 'co-host';
    }>;
    custom_fields?: Record<string, unknown>;
  }): Promise<VideoMeeting> => {
    const response = await apiClient.post<ApiResponse<VideoMeeting>>('/video/meetings', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    title: string;
    description: string;
    scheduled_at: string;
    duration_minutes: number;
    waiting_room_enabled: boolean;
    recording_enabled: boolean;
    recording_auto_start: boolean;
  }>): Promise<VideoMeeting> => {
    const response = await apiClient.put<ApiResponse<VideoMeeting>>(`/video/meetings/${id}`, data);
    return response.data;
  },

  cancel: async (id: number): Promise<VideoMeeting> => {
    const response = await apiClient.post<ApiResponse<VideoMeeting>>(`/video/meetings/${id}/cancel`);
    return response.data;
  },

  end: async (id: number): Promise<VideoMeeting> => {
    const response = await apiClient.post<ApiResponse<VideoMeeting>>(`/video/meetings/${id}/end`);
    return response.data;
  },

  upcoming: async (limit?: number): Promise<VideoMeeting[]> => {
    const response = await apiClient.get<ApiResponse<VideoMeeting[]>>('/video/meetings/upcoming', {
      params: { limit },
    });
    return response.data;
  },

  stats: async (params?: {
    start_date?: string;
    end_date?: string;
  }): Promise<MeetingStats> => {
    const response = await apiClient.get<ApiResponse<MeetingStats>>('/video/meetings/stats', { params });
    return response.data;
  },

  syncRecordings: async (id: number): Promise<VideoMeeting> => {
    const response = await apiClient.post<ApiResponse<VideoMeeting>>(`/video/meetings/${id}/sync-recordings`);
    return response.data;
  },

  syncParticipants: async (id: number): Promise<VideoMeeting> => {
    const response = await apiClient.post<ApiResponse<VideoMeeting>>(`/video/meetings/${id}/sync-participants`);
    return response.data;
  },
};

// Participant API
export const videoParticipantApi = {
  list: async (meetingId: number): Promise<VideoMeetingParticipant[]> => {
    const response = await apiClient.get<ApiResponse<VideoMeetingParticipant[]>>(
      `/video/meetings/${meetingId}/participants`
    );
    return response.data;
  },

  add: async (meetingId: number, data: {
    email: string;
    name?: string;
    first_name?: string;
    last_name?: string;
    role?: 'attendee' | 'co-host';
  }): Promise<VideoMeetingParticipant> => {
    const response = await apiClient.post<ApiResponse<VideoMeetingParticipant>>(
      `/video/meetings/${meetingId}/participants`,
      data
    );
    return response.data;
  },

  bulkAdd: async (meetingId: number, participants: Array<{
    email: string;
    name?: string;
    role?: 'attendee' | 'co-host';
  }>): Promise<{
    added: VideoMeetingParticipant[];
    failed: Array<{ email: string; error: string }>;
  }> => {
    const response = await apiClient.post<{
      data: {
        added: VideoMeetingParticipant[];
        failed: Array<{ email: string; error: string }>;
      };
    }>(`/video/meetings/${meetingId}/participants/bulk`, { participants });
    return response.data;
  },

  remove: async (meetingId: number, participantId: number): Promise<void> => {
    await apiClient.delete(`/video/meetings/${meetingId}/participants/${participantId}`);
  },
};

// Recording API
export const videoRecordingApi = {
  list: async (meetingId: number): Promise<VideoMeetingRecording[]> => {
    const response = await apiClient.get<ApiResponse<VideoMeetingRecording[]>>(
      `/video/meetings/${meetingId}/recordings`
    );
    return response.data;
  },

  get: async (meetingId: number, recordingId: number): Promise<VideoMeetingRecording> => {
    const response = await apiClient.get<ApiResponse<VideoMeetingRecording>>(
      `/video/meetings/${meetingId}/recordings/${recordingId}`
    );
    return response.data;
  },

  delete: async (meetingId: number, recordingId: number): Promise<void> => {
    await apiClient.delete(`/video/meetings/${meetingId}/recordings/${recordingId}`);
  },

  getTranscript: async (meetingId: number, recordingId: number): Promise<{
    text: string | null;
    segments: Array<{
      start: number;
      end: number;
      speaker: string;
      text: string;
    }> | null;
  }> => {
    const response = await apiClient.get<{
      data: {
        text: string | null;
        segments: Array<{
          start: number;
          end: number;
          speaker: string;
          text: string;
        }> | null;
      };
    }>(`/video/meetings/${meetingId}/recordings/${recordingId}/transcript`);
    return response.data;
  },

  listAll: async (params?: {
    type?: VideoMeetingRecording['type'];
    status?: VideoMeetingRecording['status'];
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<VideoMeetingRecording>> => {
    const response = await apiClient.get<PaginatedResponse<VideoMeetingRecording>>('/video/recordings', { params });
    return response;
  },
};

// Helper functions
export function formatDuration(seconds: number | null): string {
  if (!seconds) return '0:00';

  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;

  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }

  return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

export function formatFileSize(bytes: number | null): string {
  if (!bytes) return '0 B';

  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let size = bytes;
  let unit = 0;

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024;
    unit++;
  }

  return `${size.toFixed(2)} ${units[unit]}`;
}

export function getMeetingStatusColor(status: VideoMeeting['status']): string {
  switch (status) {
    case 'scheduled':
      return 'text-blue-600 bg-blue-100';
    case 'started':
      return 'text-green-600 bg-green-100';
    case 'ended':
      return 'text-gray-600 bg-gray-100';
    case 'canceled':
      return 'text-red-600 bg-red-100';
    default:
      return 'text-gray-600 bg-gray-100';
  }
}

export function getParticipantStatusColor(status: VideoMeetingParticipant['status']): string {
  switch (status) {
    case 'invited':
      return 'text-yellow-600 bg-yellow-100';
    case 'joined':
      return 'text-green-600 bg-green-100';
    case 'left':
      return 'text-gray-600 bg-gray-100';
    case 'no_show':
      return 'text-red-600 bg-red-100';
    default:
      return 'text-gray-600 bg-gray-100';
  }
}

export function getProviderIcon(provider: VideoProvider['provider']): string {
  switch (provider) {
    case 'zoom':
      return '📹';
    case 'teams':
      return '👥';
    case 'google_meet':
      return '🎥';
    case 'webex':
      return '💼';
    default:
      return '📺';
  }
}
