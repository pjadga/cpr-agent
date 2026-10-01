import {describe, it, expect} from 'vitest';
import {
    slugify,
    armourPenalty,
    areEffectsSuppressed,
    effectiveStats,
    relevantBonusKeys,
    rollModRows,
    augmentRollMods,
    modLabel,
    effectList,
    skillTotal,
} from '@/utils/rollEffects';

// Shapes copied from real cached actor JSON (users.character_data).
const effect = (name, changes, {disabled = false, situational = []} = {}) => ({
    name,
    disabled,
    changes: changes.map(([key, value, mode = 2]) => ({key, value: String(value), mode})),
    flags: {'cyberpunk-red-core': {changes: {situational}}},
});

const armour = (name, penalty, equipped = 'equipped') => ({
    _id: name, name, type: 'armor', effects: [],
    system: {equipped, penalty},
});

const pistol = {_id: 'w1', name: 'Pistol', type: 'weapon', system: {isRanged: true, weaponSkill: 'Handgun'}};
const knife = {_id: 'w2', name: 'Knife', type: 'weapon', system: {isRanged: false, weaponSkill: 'Melee Weapon'}};

const actor = (items, stats = {}) => ({
    system: {stats: {ref: {value: 6}, dex: {value: 6}, move: {value: 6}, ...stats}},
    items,
    effects: [],
});

describe('slugify', () => {
    it('matches CPR bonus keys', () => {
        expect(slugify('Handgun')).toBe('handgun');
        expect(slugify('Shoulder Arms')).toBe('shoulderArms');
        expect(slugify('Wardrobe & Style')).toBe('wardrobeAndStyle');
        expect(slugify('Library Search')).toBe('librarySearch');
        expect(slugify('Conceal/Reveal Object')).toBe('concealOrRevealObject');
        expect(slugify('Language (Streetslang)')).toBe('languageStreetslang');
    });
});

describe('armourPenalty (#30)', () => {
    it('takes the single worst equipped penalty as a negative', () => {
        const items = [armour('Flak (Body)', 4), armour('Kevlar Hat', 0), armour('Light Armorjack', 2)];
        expect(armourPenalty(items)).toEqual({value: -4, source: 'Flak (Body)'});
    });

    it('ignores armour that is only owned or carried', () => {
        expect(armourPenalty([armour('Flak (Body)', 4, 'owned'), armour('Flak (Head)', 4, 'carried')])).toBeNull();
    });

    it('accepts negative penalties like CPR does', () => {
        expect(armourPenalty([armour('Metalgear', -4)])?.value).toBe(-4);
    });
});

describe('areEffectsSuppressed', () => {
    it('follows item usage', () => {
        expect(areEffectsSuppressed({system: {usage: 'carried', equipped: 'owned'}})).toBe(true);
        expect(areEffectsSuppressed({system: {usage: 'carried', equipped: 'carried'}})).toBe(false);
        expect(areEffectsSuppressed({system: {usage: 'equipped', equipped: 'carried'}})).toBe(true);
        expect(areEffectsSuppressed({system: {usage: 'installed', isInstalled: false}})).toBe(true);
        expect(areEffectsSuppressed({system: {usage: 'toggled'}})).toBe(false);
    });
});

describe('effectiveStats (#22)', () => {
    it('applies a Broken Leg to MOVE', () => {
        const brokenLeg = {
            _id: 'i1', name: 'Broken Leg', type: 'criticalInjury', system: {usage: 'toggled'},
            effects: [effect('Broken Leg', [['system.stats.move.value', -4]])],
        };
        const stats = effectiveStats(actor([brokenLeg]));
        expect(stats.move).toEqual({base: 6, value: 2, mods: [{source: 'Broken Leg', value: -4}]});
        expect(stats.ref.value).toBe(6);
    });

    it('skips disabled effects (an unsnorted drug)', () => {
        const synthcoke = {
            _id: 'd1', name: 'Synthcoke', type: 'drug', system: {usage: 'snorted'},
            effects: [effect('Synthcoke Primary', [['system.stats.ref.value', 1]], {disabled: true})],
        };
        expect(effectiveStats(actor([synthcoke])).ref.value).toBe(6);
    });

    it('does not fold armour into stats, matching Foundry', () => {
        expect(effectiveStats(actor([armour('Flak (Body)', 4)])).ref.value).toBe(6);
    });
});

