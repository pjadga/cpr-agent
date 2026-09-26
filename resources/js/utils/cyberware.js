// Shared cyberware tree logic.
//
// CPR models cyberware as a three-level hierarchy that the raw item list only
// half describes:
//
//   Top parent   the body location (Cybereye, Cyberarm, Neuralware, ...). For
//                External/Internal/Fashionware a real `core: true` container
//                item exists; for the rest there is no item at all and the
//                category heading is the only thing standing in for it.
//   Level 2      the purchased foundational sitting in that location
//                ("Kija's Right Cybereye", "Neural Link", "Cyberarm").
//   Level 3      mods installed into that foundational (Chyron, Virtuality).
//
// Parentage lives in `system.installedItems.list` on the parent, so a child
// knows nothing about its own parent and the tree has to be derived.

// system.type -> the heading the player expects to read.
export const CYBERWARE_TYPE_LABELS = {
    cyberAudio: 'Cyberaudio Suite',
    cyberEye: 'Cybereye',
    cyberArm: 'Cyberarm',
    cyberLeg: 'Cyberleg',
    neuralWare: 'Neuralware',
    cyberwareInternal: 'Internal Cyberware',
    cyberwareExternal: 'External Cyberware',
    fashionware: 'Fashionware',
    borgware: 'Borgware',
};

// Headings render in this order rather than whatever order the items arrive in,
// so the sheet doesn't reshuffle itself when an item is added or removed.
export const CYBERWARE_TYPE_ORDER = Object.keys(CYBERWARE_TYPE_LABELS);

export function cyberwareTypeLabel(type) {
    return CYBERWARE_TYPE_LABELS[type] || 'Other Cyberware';
}

/**
 * Map of childId -> parentId across every item that declares installedItems.
 */
export function buildParentMap(items = []) {
    const parents = new Map();
    items.forEach((item) => {
        const list = item.system?.installedItems?.list || [];
        list.forEach((childId) => parents.set(childId, item._id));
    });
    return parents;
}

/**
 * Direct children of an item, resolved against the full item list and with
 * missing ids dropped (Foundry can leave a dangling id behind after a delete).
 */
export function childrenOf(item, items = []) {
    const list = item?.system?.installedItems?.list || [];
    return list.map((id) => items.find((i) => i._id === id)).filter(Boolean);
}

/**
 * Slots consumed by what is actually installed, summing child sizes.
 *
 * The stored `usedSlots` drifts: a real sheet had Fashionware reporting 7/7
 * while holding three size-1 items, and a Cybereye reporting 3/3 with no
 * children at all. Counting the children is the only figure that agrees with
 * what the player can see on the row.
 */
export function usedSlotsOf(item, items = []) {
    return childrenOf(item, items).reduce((total, child) => {
        // An explicit size of 0 is meaningful (foundationals carry it), so only
        // fall back to 1 when the field is genuinely absent.
        const size = Number(child.system?.size);
        return total + (Number.isFinite(size) ? size : 1);
    }, 0);
}

export function totalSlotsOf(item) {
    return Number(item?.system?.installedItems?.slots) || 0;
}

export function freeSlotsOf(item, items = []) {
    return Math.max(0, totalSlotsOf(item) - usedSlotsOf(item, items));
}

/**
 * Whether an item is currently installed.
 *
 * `system.isInstalled` is a derived getter in CPR, so it survives a fresh
 * ACTOR_DATA push but is absent entirely from some cached sheets — one real
 * actor had it missing on all 14 items, which made every installed mod render
 * an Install button. Containment in a parent's list is the only ground truth
 * available, with the getter as a fallback for foundationals, which install
 * into the actor and so never appear in anyone's list.
 */
export function isCyberwareInstalled(item, items = [], parentMap = null) {
    if (!item) return false;
    const parents = parentMap || buildParentMap(items);
    if (parents.has(item._id)) return true;
    if (typeof item.system?.isInstalled === 'boolean') return item.system.isInstalled;
    // No getter and not nested: a foundational holding mods must be installed.
    return !!item.system?.isFoundational && childrenOf(item, items).length > 0;
}

/**
 * Core items are the innate body locations the character is born with. They are
 * the top parent made concrete, cannot be removed, and so carry no action.
 */
export function isTopParent(item) {
    return item?.system?.core === true;
}
