import { ref, watch } from 'vue';
import { debounce, throttle } from '../utils/helpers';
import { cachedPost } from '../utils/api';
import { useEchoListener } from '../utils/listener';
/**
 * Composable for combat-related logic (movement, target selection, rolls)
 */
export function useCombat(controlState, charData, fetchCharacterData, throwError) {
    const mockEnemies = ref([]);
    const dvInfo = ref(null);
    const dvLoading = ref(false);
    const dvError = ref(null);

    /**
     * Fetch Difficulty Value (DV) for the selected weapon and target
     */
    const fetchDVForSelectedWeapon = async () => {
        dvError.value = null;
        dvInfo.value = null;
        if (!controlState.value.selectedWeapon || !controlState.value.selectedTarget) return;

        try {
            dvLoading.value = true;
            const response = await window.axios.post('/getvisibletokens', {
                selectedWeapon: controlState.value.selectedWeapon,
                selectedTarget: controlState.value.selectedTarget
            });

            if (response.data && response.data.success && response.data.data) {
                dvInfo.value = response.data.data.dv;
                dvLoading.value = false;
            }
        } catch (e) {
            console.error('Error fetching DV', e);
            dvError.value = 'Unable to fetch DV.';
            dvLoading.value = false;
        }
    };

    const debouncedFetchDV = debounce(fetchDVForSelectedWeapon, 250);

    /**
     * Update mock enemies list and handle selection
     */
    const updateMockEnemies = (list) => {
        if (!Array.isArray(list)) return;

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
    };

    /**
     * Get list of visible tokens (enemies) from the server
     */
    const getVisibleTokens = () => {
        window.axios.post("/listvisibletokens", {}).then(response => {
            if (response.data && response.data.success && response.data.data) {
                updateMockEnemies(response.data.data);
            }
        }).catch(err => console.error('Error fetching visible tokens', err));
    };

    const handleFoundryResponse = (e) => {
        const payload = e.payload || e;

        if (payload.type === 'DV_INFO_DATA') {
            dvInfo.value = payload.result?.dv;
            dvLoading.value = false;
        } else if (payload.type === 'VISIBLE_TOKENS_DATA') {
            updateMockEnemies(payload.tokens || []);
        }
    };

    useEchoListener(handleFoundryResponse);

    const debouncedGetVisibleTokens = debounce(getVisibleTokens, 1500);
    const throttledGetVisibleTokens = throttle(getVisibleTokens, 15000);

    const debouncedFetchCharacterData = debounce(fetchCharacterData, 1000);

    let lastMoveTime = 0;

    /**
     * Move character token
     */
    const moveToken = (direction) => {
        const weaponId = controlState.value.selectedWeapon?._id;
        const actorId = charData.value?.uuid;
        const tokenId = charData.value?.tokenId;
        const now = Date.now();

        // 20ms throttle to prevent OS-level bounce but feel completely raw
        if (now - lastMoveTime < 20) return;

        lastMoveTime = now;
        window.axios.post("/movetoken", { direction, weaponId, actorId, tokenId });

        // Refresh visible tokens after movement.
        debouncedGetVisibleTokens();
    };

    /**
     * Target a specific token
     */
    const targetToken = (tokenId) => {
        const payload = {
            targetTokenId: tokenId,
            actorId: charData.value?.uuid,
            tokenId: charData.value?.tokenId
        };
        window.axios.post("/targettokens", payload).then(() => {
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

        const payload = {
            ...controlState.value,
            actorId: charData.value?.uuid,
            tokenId: charData.value?.tokenId
        };

        window.axios.post("/attackroll", payload).then((response) => {
            // debouncedFetchCharacterData(); // REMOVED: Rely on SHEET_DELTA for ammo updates to avoid race conditions with stale DB data
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
        const payload = {
            ...controlState.value,
            actorId: charData.value?.uuid,
            tokenId: charData.value?.tokenId
        };
        window.axios.post("/damageroll", payload).then((response) => {
            // debouncedFetchCharacterData();
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
                weaponId: controlState.value.selectedWeapon._id,
                actorId: charData.value?.uuid,
                tokenId: charData.value?.tokenId
            };
            window.axios.post("/reloadweapon", payload);
            // Handled by SHEET_DELTA from Foundry
        }
    };

    watch(() => controlState.value.selectedTarget, (newTarget) => {
        if (newTarget) {
            targetToken(newTarget.id);
            debouncedFetchDV();
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