describe('relevantBonusKeys', () => {
    it('only all-action keys without a weapon', () => {
        expect(relevantBonusKeys({})).toEqual(['allActions', 'allActionsSpeech', 'allActionsHands']);
    });

    it('adds skill, attack and ranged/melee keys for a weapon', () => {
        expect(relevantBonusKeys({skillName: 'Handgun', weapon: pistol})).toEqual(expect.arrayContaining(['handgun', 'universalAttack', 'ranged', 'singleShot']));
        expect(relevantBonusKeys({skillName: 'Melee Weapon', weapon: knife})).toEqual(expect.arrayContaining(['meleeWeapon', 'melee']));
        expect(relevantBonusKeys({skillName: 'Melee Weapon', weapon: knife})).not.toContain('ranged');
    });
});

describe('rollModRows', () => {
    it('applies armour to a REF attack (#30)', () => {
        const {applied, total} = rollModRows(actor([armour('Flak (Body)', 4)]), {statName: 'ref', skillName: 'Handgun', weapon: pistol});
        expect(applied).toEqual([expect.objectContaining({title: 'Flak (Body)', value: -4})]);
        expect(total).toBe(-4);
    });

    it('lists armour as available, not applied, when the roll stat is not REF/DEX/MOVE', () => {
        const {applied, available, total} = rollModRows(actor([armour('Flak (Body)', 4)]), {statName: 'tech', skillName: 'Handgun', weapon: pistol});
        expect(applied).toEqual([]);
        expect(available).toHaveLength(1);
        expect(total).toBe(0);
    });

    it('splits enabled, situational and inactive effects (#22)', () => {
        const scope = {
            _id: 'g1', name: 'Smartgun Link', type: 'cyberware', system: {usage: 'installed', isInstalled: true},
            effects: [effect('Link', [['bonuses.handgun', 1], ['bonuses.handgun', 2], ['bonuses.handgun', 3]], {
                situational: [
                    {isSituational: false, onByDefault: false},
                    {isSituational: true, onByDefault: true},
                    {isSituational: true, onByDefault: false},
                ],
            })],
        };
        const drug = {
            _id: 'd1', name: 'Black Lace', type: 'drug', system: {usage: 'snorted'},
            effects: [effect('Rush', [['bonuses.allActions', 1]], {disabled: true})],
        };
        const {applied, available, total} = rollModRows(actor([scope, drug]), {statName: 'ref', skillName: 'Handgun', weapon: pistol});
        expect(applied.map((r) => r.value)).toEqual([1, 2]);
        expect(total).toBe(3);
        expect(available.map((r) => [r.title, r.value])).toEqual([['Smartgun Link', 3], ['Black Lace', 1]]);
    });

    it('ignores bonuses for other skills', () => {
        const agent = {
            _id: 'a1', name: 'Agent', type: 'gear', system: {usage: 'carried', equipped: 'equipped'},
            effects: [effect('Agent', [['bonuses.librarySearch', 2]])],
        };
        expect(rollModRows(actor([agent]), {statName: 'ref', skillName: 'Handgun', weapon: pistol}).applied).toEqual([]);
    });

    it('shows stat effects on the roll stat without totalling them', () => {
        const coke = {
            _id: 'd1', name: 'Synthcoke', type: 'drug', system: {usage: 'snorted'},
            effects: [effect('Synthcoke Primary', [['system.stats.ref.value', 1]])],
        };
        const {applied, total} = rollModRows(actor([coke]), {statName: 'ref', skillName: 'Handgun', weapon: pistol});
        expect(applied).toEqual([expect.objectContaining({value: 1, included: true})]);
        expect(total).toBe(0);
    });

    it('leaves critical injuries to the Bridge', () => {
        const arm = {
            _id: 'c1', name: 'Broken Arm', type: 'criticalInjury', system: {usage: 'toggled'},
            effects: [effect('Broken Arm', [['bonuses.allActionsHands', -2]])],
        };
        expect(rollModRows(actor([arm]), {}).applied).toEqual([]);
    });
});

