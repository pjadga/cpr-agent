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

        // If it's an external URL (e.g. pointing to Ngrok), proxy it
        if (fullUrl.includes('ngrok-free.dev') || fullUrl.includes('ngrok.dev')) {
            return `/proxy-image?url=${encodeURIComponent(fullUrl)}`;
        }

        return fullUrl;
    };

    /**
     * Fetch base URL for images
     */
    const fetchBaseUrl = async () => {
//        if (baseUrl.value) return; // Use cached value if available
        try {
            const response = await window.axios.get("/getbaseurl");
            const url = response.data.replace(/\/$/, ''); // Remove trailing slash if any
            baseUrl.value = url;
            localStorage.setItem('cpr_base_url', baseUrl.value);
        } catch (err) {
            console.error("Error fetching base URL:", err);
        }
    };

    /**
     * Fetch character data from the server
     */
    const fetchCharacterData = async (isInitial = false) => {
        try {
            let payload = { "actorUuid": actorUuid };

            if (isInitial) {
                // Initial check: if we don't have actor data saved, we need to fetch it from Foundry
                // First, ensure we have a UUID (should be in localStorage or we'll need to fetch it)
                if (!actorUuid) {
                    const getActors = await window.axios.post("/getactors");
                    if (getActors.data.uuid) {
                        actorUuid = getActors.data.uuid;
                        localStorage.setItem('cpr_actor_uuid', actorUuid);
                    }
                }

                // If we still don't have a UUID, we can't proceed
                if (!actorUuid) {
                    error.value = "No character UUID found. Please check your settings.";
                    loading.value = false;
                    return;
                }

                // Call /actorsheet to see if we already have saved data
                try {
                    const checkResponse = await window.axios.post("/actorsheet", { "actorUuid": actorUuid });
                    charData.value = checkResponse.data;
                    loading.value = false;
                } catch (err) {
                    // If /actorsheet fails (e.g. 400 because no character_data in DB),
                    // then we fetch from /getactor and save it
                    console.log("No saved character data, fetching from Foundry...");
                    const getActorResponse = await window.axios.post("/getactor", { "actorId": actorUuid });

                    if (getActorResponse.data.actor) {
                        // Save the fetched actor data to the DB via /actorsheet
                        const saveResponse = await window.axios.post("/actorsheet", {
                            "actorUuid": actorUuid,
                            "actorData": getActorResponse.data.actor
                        });
                        charData.value = saveResponse.data;
                    } else {
                        throw new Error("Could not fetch actor from Foundry");
                    }
                }
            } else {
                // For regular polling, just get from DB
                const response = await window.axios.post("/actorsheet", payload);

                // Only update if data has actually changed
                if (charData.value && isDeepEqual(charData.value.data, response.data.data)) {
                    if (charData.value.requestId !== response.data.requestId) {
                        charData.value.requestId = response.data.requestId;
                    }
                    return;
                }

                charData.value = response.data;
            }

            if (isInitial) loading.value = false;
            setTimeout(refreshIcons, 50);
        } catch (err) {
            console.error("Fetch Error:", err);
            if (isInitial) {
                if (err.response && err.response.data.error) {
                    error.value = err.response.data.error;
                } else {
                    error.value = err.message || "An error occurred while fetching character data.";
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
