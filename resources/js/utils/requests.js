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
 * Check if a requestId was initiated by the current client instance.
 * @param {string} requestId
 * @returns {boolean}
 */
export function isMyRequest(requestId) {
    if (!requestId) return true; // Unsolicited updates are for everyone
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
                console.log(`[Agent] Response: ${requestId}`);
                if (requestId) {
                    console.log(`[Agent] Incoming Request: ${requestId}`);
                    registerMyRequest(requestId);
                }
                return response;
            },
            (error) => Promise.reject(error)
        );
        window._cpr_interceptors_setup = true;
    }
}
