import { ref } from 'vue';
import { debounce, isDeepEqual } from '../utils/helpers';
import { cachedPost } from '../utils/api';
import { useEchoListener } from '../utils/listener';
/**
 * Composable for fetching and managing die rolls and modifiers
 */
export function useRollModifiers(controlState) {
    const showRollPanel = ref(false);
    const rollModsLoading = ref(false);
    const rollModsError = ref(null);
    const rollModsData = ref(null);

    /**
     * Fetch roll modifiers for the currently selected weapon
     */
    const fetchRollModifiers = async () => {
        rollModsError.value = null;
        rollModsLoading.value = true;

        try {
            // We don't block if no weapon is selected anymore, we want to see penalties
            await window.axios.post('/getrollmodifiers', {
                selectedWeapon: controlState.value.selectedWeapon
            });
            // Result will come back via Echo FoundryResponse
        } catch (e) {
            console.error('Error fetching roll modifiers', e);
            rollModsError.value = 'Unable to fetch roll modifiers.';
            rollModsLoading.value = false;
        }
    };

    const handleFoundryResponse = (e) => {
        const payload = e.payload || e;
        if (payload.type === 'ROLL_MODIFIERS_DATA') {
            const result = payload.result;
            if (!isDeepEqual(rollModsData.value || {}, result || {})) {
                rollModsData.value = result;
            }
            rollModsLoading.value = false;
        }
    };

    useEchoListener(handleFoundryResponse);

    const debouncedFetchRollModifiers = debounce(fetchRollModifiers, 250);

    const openRollPanel = async () => {
        showRollPanel.value = true;
        await fetchRollModifiers();
    };

    const closeRollPanel = () => {
        showRollPanel.value = false;
    };

    return {
        showRollPanel,
        rollModsLoading,
        rollModsError,
        rollModsData,
        debouncedFetchRollModifiers,
        openRollPanel,
        closeRollPanel,
        fetchRollModifiers
    };
}
