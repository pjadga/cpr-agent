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
        try {
            const response = await window.axios.get("/api/getbaseurl");
            const url = response.data.replace(/\/$/, '');
            baseUrl.value = url;
            localStorage.setItem('cpr_base_url', baseUrl.value);
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
