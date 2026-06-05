const pendingRequestIds = new Set();

/**
 * Register a requestId as being initiated by the current client instance.
 * @param {string} requestId
 */
export function registerMyRequest(requestId) {
    if (!requestId) return;
    pendingRequestIds.add(requestId);

    // Automatically cleanup after 30 seconds
    setTimeout(() => {
        pendingRequestIds.delete(requestId);
    }, 30000);
}

/**
 * Check if an actorId belongs to the current user.
 * @param {string} actorId
 * @returns {boolean}
 */
export function isMyActor(actorId) {
    if (!actorId) return false;
    const myActorId = (localStorage.getItem('cpr_actor_uuid') || '').replace(/^Actor\./, '');
    return String(actorId).replace(/^Actor\./, '') === myActorId;
}

/**
 * Check if a requestId was initiated by the current client instance.
 * @param {string} requestId
 * @returns {boolean}
 */
export function isMyRequest(requestId) {
    if (!requestId) return false; // Unsolicited updates are for specifics. Like weapon drops etc
    return pendingRequestIds.has(requestId);
}

/**
 * Intercept Axios responses to automatically register any requestId returned in the payload.
 */
export function setupAxiosInterceptors() {
    if (window.axios && !window._cpr_interceptors_setup) {
        window.axios.interceptors.response.use(
            (response) => {
                const data = response.data;
                const requestId = data.requestId || (data.data && data.data.requestId);
                if (requestId) {
                    registerMyRequest(requestId);
                }
                return response;
            },
            (error) => Promise.reject(error)
        );
        window._cpr_interceptors_setup = true;
    }
}
