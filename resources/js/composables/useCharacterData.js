import { ref, onMounted, onUnmounted, shallowRef, nextTick } from 'vue';
import { isDeepEqual } from '../utils/helpers';

/**
 * Composable to handle fetching and polling character data
 */
export function useCharacterData() {
    const charData = shallowRef(null);
    const loading = ref(true);
    const error = ref(null);
    const baseUrl = ref('');
    let refreshInterval = null;
    let actorUuid = null;

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
        if (!path) return '';
        if (path.startsWith('http')) return path;
        if (baseUrl.value) return baseUrl.value + "/" + path;

        // Fetch base URL if not yet available
        window.axios.get("/getbaseurl").then(response => {
            baseUrl.value = response.data;
            return baseUrl.value + "/" + path;
        });
    };

    /**
     * Fetch character data from the server
     */
    const fetchCharacterData = async (isInitial = false) => {
        try {
            if (isInitial) {
                const getActors = await window.axios.post("/getactors");
                actorUuid = getActors.data.uuid;
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
        await fetchCharacterData(true);
        // Poll for updates every 30 seconds
        refreshInterval = setInterval(() => fetchCharacterData(), 30000);
    });

    onUnmounted(() => {
        if (refreshInterval) clearInterval(refreshInterval);
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
