// Difficulty mapping
export const DIFFICULTY_MAP: Record<number, string> = {
  1: 'F',
  2: 'PD',
  3: 'AD',
  4: 'D',
  5: 'TD',
  6: 'ED',
  7: 'EX',
};

export const DIFFICULTY_COLORS: Record<number, string> = {
  1: '#4CAF50',
  2: '#8BC34A',
  3: '#CDDC39',
  4: '#FFC107',
  5: '#FF9800',
  6: '#F44336',
  7: '#9C27B0',
};

export const getDifficultyLabel = (level: number): string =>
  DIFFICULTY_MAP[level] || '?';

export const getDifficultyColor = (level: number): string =>
  DIFFICULTY_COLORS[level] || '#9E9E9E';

// Status colors
export const STATUS_COLORS: Record<string, string> = {
  ouvert: '#2E7D32',
  ferme: '#F57F17',
  ferme_definitif: '#B71C1C',
};

export const getStatusColor = (status?: string): string =>
  status ? STATUS_COLORS[status] || '#9E9E9E' : '#9E9E9E';

// Format duration
export const formatDuration = (
  minH?: number,
  maxH?: number,
): string => {
  if (!minH && !maxH) return '-';
  if (minH && maxH && minH !== maxH) return `${minH}h - ${maxH}h`;
  return `${minH || maxH}h`;
};

// Format date
export const formatDate = (dateStr: string): string => {
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  } catch {
    return dateStr;
  }
};

// Format GPS
export const formatGPS = (lat?: number, lng?: number): string => {
  if (!lat || !lng) return '-';
  return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
};

// Stars rating helper
export const renderStars = (rating?: number, max = 5): string => {
  if (!rating) return '☆☆☆☆☆';
  const filled = Math.round(rating);
  return '★'.repeat(filled) + '☆'.repeat(max - filled);
};
