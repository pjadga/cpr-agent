import {ref} from 'vue';
import {debounce, isDeepEqual} from '../utils/helpers';
import {useEchoListener} from '../utils/listener';

/**
 * Composable for fetching and managing die rolls and modifiers
 */
export function useRollModifiers(controlState) {
    const showRollPanel = ref(false);
    const rollModsLoading = ref(false);
    const rollModsError = ref(null);
    const rollModsData = ref(null);

    let fetchTimeout = null;

    /**
     * Fetch roll modifiers for the currently selected weapon
     */
    const fetchRollModifiers = async () => {
        rollModsError.value = null;
        rollModsLoading.value = true;
        if (fetchTimeout) clearTimeout(fetchTimeout);

        // Safety timeout to ensure loading doesn't get stuck if Foundry doesn't respond
        fetchTimeout = setTimeout(() => {
            if (rollModsLoading.value) {
                console.warn('Foundry response timeout for roll modifiers.');
                rollModsLoading.value = false;
                if (!rollModsData.value) {
                    rollModsData.value = {injuries: [], totalPenalty: 0, finalModifier: 0};
                }
            }
        }, 10000);

        try {
            // We don't block if no weapon is selected anymore, we want to see penalties
            await window.axios.post('/api/getrollmodifiers', {
                selectedWeapon: controlState.value.selectedWeapon
            });
            // Result will come back via Echo FoundryResponse
        } catch (e) {
            if (fetchTimeout) clearTimeout(fetchTimeout);
            console.error('Error fetching roll modifiers', e);
            rollModsError.value = 'Unable to fetch roll modifiers.';
            rollModsLoading.value = false;
        }
    };

    const handleFoundryResponse = (e) => {
        const payload = e.payload || e;
        if (payload.type === 'ROLL_MODIFIERS_DATA') {
            if (fetchTimeout) clearTimeout(fetchTimeout);
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
