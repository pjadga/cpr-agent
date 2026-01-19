import { ref, onMounted, onUnmounted, shallowRef, nextTick } from 'vue';
import { isDeepEqual } from '../utils/helpers';

// Shared state to prevent multiple fetches/intervals
const charData = shallowRef(null);
const loading = ref(true);
const error = ref(null);
const baseUrl = ref(localStorage.getItem('cpr_base_url') || '');
let refreshInterval = null;
let actorUuid = localStorage.getItem('cpr_actor_uuid') || null;
let useCount = 0;

/**
 * Composable to handle fetching and polling character data
 */
export function useCharacterData() {
    /**
     * Efficiently refresh Lucide icons
     */
    const refreshIcons = () => {
        if (window.lucide) {
            nextTick(() => window.lucide.createIcons());
        }
    };

    /**
     * Construct full image URL
     */
    const getImageUrl = (path) => {
        if (!path) return '/systems/cyberpunk-red-core/icons/compendium/default/Default_Program.svg';
        if (path.startsWith('http')) return path;

        let cleanPath = path;
        if (path.startsWith('systems/cyberpunk-red-core/')) {
            cleanPath = path.replace('systems/cyberpunk-red-core/', '/systems/cyberpunk-red-core/');
        }

        if (cleanPath.startsWith('/')) {
            if (baseUrl.value) {
                return baseUrl.value + cleanPath;
            }
            return cleanPath;
        }

        if (baseUrl.value) return baseUrl.value + "/" + cleanPath;
        return cleanPath;
    };

    /**
     * Fetch base URL for images
     */
    const fetchBaseUrl = async () => {
        if (baseUrl.value) return; // Use cached value if available
        try {
            const response = await window.axios.get("/getbaseurl");
            const url = response.data.replace(/\/$/, ''); // Remove trailing slash if any
            baseUrl.value = url;
            localStorage.setItem('cpr_base_url', url);
        } catch (err) {
            console.error("Error fetching base URL:", err);
        }
    };

    /**
     * Fetch character data from the server
     */
    const fetchCharacterData = async (isInitial = false) => {
        try {
            if (isInitial && !actorUuid) {
                const getActors = await window.axios.post("/getactors");
                actorUuid = getActors.data.uuid;
                localStorage.setItem('cpr_actor_uuid', actorUuid);
            }
            if (!actorUuid) return;

            const response = await window.axios.post("/actorsheet", { "actorUuid": actorUuid });

            // Only update if data has actually changed
            if (charData.value && isDeepEqual(charData.value.data, response.data.data)) {
                if (charData.value.requestId !== response.data.requestId) {
                    charData.value.requestId = response.data.requestId;
                }
                return;
            }

            charData.value = response.data;
            if (isInitial) loading.value = false;

            setTimeout(refreshIcons, 50);
        } catch (err) {
            console.error("Fetch Error:", err);
            if (isInitial) {
                if (err.response) {
                    error.value = err.response.data.error;
                }
                loading.value = false;
            }
        }
    };

    onMounted(async () => {
        useCount++;
        if (useCount === 1) {
            await fetchBaseUrl();
            await fetchCharacterData(true);
            // Poll for updates every 60 seconds (reduced frequency)
            refreshInterval = setInterval(() => fetchCharacterData(), 60000);
        }
    });

    onUnmounted(() => {
        useCount--;
        if (useCount === 0 && refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    });

    return {
        charData,
        loading,
        error,
        getImageUrl,
        fetchCharacterData,
        refreshIcons
    };
}
