/**
 * Deep equality check to prevent unnecessary reactivity updates
 */
export function isDeepEqual(obj1, obj2) {
    if (obj1 === obj2) return true;
    if (typeof obj1 !== 'object' || obj1 === null || typeof obj2 !== 'object' || obj2 === null) return false;
    const keys1 = Object.keys(obj1);
    const keys2 = Object.keys(obj2);
    if (keys1.length !== keys2.length) return false;
    for (let key of keys1) {
        if (!keys2.includes(key) || !isDeepEqual(obj1[key], obj2[key])) return false;
    }
    return true;
}

/**
 * Debounce function to limit execution frequency
 */
export function debounce(fn, wait = 250) {
    let t = null;
    return (...args) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

/**
 * Shallow array equality check
 */
export function arraysEqualShallow(a, b) {
    if (a === b) return true;
    if (!Array.isArray(a) || !Array.isArray(b)) return false;
    if (a.length !== b.length) return false;
    const aIds = a.map(i => i.id || JSON.stringify(i)).join(',');
    const bIds = b.map(i => i.id || JSON.stringify(i)).join(',');
    return aIds === bIds;
}
