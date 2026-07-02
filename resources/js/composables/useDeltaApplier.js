import { nextTick, triggerRef } from 'vue';

/**
 * Efficiently refresh Lucide icons.
 */
export function useRefreshIcons() {
    return () => {
        if (window.lucide) {
            nextTick(() => window.lucide.createIcons());
        }
    };
}

/**
 * Applies a focused delta update to the local character data.
 * Handles dot-notation paths, items.+ID (append), items.ID.field (deep update), and null values (delete).
 */
export function useDeltaApplier(charData, loading) {
    /**
     * Deep set a value at a dot-notation path within an object.
     */
    const deepSet = (obj, path, value) => {
        // Special case for items.+ID (addition)
        if (path.startsWith('data.items.+')) {
            const itemId = path.replace('data.items.+', '');
            if (!Array.isArray(obj.data.items)) obj.data.items = [];
            const existingIndex = obj.data.items.findIndex(i => i._id === itemId);
            if (existingIndex > -1) {
                obj.data.items[existingIndex] = value;
            } else {
                obj.data.items.push(value);
            }
            return;
        }

        // Special case for data.items.ID.field... or data.items.ID (delete/replace)
        if (path.startsWith('data.items.')) {
            const parts = path.split('.');
            const itemId = parts[2];
            if (!Array.isArray(obj.data.items)) obj.data.items = [];

            const existingIndex = obj.data.items.findIndex(i => i._id === itemId);

            if (parts.length === 3) {
                // Deletion or replacement of entire item: data.items.ID
                if (value === null) {
                    if (existingIndex > -1) obj.data.items.splice(existingIndex, 1);
                } else {
                    if (existingIndex > -1) {
                        obj.data.items[existingIndex] = value;
                    } else {
                        obj.data.items.push(value);
                    }
                }
            } else {
                // Deep update: data.items.ID.field...
                if (existingIndex === -1) return;

                const subPath = parts.slice(3).join('.');
                const targetItem = obj.data.items[existingIndex];

                const setNested = (o, p, v) => {
                    const ks = p.split('.');
                    let t = o;
                    for (let i = 0; i < ks.length - 1; i++) {
                        const k = ks[i];
                        if (typeof t[k] !== 'object' || t[k] === null) t[k] = {};
                        t = t[k];
                    }
                    const lastK = ks[ks.length - 1];
                    if (v === null) delete t[lastK];
                    else t[lastK] = v;
                };

                setNested(targetItem, subPath, value);
            }
            return;
        }

        // Standard dot-notation for non-items paths
        const keys = path.split('.');
        let target = obj;
        for (let i = 0; i < keys.length - 1; i++) {
            const k = keys[i];
            if (typeof target[k] !== 'object' || target[k] === null) {
                target[k] = {};
            }
            target = target[k];
        }

        const lastKey = keys[keys.length - 1];
        if (value === null) {
            delete target[lastKey];
        } else {
            target[lastKey] = value;
        }
    };

    /**
     * Apply a delta to the character data, triggering Vue reactivity.
     */
    const applyDelta = (delta = {}, { tab } = {}) => {
        if (!charData.value || !delta) return;

        // Track which item indices were modified for shallowRef reactivity
        const modifiedItemIndices = new Set();

        Object.entries(delta).forEach(([path, value]) => {
            let mapped = path.startsWith('data.') ? path : (path.startsWith('system') || path.startsWith('items') ? `data.${path}` : path);

            // Special handling for 'items' to ensure it remains an array
            if (path === 'items') {
                const valueArray = Array.isArray(value) ? value : Object.values(value || {});
                charData.value.data.items = valueArray;
                return;
            }

            deepSet(charData.value, mapped, value);
        });

        // Replace charData.value to trigger shallowRef reactivity
        if (modifiedItemIndices.size > 0 && charData.value.data.items) {
            const newItems = charData.value.data.items.map((item, index) => {
                if (modifiedItemIndices.has(index)) {
                    return JSON.parse(JSON.stringify(item));
                }
                return item;
            });

            charData.value = {
                ...charData.value,
                data: {
                    ...charData.value.data,
                    items: newItems
                }
            };
        } else {
            charData.value = {
                ...charData.value,
                data: { ...charData.value.data }
            };
        }

        loading.value = false;
        triggerRef(charData);
    };

    return { applyDelta };
}
