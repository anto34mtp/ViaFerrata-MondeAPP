import axios from 'axios';

const BASE_URL = 'https://viaferrata-monde.fr/mobile-api';

export const apiClient = axios.create({
  baseURL: BASE_URL,
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Unwrap the { ok: true, data: ... } envelope returned by every mok() call
apiClient.interceptors.response.use((response) => {
  const body = response.data;
  if (body && typeof body === 'object' && 'ok' in body && 'data' in body) {
    response.data = body.data;
  }
  return response;
});

// ─── Via types ────────────────────────────────────────────────────────────────

export interface Via {
  id: number;
  slug: string;
  name: string;
  location?: string;
  department_code?: string;
  department_name?: string;
  country?: string;
  difficulty?: number;
  duration_min?: number;
  duration_max?: number;
  length_m?: number;
  elevation_m?: number;
  altitude_max_m?: number;
  gps_lat?: number;
  gps_lng?: number;
  opening_status?: 'ouvert' | 'ferme' | 'ferme_definitif';
  description?: string;
  pricing_info?: string;
  tourism_office?: string;
  avg_rating_general?: number;
  avg_rating_beauty?: number;
  avg_rating_difficulty?: number;
  ratings_count?: number;
  image_url?: string;
  photos?: Photo[];
  comments?: Comment[];
  ratings?: Rating[];
}

export interface Photo {
  id: number;
  url: string;
  caption?: string;
}

export interface Comment {
  id: number;
  content: string;
  author_name?: string;
  created_at: string;
}

export interface Rating {
  id: number;
  rating_general: number;
  rating_beauty: number;
  rating_difficulty: number;
  author_name?: string;
  created_at: string;
}

export interface MapPoint {
  id: number;
  slug: string;
  name: string;
  gps_lat: number;
  gps_lng: number;
  difficulty?: number;
  country?: string;
}

// ─── Favorites ────────────────────────────────────────────────────────────────

export interface Favorite {
  id: number;
  via_id: number;
  status: 'to_do' | 'done';
  created_at: string;
  via?: Via;
}

// ─── Logbook ──────────────────────────────────────────────────────────────────

export interface LogbookEntry {
  id: number;
  via_id: number;
  done_date: string;
  conditions?: string;
  companion?: string;
  notes?: string;
  via?: Via;
}

// ─── Trips ────────────────────────────────────────────────────────────────────

export interface Trip {
  id: number;
  name: string;
  description?: string;
  start_date?: string;
  end_date?: string;
  nb_days?: number;
  owner_username?: string;
  vias_by_day?: Record<string, Via[]>;
}

// ─── Dashboard ────────────────────────────────────────────────────────────────

export interface DashboardData {
  stats: {
    favorites_count?: number;
    to_do_count?: number;
    done_count?: number;
    logbook_count?: number;
    logbook_this_year?: number;
    trips_count?: number;
  };
  recent_favorites: Favorite[];
  recent_logbook: LogbookEntry[];
  trips: Trip[];
}

// ─── Stats ────────────────────────────────────────────────────────────────────

export interface Stats {
  total_vias: number;
  countries: number; // nombre de pays distincts
}

// ─── API Functions ────────────────────────────────────────────────────────────

// Vias
export const getVias = (params: {
  page?: number;
  limit?: number;
  search?: string;
  department_code?: string;
  country?: string;
  difficulty_min?: string | number;
  difficulty_max?: string | number;
  order_by?: string;
}) => apiClient.get<{data: Via[]; total: number; page: number; limit: number}>('/vias', {params});

export const getTopRatedVias = (limit = 20) =>
  apiClient.get<Via[]>('/vias/top-rated', {params: {limit}});

export const getMapVias = () => apiClient.get<MapPoint[]>('/vias/map');

export const getViaDetail = (slug: string) =>
  apiClient.get<Via>(`/vias/${slug}`);

export const rateVia = (
  slug: string,
  data: {
    rating_general: number;
    rating_beauty: number;
    rating_difficulty: number;
  },
) => apiClient.post(`/vias/${slug}/rate`, data);

export const commentVia = (
  slug: string,
  data: {content: string; author_name?: string},
) => apiClient.post(`/vias/${slug}/comment`, data);

// Auth
export const login = (data: {login: string; password: string}) =>
  apiClient.post<{token: string; user: {id: number; username: string; email: string}}>('/auth/login', data);

export const register = (data: {
  username: string;
  email: string;
  password: string;
}) =>
  apiClient.post<{token: string; user: {id: number; username: string; email: string}}>('/auth/register', data);

export const getMe = () =>
  apiClient.get<{id: number; username: string; email: string}>('/auth/me');

// Favorites
export const getFavorites = (status?: string) =>
  apiClient.get<Favorite[]>('/favorites', {params: status ? {status} : {}});

export const addFavorite = (data: {via_id: number; status: 'to_do' | 'done'}) =>
  apiClient.post<Favorite>('/favorites', data);

export const deleteFavorite = (via_id: number) =>
  apiClient.delete(`/favorites/${via_id}`);

// Logbook
export const getLogbook = () => apiClient.get<LogbookEntry[]>('/logbook');

export const addLogbookEntry = (data: {
  via_id: number;
  done_date: string;
  conditions?: string;
  companion?: string;
  notes?: string;
}) => apiClient.post<LogbookEntry>('/logbook', data);

export const deleteLogbookEntry = (id: number) =>
  apiClient.delete(`/logbook/${id}`);

// Trips
export const getTrips = () =>
  apiClient.get<{my_trips: Trip[]; shared_trips: Trip[]}>('/trips');

export const createTrip = (data: {
  name: string;
  description?: string;
  start_date?: string;
  end_date?: string;
  nb_days?: number;
}) => apiClient.post<Trip>('/trips', data);

export const getTripDetail = (id: number) =>
  apiClient.get<Trip>(`/trips/${id}`);

export const updateTrip = (
  id: number,
  data: Partial<{
    name: string;
    description: string;
    start_date: string;
    end_date: string;
    nb_days: number;
  }>,
) => apiClient.patch<Trip>(`/trips/${id}`, data);

export const deleteTrip = (id: number) => apiClient.delete(`/trips/${id}`);

export const addViaToTrip = (
  id: number,
  data: {via_id: number; day_number: number; notes?: string},
) => apiClient.post(`/trips/${id}/vias`, data);

export const removeViaFromTrip = (id: number, via_id: number) =>
  apiClient.delete(`/trips/${id}/vias/${via_id}`);

// Dashboard
export const getDashboard = () =>
  apiClient.get<DashboardData>('/dashboard');

// Stats
export const getStats = () => apiClient.get<Stats>('/stats');
