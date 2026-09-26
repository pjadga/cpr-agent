import {ref} from 'vue';
import {useEchoListener} from '../utils/listener';

// Shared singletons so the install trigger (radial / row action) and the modal
// component read the same state, regardless of which component calls useCyberware.
const showInstallModal = ref(false);
const installLoading = ref(false);
// The CYBERWARE_INSTALL_OPTIONS payload:
// { itemId, itemName, isFoundational, canInstall, reason, targets:[{id,name}], humanityLoss:{roll,static} }
const installOptions = ref(null);
// Uninstall is a confirm step rather than a request/response round trip: there
// is nothing to ask Foundry, only the player to confirm before the mod comes out.
const showUninstallModal = ref(false);
const uninstallTarget = ref(null);

/**
 * Cyberware install / uninstall. Mirrors CPR's dialog flow, driven from the app:
 *   requestInstall(item)  -> PREPARE_CYBERWARE_INSTALL, options return via echo
 *   confirmInstall(...)   -> INSTALL_CYBERWARE (installItems + humanity loss)
 *   requestUninstall(item) -> confirm, then UNINSTALL_CYBERWARE
 * The sheet updates via SHEET_DELTA; the humanity roll posts a CPR chat card.
 *
 * Call once (in character-sheet) so the echo listener registers a single time.
 */
export function useCyberware() {
    const requestInstall = async (item) => {
        if (!item?._id) return;
        installOptions.value = null;
        installLoading.value = true;
        try {
            await window.axios.post('/api/prepare-cyberware-install', {itemId: item._id});
            // Options arrive asynchronously as CYBERWARE_INSTALL_OPTIONS.
        } catch (e) {
            console.error('Error preparing cyberware install', e);
            installLoading.value = false;
        }
    };

    const confirmInstall = async ({foundationalId = null, humanityLossType = 'none'} = {}) => {
        const opts = installOptions.value;
        if (!opts) return;
        showInstallModal.value = false;
        try {
            await window.axios.post('/api/install-cyberware', {
                itemId: opts.itemId,
                foundationalId,
                humanityLossType,
            });
        } catch (e) {
            console.error('Error installing cyberware', e);
        }
    };

    // Open the confirm. The parent is resolved here rather than at the row so
    // the Bridge gets the foundational it needs to detach from.
    const requestUninstall = (item, items = []) => {
        if (!item?._id) return;
        uninstallTarget.value = {
            item,
            foundationalId: findParentId(item._id, items),
        };
        showUninstallModal.value = true;
    };

    const confirmUninstall = async () => {
        const target = uninstallTarget.value;
        if (!target) return;
        showUninstallModal.value = false;
        try {
            await window.axios.post('/api/uninstall-cyberware', {
                itemId: target.item._id,
                foundationalId: target.foundationalId,
            });
        } catch (e) {
            console.error('Error uninstalling cyberware', e);
        }
        uninstallTarget.value = null;
    };

    const closeUninstallModal = () => {
        showUninstallModal.value = false;
        uninstallTarget.value = null;
    };

    const closeInstallModal = () => {
        showInstallModal.value = false;
    };

    const findParentId = (childId, items) => {
        const parent = (items || []).find(
            (i) => (i.system?.installedItems?.list || []).includes(childId)
        );
        return parent?._id || null;
    };

    const handleFoundryResponse = (e) => {
        const payload = e.payload || e;
        if (payload.type === 'CYBERWARE_INSTALL_OPTIONS') {
            installLoading.value = false;
            installOptions.value = payload;
            showInstallModal.value = true;
        }
    };

    useEchoListener(handleFoundryResponse);

    return {
        showInstallModal,
        installLoading,
        installOptions,
        requestInstall,
        confirmInstall,
        closeInstallModal,
        showUninstallModal,
        uninstallTarget,
        requestUninstall,
        confirmUninstall,
        closeUninstallModal,
    };
}
