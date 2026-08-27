// Cyberpunk Red published range / Difficulty Value tables (core rulebook).
// Ported from the Bridge's constants.js so the Agent can render a weapon's full
// DV-by-range table client-side, with no Foundry round-trip. Keep in sync with
// src/constants.js in the Cyberpunk-Red-Agent-Bridge repo.

export const RANGE_DV_TABLE = {
    'Pistol':              [13, 15, 20, 25, 30, 30, 99, 99],
    'SMG':                 [15, 13, 15, 20, 25, 25, 30, 99],
    'Shotgun':             [13, 15, 20, 25, 30, 35, 99, 99],
    'Assault Rifle':       [17, 16, 15, 13, 15, 20, 25, 30],
    'Sniper Rifle':        [30, 25, 25, 20, 15, 16, 17, 20],
    'Bows and Crossbows':  [15, 13, 15, 17, 20, 22, 99, 99],
    'Grenade Launcher':    [16, 15, 15, 17, 20, 22, 25, 99],
    'Rocket Launcher':     [17, 16, 15, 15, 20, 20, 25, 30],
    'Martial Arts':        ['VS'],
    'Unarmed':             ['VS'],
    'Light Melee Weapon':  ['VS'],
    'Medium Melee Weapon': ['VS'],
    'Heavy Melee Weapon':  ['VS'],
    'Very Heavy Melee Weapon': ['VS'],
};

// Upper bound (metres) of each range band. distanceM <= brackets[i] picks band i.
export const DISTANCE_BRACKETS = [6, 12, 25, 50, 100, 200, 400, 800];

// Human-readable band labels aligned to DISTANCE_BRACKETS.
const BAND_LABELS = ['0-6', '7-12', '13-25', '26-50', '51-100', '101-200', '201-400', '401-800'];

const WEAPON_TYPE_MAP = {
    'mediumPistol': 'Pistol',
    'heavyPistol': 'Pistol',
    'veryHeavyPistol': 'Pistol',
    'smg': 'SMG',
    'heavySmg': 'SMG',
    'shotgun': 'Shotgun',
    'assaultRifle': 'Assault Rifle',
    'sniperRifle': 'Sniper Rifle',
    'bowsAndCrossbows': 'Bows and Crossbows',
    'grenadeLauncher': 'Grenade Launcher',
    'rocketLauncher': 'Rocket Launcher',
    'thrownWeapon': 'Grenade Launcher',
    'unarmed': 'Unarmed',
    'martialArts': 'Martial Arts',
    'lightMelee': 'Light Melee Weapon',
    'lightMeleeWeapon': 'Light Melee Weapon',
    'mediumMelee': 'Medium Melee Weapon',
    'mediumMeleeWeapon': 'Medium Melee Weapon',
    'heavyMelee': 'Heavy Melee Weapon',
    'heavyMeleeWeapon': 'Heavy Melee Weapon',
    'vHeavyMelee': 'Very Heavy Melee Weapon',
    'veryHeavyMelee': 'Very Heavy Melee Weapon',
    'veryHeavyMeleeWeapon': 'Very Heavy Melee Weapon',
};

/** Resolve the RANGE_DV_TABLE category for a weapon item. */
export function weaponCategory(weapon) {
    return (WEAPON_TYPE_MAP[weapon?.system?.weaponType] || WEAPON_TYPE_MAP[weapon?.system?.variety]) || 'Pistol';
}

/**
 * Build the DV-by-range rows for a weapon. Returns:
 *   { category, melee, rows: [{ range, dv, outOfRange }] }
 * Melee weapons resolve to a single 'VS' (opposed roll) row.
 */
export function dvRowsForWeapon(weapon) {
    const category = weaponCategory(weapon);
    const table = RANGE_DV_TABLE[category];
    if (!table) return { category, melee: false, rows: [] };

    if (typeof table[0] === 'string') {
        return { category, melee: true, rows: [{ range: 'Melee', dv: table[0], outOfRange: false }] };
    }

    const rows = table.map((dv, i) => ({
        range: `${BAND_LABELS[i]}m`,
        dv,
        outOfRange: dv === 99,
    }));
    return { category, melee: false, rows };
}

/**
 * Index of the band a distance falls into (matches lookupRangeDV bracketing).
 * Returns -1 when out of range or when distance is unknown.
 */
export function activeBracketIndex(distanceM) {
    if (typeof distanceM !== 'number') return -1;
    return DISTANCE_BRACKETS.findIndex(limit => distanceM <= limit);
}
