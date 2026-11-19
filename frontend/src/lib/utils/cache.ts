const PREFIX = 'invitaku_cache_';

interface CachedValue<T> {
  savedAt: number;
  maxAgeMs?: number;
  value: T;
}

/**
 * Lightweight localStorage cache used to make repeated pages feel instant on
 * slow connections. The app still refreshes from the server in the background.
 */
export function readCache<T>(key: string, maxAgeMs = 10 * 60 * 1000): T | null {
  if (typeof localStorage === 'undefined') return null;

  try {
    const raw = localStorage.getItem(PREFIX + key);
    if (!raw) return null;

    const parsed = JSON.parse(raw) as CachedValue<T>;
    if (!parsed || typeof parsed.savedAt !== 'number') return null;
    const age = typeof parsed.maxAgeMs === 'number' ? parsed.maxAgeMs : maxAgeMs;
    if (Date.now() - parsed.savedAt > age) return null;

    return parsed.value;
  } catch {
    return null;
  }
}

export function writeCache<T>(key: string, value: T, maxAgeMs?: number): void {
  if (typeof localStorage === 'undefined') return;

  try {
    const payload: CachedValue<T> = { savedAt: Date.now(), value };
    if (typeof maxAgeMs === 'number') payload.maxAgeMs = maxAgeMs;
    localStorage.setItem(PREFIX + key, JSON.stringify(payload));
  } catch {
    // Storage can be full or blocked; caching is best-effort only.
  }
}
