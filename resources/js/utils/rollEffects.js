// Armour penalties and item active effects, computed client-side from the cached
// actor JSON. The Bridge's GET_ROLL_MODIFIERS only reports wound state and
// critical injuries, so these fill the gaps in the roll panel without a Bridge
// change. The rules are ported from cyberpunk-red-core; keep in sync with:
//   actor/cpr-actor.js           getArmorPenaltyMods / _getArmorValue
//   item/mixins/cpr-effects.js   areEffectsSuppressed
//   item/mixins/cpr-attackable.js which bonus keys an attack roll picks up
//   rolls/cpr-modifiers.js       situational filtering
//   utils/cpr-systemUtils.js     slugify
//
// The real roll still happens in Foundry (actor.sheet._onRoll), which applies
// all of this itself. This module only makes the panel tell the truth about it.

export const ARMOUR_PENALTY_STATS = ['ref', 'dex', 'move'];

// Foundry CONST.ACTIVE_EFFECT_MODES
const MODE = {CUSTOM: 0, MULTIPLY: 1, ADD: 2, DOWNGRADE: 3, UPGRADE: 4, OVERRIDE: 5};

const STAT_KEY = /^system\.stats\.(\w+)\.value$/;

// Bonuses that apply to every action, so they count with or without a weapon.
const ALL_ACTIONS_KEYS = ['allActions', 'allActionsSpeech', 'allActionsHands'];

/**
 * CPR's skill-name → bonus-key slug ("Shoulder Arms" → "shoulderArms",
 * "Wardrobe & Style" → "wardrobeAndStyle"). Quirks included on purpose.
 */
export function slugify(name) {
    const initialSplit = String(name).split(' ').join('');
    const lowerFirst = (s) => s.charAt(0).toLowerCase() + s.slice(1);
    if (name === 'Conceal/Reveal Object' || name === 'Paint/Draw/Sculpt' || name === 'Resist Torture/Drugs') {
        return lowerFirst(initialSplit.split('/').join('Or'));
    }
    if (name === 'Language (Streetslang)') {
        return lowerFirst(initialSplit.split('(').join('').split(')').join(''));
    }
    return lowerFirst(initialSplit.split('/').join('And').split('&').join('And'));
}

/**
 * Worst penalty across equipped armour, as a negative number with its source.
 * CPR takes the max penalty per location (head/body) then the worst location,
 * which is the same as the single worst equipped piece. Null when unarmoured.
 */
export function armourPenalty(items = []) {
    let worst = null;
    for (const item of items) {
        if (item?.type !== 'armor' || item.system?.equipped !== 'equipped') continue;
        const penalty = Math.abs(Number(item.system.penalty) || 0);
        if (penalty > 0 && (!worst || penalty > -worst.value)) {
            worst = {value: -penalty, source: item.name};
        }
    }
    return worst;
}

/** Whether an item's usage mode switches its effects off (CPR areEffectsSuppressed). */
export function areEffectsSuppressed(item) {
    const system = item?.system || {};
    switch (system.usage) {
        case 'carried':
            return system.equipped === 'owned';
        case 'equipped':
            return system.equipped !== 'equipped';
        case 'installed':
            return !system.isInstalled;
        case 'rezzed':
            return !system.isRezzed;
        default:
            return false;
    }
}

/**
 * Every change on every effect (actor effects and item effects), one row each.
 * `active` mirrors Foundry: not disabled and not suppressed by item usage.
 * Actor-level effects are never suppressed.
 */
export function collectMods(actorData) {
    const mods = [];
    const push = (effect, item) => {
        const situational = effect.flags?.['cyberpunk-red-core']?.changes?.situational || [];
        const suppressed = item ? areEffectsSuppressed(item) : false;
        (effect.changes || []).forEach((change, index) => {
            const value = Number(change.value);
            if (Number.isNaN(value)) return;
            mods.push({
                source: item?.name || effect.name || 'Effect',
                effect: effect.name || '',
                itemType: item?.type || null,
                key: change.key,
                mode: Number(change.mode),
                value,
                active: !effect.disabled && !suppressed,
                situational: !!situational[index]?.isSituational,
                onByDefault: !!situational[index]?.onByDefault,
            });
        });
    };

    (actorData?.effects || []).forEach((effect) => push(effect, null));
    (actorData?.items || []).forEach((item) => (item.effects || []).forEach((effect) => push(effect, item)));
    return mods;
}

