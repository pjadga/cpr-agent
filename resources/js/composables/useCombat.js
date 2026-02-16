import { ref, watch, onMounted, onUnmounted } from 'vue';
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
            await window.axios.post('/getvisibletokens', {
                selectedWeapon: controlState.value.selectedWeapon
            });
        } catch (e) {
            console.error('Error fetching DV', e);
            dvError.value = 'Unable to fetch DV.';
            dvLoading.value = false;
        }
    };

    const debouncedFetchDV = debounce(fetchDVForSelectedWeapon, 250);

    /**
     * Get list of visible tokens (enemies) from the server
     */
    const getVisibleTokens = () => {
        window.axios.post("/listvisibletokens", {}).catch(err => console.error('Error fetching visible tokens', err));
    };

    const handleFoundryResponse = (e) => {
        const payload = e.payload || e;
        console.log('useCombat: Received FoundryResponse', payload);

        if (payload.type === 'DV_INFO_DATA') {
            dvInfo.value = payload.result?.dv;
            dvLoading.value = false;
        } else if (payload.type === 'VISIBLE_TOKENS_DATA') {
            const list = payload.tokens || [];

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
                // Ensure list remains sorted by distance even if IDs didn't change but positions did
                mockEnemies.value.sort((a, b) => a.distanceM - b.distanceM);
            }

            if (controlState.value.selectedTarget && controlState.value.selectedWeapon) {
                debouncedFetchDV();
            }
        }
    };

    onMounted(() => {
        if (window.Echo) {
            window.Echo.private('cpr-agent')
                .listen('.FoundryResponse', handleFoundryResponse);
        }
    });

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

        window.axios.post("/attackroll", controlState.value).then((response) => {
            debouncedFetchCharacterData();
            if (response.data && response.data.data) {
                let rollData = response.data.data;
                // Handle list of rolls from getRolls(limit: 5)
                if (rollData.data && Array.isArray(rollData.data) && rollData.data.length > 0) {
                    const lastRoll = rollData.data[0];
                    throwError(`Roll Result: ${lastRoll.rollTotal}`);
                } else if (typeof rollData === 'number') {
                    throwError(`Roll Result: ${rollData}`);
                }
            }
        });
    };

    /**
     * Execute a damage roll
     */
    const rollDamage = () => {
        window.axios.post("/damageroll", controlState.value).then((response) => {
            debouncedFetchCharacterData();
            if (response.data && response.data.data) {
                let rollData = response.data.data;
                // Handle list of rolls from getRolls(limit: 5)
                if (rollData.data && Array.isArray(rollData.data) && rollData.data.length > 0) {
                    const lastRoll = rollData.data[0];
                    throwError(`Roll Result: ${lastRoll.rollTotal}`);
                } else if (typeof rollData === 'number') {
                    throwError(`Roll Result: ${rollData}`);
                }
            }
        });
    };

    /**
     * Check if the selected weapon needs reloading
     */
    const checkReloadRequired = () => {
        const weapon = controlState.value.selectedWeapon;
        if (weapon?.system?.magazine && weapon.system.magazine.max > 0) {
            controlState.value.reloadRequired = weapon.system.magazine.value === 0;
        } else {
            controlState.value.reloadRequired = false;
        }
    };

    /**
     * Reload the selected weapon
     */
    const reloadWeapon = (refreshAll) => {
        if (controlState.value.selectedWeapon) {
            const payload = {
                selectedWeapon: controlState.value.selectedWeapon
            };
            window.axios.post("/reloadweapon", payload).then(() => {
                // Refresh character data and combat data after 2 seconds
                setTimeout(() => {
                    if (refreshAll) {
                        refreshAll();
                    } else {
                        fetchCharacterData();
                        getVisibleTokens();
                        if (controlState.value.selectedWeapon && controlState.value.selectedTarget) {
                            fetchDVForSelectedWeapon();
                        }
                    }
                }, 2000);
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
        if (window.Echo) {
            window.Echo.private('cpr-agent')
                .stopListening('.FoundryResponse', handleFoundryResponse);
        }
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
        targetToken,
        checkReloadRequired,
        reloadWeapon,
        fetchDVForSelectedWeapon
    };
}