describe('augmentRollMods', () => {
    it('adds armour to the Bridge totals', () => {
        const bridge = {
            actorName: 'Jimmy', baseBonus: 12,
            breakdown: {statName: 'ref', statValue: 6, skillName: 'Handgun', skillLevel: 4, weaponAcc: 2},
            totalPenalty: -2, finalModifier: 10, injuries: [{title: 'Seriously Wounded', penalty: -2}],
        };
        const out = augmentRollMods(bridge, actor([armour('Flak (Body)', 4)]), pistol);
        expect(out.totalPenalty).toBe(-6);
        expect(out.finalModifier).toBe(6);
        expect(out.injuries).toBe(bridge.injuries);
        expect(out.effects).toHaveLength(1);
    });

    it('passes null through', () => {
        expect(augmentRollMods(null, actor([]), pistol)).toBeNull();
    });
});

// Shapes from the live character (Smash, Black Lace, Dismembered Leg).
const smash = {
    _id: 'd2', name: 'Smash', type: 'drug', system: {usage: 'snorted'},
    effects: [
        effect('Smash Primary', [['bonuses.persuasion', 2], ['bonuses.acting', 2]]),
        effect('Smash Addiction', [['bonuses.persuasion', -2]], {disabled: true}),
    ],
};
const persuasion = {_id: 's1', name: 'Persuasion', type: 'skill', system: {stat: 'cool', level: 4}};
const handgun = {_id: 's2', name: 'Handgun', type: 'skill', system: {stat: 'ref', level: 6}};

describe('modLabel', () => {
    it('names stats and bonuses for people', () => {
        expect(modLabel('system.stats.move.value')).toBe('MOVE');
        expect(modLabel('bonuses.allActionsHands')).toBe('Hand actions');
        expect(modLabel('bonuses.humanPerception')).toBe('Human perception');
    });
});

describe('skillTotal (#22)', () => {
    it('adds a skill bonus from an active effect, not the disabled one', () => {
        const total = skillTotal(actor([smash], {cool: {value: 5}}), persuasion);
        expect(total).toEqual({base: 9, value: 11, mods: [{source: 'Smash', value: 2}]});
    });

    it('folds effect-adjusted stats and armour into REF skills', () => {
        const blackLace = {
            _id: 'd1', name: 'Black Lace', type: 'drug', system: {usage: 'snorted'},
            effects: [effect('Black Lace Addiction', [['system.stats.ref.value', -2]])],
        };
        const total = skillTotal(actor([blackLace, armour('Flak (Body)', 4)]), handgun);
        expect(total.base).toBe(12);
        expect(total.value).toBe(6);
        expect(total.mods).toEqual([{source: 'Black Lace', value: -2}, {source: 'Flak (Body)', value: -4}]);
    });

    it('is unchanged with no effects', () => {
        expect(skillTotal(actor([]), handgun)).toEqual({base: 12, value: 12, mods: []});
    });
});

describe('effectList (#22)', () => {
    it('lists active effects first, inactive with a reason', () => {
        const hat = {
            _id: 'g1', name: 'Kevlar Hat', type: 'clothing', system: {usage: 'equipped', equipped: 'carried'},
            effects: [effect('Style', [['bonuses.wardrobeAndStyle', 1]])],
        };
        const list = effectList(actor([smash, hat]));
        expect(list.map((e) => [e.effect, e.active, e.reason])).toEqual([
            ['Smash Primary', true, null],
            ['Smash Addiction', false, 'off'],
            ['Style', false, 'not equipped'],
        ]);
        expect(list[0].changes).toEqual([
            {label: 'Persuasion', value: 2, situational: false},
            {label: 'Acting', value: 2, situational: false},
        ]);
    });
});
