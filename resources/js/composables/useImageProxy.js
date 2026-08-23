import { ref } from 'vue';

/**
 * Composable for constructing image URLs and handling proxy logic.
 */
export function useImageProxy() {
    const baseUrl = ref(localStorage.getItem('cpr_base_url') || '');

    /**
     * Fetch base URL for images from the server.
     */
    const fetchBaseUrl = async () => {
        // In the Foundry embed there is no authenticated session and
        // /api/getbaseurl is short-circuited to {} by the postMessage adapter.
        // The image base is the parent (Foundry) origin, which initEmbed()
        // seeds into cpr_base_url — read that instead of calling the server.
        if (window.__CPR_EMBED) {
            baseUrl.value = localStorage.getItem('cpr_base_url') || baseUrl.value;
            return;
        }
        try {
            const response = await window.axios.get("/api/getbaseurl");
            // getFoundryUrl() returns a string (or null); guard so a non-string
            // response (e.g. an empty-object degrade) never throws on .replace.
            const url = typeof response.data === 'string' ? response.data.replace(/\/$/, '') : '';
            if (url) {
                baseUrl.value = url;
                localStorage.setItem('cpr_base_url', baseUrl.value);
            }
        } catch (err) {
            console.error("Error fetching base URL:", err);
        }
    };

    /**
     * Construct full image URL, handling proxy for external hosts.
     */
    const getImageUrl = (path) => {
        if (!path) return '/systems/cyberpunk-red-core/icons/compendium/default/Default_Program.svg';

        let cleanPath = path;
        if (path.startsWith('systems/cyberpunk-red-core/')) {
            cleanPath = path.replace('systems/cyberpunk-red-core/', '/systems/cyberpunk-red-core/');
        }

        let fullUrl = cleanPath;
        if (cleanPath.startsWith('/')) {
            if (baseUrl.value) {
                fullUrl = baseUrl.value + cleanPath;
            }
        } else if (baseUrl.value && !cleanPath.includes('://')) {
            fullUrl = baseUrl.value + "/" + cleanPath;
        }

        // Proxy ngrok URLs to avoid mixed content issues
        if (fullUrl.includes('ngrok-free.dev') || fullUrl.includes('ngrok.dev')) {
            return `/api/proxy-image?url=${encodeURIComponent(fullUrl)}`;
        }

        return fullUrl;
    };

    return { baseUrl, fetchBaseUrl, getImageUrl };
}
