import { ref } from 'vue';
import { triggerRef } from 'vue';

/**
 * Composable for managing resource adjustment modals (HP, Humanity)
 */
export function useResources(charData, updateStatCallback) {
    const showResourceModal = ref(false);
    const resourceLabel = ref('');
    const resourceValue = ref(0);
    const resourceMax = ref(0);
    const resourceColor = ref('text-cpr-yellow');
    const bulkSign = ref('+');
    const bulkAmount = ref(0);

    const clampResource = (val) => {
        const max = Number(resourceMax.value) || 0;
        if (max <= 0) return Math.max(0, Math.floor(val));
        return Math.max(0, Math.min(max, Math.floor(val)));
    };

    const openResourceModal = (label, current, max, color) => {
        resourceLabel.value = label;
        resourceValue.value = clampResource(Number(current) || 0);
        resourceMax.value = Number(max) || 0;
        resourceColor.value = color || 'text-cpr-yellow';
        bulkSign.value = '+';
        bulkAmount.value = 0;
        showResourceModal.value = true;
    };

    const closeResourceModal = () => {
        showResourceModal.value = false;
    };

    const stepResource = (delta) => {
        resourceValue.value = clampResource(resourceValue.value + (delta || 0));
    };

    const applyBulkChange = () => {
        const amt = Math.abs(Number(bulkAmount.value) || 0);
        if (!amt) return;
        const sign = bulkSign.value === '-' ? -1 : 1;
        resourceValue.value = clampResource(resourceValue.value + sign * amt);
    };

    const commitResourceValue = () => {
        if (charData.value) {
            if (resourceLabel.value === 'HIT POINTS') {
                const hp = charData.value.data.system.derivedStats.hp;
                if (hp) hp.value = resourceValue.value;
            } else if (resourceLabel.value === 'HUMANITY') {
                const hum = charData.value.data.system.derivedStats.humanity;
                if (hum) hum.value = resourceValue.value;
            }
            triggerRef(charData);
        }
        updateStatCallback(resourceLabel.value, resourceValue.value);
        showResourceModal.value = false;
    };

    return {
        showResourceModal,
        resourceLabel,
        resourceValue,
        resourceMax,
        resourceColor,
        bulkSign,
        bulkAmount,
        openResourceModal,
        closeResourceModal,
        stepResource,
        applyBulkChange,
        commitResourceValue
    };
}
