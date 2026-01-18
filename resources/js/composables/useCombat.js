import { ref, watch, onUnmounted } from 'vue';
import { debounce } from '../utils/helpers';
import { cachedPost } from '../utils/api';
/**
 * Composable for combat-related logic (movement, target selection, rolls)
 */
export function useCombat(controlState, charData, fetchCharacterData, throwError) {
    const mockEnemies = ref([]);
    const dvInfo = ref(null);
    const dvLoading = ref(false);
    const dvError = ref(null);
    let moveRefreshTimer = null;

    /**
     * Fetch Difficulty Value (DV) for the selected weapon and target
     */
    const fetchDVForSelectedWeapon = async () => {
        dvError.value = null;
        dvInfo.value = null;
        if (!controlState.value.selectedWeapon || !controlState.value.selectedTarget) return;

        try {
            dvLoading.value = true;
            const response = await cachedPost('/getvisibletokens', {
                selectedWeapon: controlState.value.selectedWeapon
            }, 1000);
            dvInfo.value = response?.data?.data?.result;
        } catch (e) {
            console.error('Error fetching DV', e);
            dvError.value = 'Unable to fetch DV.';
        } finally {
            dvLoading.value = false;
        }
    };

    const debouncedFetchDV = debounce(fetchDVForSelectedWeapon, 250);

    /**
     * Get list of visible tokens (enemies) from the server
     */
    const getVisibleTokens = () => {
        cachedPost("/listvisibletokens", {}, 1500).then(response => {
            const list = response?.data?.data?.result || [];

            // Update existing list to prevent complete re-render if possible
            const currentIds = (mockEnemies.value || []).map(e => e.id).sort().join(',');
            const newIds = list.map(e => e.id).sort().join(',');

            if (currentIds !== newIds) {
                const prevSelectedId = controlState.value.selectedTarget?.id;
                mockEnemies.value = list;
                if (prevSelectedId) {
                    controlState.value.selectedTarget = list.find(t => t.id === prevSelectedId) || null;
                }
            } else {
                list.forEach(newItem => {
                    const existing = mockEnemies.value.find(e => e.id === newItem.id);
                    if (existing) {
                        if (existing.distanceM !== newItem.distanceM) existing.distanceM = newItem.distanceM;
                        if (existing.name !== newItem.name) existing.name = newItem.name;
                    }
                });
            }

            if (controlState.value.selectedTarget && controlState.value.selectedWeapon) {
                debouncedFetchDV();
            }
        }).catch(err => console.error('Error fetching visible tokens', err));
    };

    const debouncedGetVisibleTokens = debounce(getVisibleTokens, 200);

    const debouncedFetchCharacterData = debounce(fetchCharacterData, 1000);

    /**
     * Move character token
     */
    const moveToken = (direction) => {
        window.axios.post("/movetoken", { direction }).then(() => {
            debouncedFetchCharacterData();
        });
        if (moveRefreshTimer) clearTimeout(moveRefreshTimer);
        moveRefreshTimer = setTimeout(() => {
            getVisibleTokens();
            if (controlState.value.selectedWeapon && controlState.value.selectedTarget) {
                debouncedFetchDV();
            }
        }, 4000);
    };

    /**
     * Target a specific token
     */
    const targetToken = (tokenId) => {
        window.axios.post("/targettokens", { token: tokenId }).then(() => {
            debouncedFetchCharacterData();
        });
    };

    /**
     * Execute an attack roll
     */
    const rollHit = () => {
        if (!controlState.value.selectedTarget) return throwError("No Target Selected");
        if (!controlState.value.selectedWeapon) return throwError("No Weapon Selected");
        if (controlState.value.reloadRequired) return throwError("Out of Ammo!");

        window.axios.post("/attackroll", controlState.value).then(() => {
            debouncedFetchCharacterData();
        });
    };

    /**
     * Execute a damage roll
     */
    const rollDamage = () => {
        window.axios.post("/damageroll", controlState.value).then(() => {
            debouncedFetchCharacterData();
        });
    };

    /**
     * Check if the selected weapon needs reloading
     */
    const checkReloadRequired = () => {
        const weapon = controlState.value.selectedWeapon;
        if (weapon?.system.magazine && weapon.system.magazine.max > 0) {
            controlState.value.reloadRequired = weapon.system.magazine.value === 0;
        } else {
            controlState.value.reloadRequired = false;
        }
    };

    /**
     * Reload the selected weapon
     */
    const reloadWeapon = () => {
        if (controlState.value.selectedWeapon) {
            window.axios.post("/reloadweapon", controlState.value).then(() => {
                debouncedFetchCharacterData();
            });
        }
    };

    watch(() => controlState.value.selectedTarget, (newTarget) => {
        if (newTarget) {
            targetToken(newTarget.id);
            debouncedFetchDV();
        }
    });

    onUnmounted(() => {
        if (moveRefreshTimer) clearTimeout(moveRefreshTimer);
    });

    return {
        mockEnemies,
        dvInfo,
        dvLoading,
        dvError,
        debouncedFetchDV,
        getVisibleTokens,
        debouncedGetVisibleTokens,
        moveToken,
        rollHit,
        rollDamage,
        checkReloadRequired,
        reloadWeapon,
        fetchDVForSelectedWeapon
    };
}
