const _inFlight = new Map();
const _responseCache = new Map();
const _makeKey = (url, data) => `${url}::${JSON.stringify(data||{})}`;

/**
 * Cached POST request to prevent redundant network calls
 */
export function cachedPost(url, data, ttlMs = 1500) {
    const key = _makeKey(url, data);
    const now = Date.now();
    const cached = _responseCache.get(key);

    if (cached && cached.expiresAt > now) {
        return Promise.resolve(cached.data);
    }

    if (_inFlight.has(key)) {
        return _inFlight.get(key);
    }

    const p = window.axios.post(url, data).then(resp => {
        _responseCache.set(key, { expiresAt: now + ttlMs, data: resp });
        _inFlight.delete(key);
        return resp;
    }).catch(err => {
        _inFlight.delete(key);
        throw err;
    });

    _inFlight.set(key, p);
    return p;
}