const applyMode = (current, mod) => {
    switch (mod.mode) {
        case MODE.ADD: return current + mod.value;
        case MODE.MULTIPLY: return current * mod.value;
        case MODE.OVERRIDE: return mod.value;
        case MODE.UPGRADE: return Math.max(current, mod.value);
        case MODE.DOWNGRADE: return Math.min(current, mod.value);
        default: return current;
    }
};

/**
 * Stats as Foundry derives them: the cached JSON holds source values, so a
 * Broken Leg's -4 MOVE only shows up once active stat effects are applied.
 * Armour is not folded in, matching Foundry (CPR applies it per roll instead).
 *
 * Returns { ref: { base, value, mods: [{source, value}] }, ... }
 */
export function effectiveStats(actorData) {
    const stats = actorData?.system?.stats || {};
    const result = {};
    for (const [name, stat] of Object.entries(stats)) {
        const base = Number(stat?.value) || 0;
        result[name] = {base, value: base, mods: []};
    }

    const statMods = collectMods(actorData)
        .filter((m) => m.active && STAT_KEY.test(m.key))
        .sort((a, b) => a.mode - b.mode);
    for (const mod of statMods) {
        const entry = result[mod.key.match(STAT_KEY)[1]];
        if (!entry) continue;
        entry.value = applyMode(entry.value, mod);
        entry.mods.push({source: mod.source, value: mod.value});
    }
    return result;
}

/** The `bonuses.*` keys a roll picks up (cpr-attackable.js for attacks). */
export function relevantBonusKeys({skillName, weapon} = {}) {
    if (!weapon) return [...ALL_ACTIONS_KEYS];
    const keys = [...ALL_ACTIONS_KEYS, 'universalAttack'];
    if (skillName && skillName !== 'None') {
        const slug = slugify(skillName);
        keys.push(slug, `${slug}Hearing`, `${slug}Sight`);
    }
    if (weapon.system?.isRanged === true) keys.push('ranged', 'singleShot');
    if (weapon.system?.isRanged === false) keys.push('melee');
    return keys;
}

/**
 * Panel rows for armour and effects, split into what counts towards the roll
 * and what could count (situational, or the effect is currently off).
 *
 * Critical injuries are skipped: the Bridge already reports those.
 * Stat effects on the roll's stat are shown but not totalled, because the
 * Bridge's statValue comes from the live actor and already includes them.
 */
export function rollModRows(actorData, {statName, skillName, weapon} = {}) {
    const applied = [];
    const available = [];
    let total = 0;
    const stat = String(statName || '').toLowerCase();

    const armour = armourPenalty(actorData?.items);
    if (armour) {
        if (weapon && ARMOUR_PENALTY_STATS.includes(stat)) {
            applied.push({title: armour.source, detail: `Armour penalty (${stat.toUpperCase()})`, value: armour.value});
            total += armour.value;
        } else {
            available.push({title: armour.source, detail: 'Armour penalty: REF / DEX / MOVE rolls', value: armour.value});
        }
    }

    const keys = relevantBonusKeys({skillName, weapon});
    for (const mod of collectMods(actorData)) {
        if (mod.itemType === 'criticalInjury') continue;

        const statMatch = mod.key.match(STAT_KEY);
        if (statMatch) {
            if (weapon && mod.active && statMatch[1] === stat) {
                applied.push({title: mod.source, detail: `${mod.effect}: in ${stat.toUpperCase()}`, value: mod.value, included: true});
            }
            continue;
        }

        const bonus = String(mod.key).replace(/^bonuses\./, '');
        if (bonus === mod.key || !keys.includes(bonus)) continue;

        if (mod.active && (!mod.situational || mod.onByDefault)) {
            applied.push({title: mod.source, detail: mod.effect, value: mod.value});
            total += mod.value;
        } else {
            available.push({title: mod.source, detail: mod.active ? `${mod.effect}: situational` : `${mod.effect}: inactive`, value: mod.value});
        }
    }

    return {applied, available, total};
}

/**
 * Merge armour and effects into the Bridge's ROLL_MODIFIERS_DATA result.
 * Leaves the Bridge's own fields intact and adjusts the totals.
 */
export function augmentRollMods(result, actorData, weapon) {
    if (!result) return result;
    const breakdown = result.breakdown || {};
    const {applied, available, total} = rollModRows(actorData, {
        statName: weapon ? breakdown.statName : null,
        skillName: breakdown.skillName,
        weapon,
    });
    return {
        ...result,
        effects: applied,
        availableEffects: available,
        totalPenalty: (Number(result.totalPenalty) || 0) + total,
        finalModifier: (Number(result.finalModifier) || 0) + total,
    };
}
