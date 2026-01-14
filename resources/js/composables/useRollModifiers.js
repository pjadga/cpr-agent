import { ref } from 'vue';
import { debounce, cachedPost, isDeepEqual } from '../utils/helpers';

/**
 * Composable for fetching and managing roll modifiers (bonuses/penalties)
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
            if (!controlState.value.selectedWeapon) {
                rollModsError.value = 'No weapon selected. Choose a weapon in Control > Select Weapon.';
                rollModsData.value = null;
                return;
            }

            const response = await cachedPost('/getrollmodifiers', {
                selectedWeapon: controlState.value.selectedWeapon
            }, 2500);

            const data = response?.data?.data;
            const payload = data?.result ? data.result : data;

            // Only update if the payload actually changed
            if (!isDeepEqual(rollModsData.value || {}, payload || {})) {
                rollModsData.value = payload;
            }

            if (rollModsData.value?.error) {
                rollModsError.value = rollModsData.value.error;
                rollModsData.value = null;
            }
        } catch (e) {
            console.error('Error fetching roll modifiers', e);
            rollModsError.value = 'Unable to fetch roll modifiers.';
        } finally {
            rollModsLoading.value = false;
        }
    };

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
        closeRollPanel
    };
}
