/**
 * Cyberware tree logic.
 *
 * Fixtures mirror two real cached sheets: one where the derived `isInstalled`
 * getter survived serialisation and one where it did not, because the missing
 * getter is what made installed mods offer an Install button.
 */

import {describe, expect, it} from 'vitest';
import {
    buildParentMap,
    childrenOf,
    cyberwareTypeLabel,
    isCyberwareInstalled,
    isTopParent,
    usedSlotsOf,
} from '../../resources/js/utils/cyberware';

const cw = (id, name, system = {}) => ({_id: id, name, type: 'cyberware', system});

// Fashionware reports 7/7 while holding three size-1 items, exactly as the
// stored sheet does.
const fashionware = cw('fw', 'Fashionware (7 Option Slots)', {
    type: 'fashionware',
    isFoundational: true,
    core: true,
    size: 1,
    installedItems: {allowed: true, list: ['techhair', 'tacts', 'chemskin'], slots: 7, usedSlots: 7},
});
const techhair = cw('techhair', 'Techhair', {type: 'fashionware', isFoundational: false, core: false, size: 1});
const tacts = cw('tacts', 'Shift Tacts', {type: 'fashionware', isFoundational: false, core: false, size: 1});
const chemskin = cw('chemskin', 'Chemskin', {type: 'fashionware', isFoundational: false, core: false, size: 1});
// Loose, not installed in anything.
const biomonitor = cw('bio', 'Biomonitor', {
    type: 'fashionware', isFoundational: false, core: false, size: 1,
    installedItems: {allowed: true, list: [], slots: 3, usedSlots: 0},
});
const rightEye = cw('reye', "Kija's Right Cybereye", {
    type: 'cyberEye', isFoundational: true, core: false, size: 0,
    installedItems: {allowed: true, list: ['chyron'], slots: 3, usedSlots: 2},
});
const chyron = cw('chyron', 'Chyron', {type: 'cyberEye', isFoundational: false, core: false, size: 1});

const items = [fashionware, techhair, tacts, chemskin, biomonitor, rightEye, chyron];

describe('cyberware slot counting', () => {
    it('counts slots from the children actually present, not the stored usedSlots', () => {
        expect(fashionware.system.installedItems.usedSlots).toBe(7);
        expect(usedSlotsOf(fashionware, items)).toBe(3);
    });

    it('ignores ids that no longer resolve to an item', () => {
        const stale = cw('stale', 'Stale', {
            installedItems: {list: ['techhair', 'deleted-id'], slots: 4, usedSlots: 2},
        });
        expect(childrenOf(stale, items)).toHaveLength(1);
        expect(usedSlotsOf(stale, items)).toBe(1);
    });

    it('sums child sizes rather than counting them', () => {
        const big = cw('big', 'Borg', {installedItems: {list: ['heavy'], slots: 8, usedSlots: 0}});
        const heavy = cw('heavy', 'Heavy Mod', {size: 3});
        expect(usedSlotsOf(big, [big, heavy])).toBe(3);
    });
});

describe('installed state without the isInstalled getter', () => {
    it('treats containment in a parent as installed', () => {
        expect(techhair.system.isInstalled).toBeUndefined();
        expect(isCyberwareInstalled(techhair, items)).toBe(true);
    });

    it('treats loose cyberware as not installed', () => {
        expect(isCyberwareInstalled(biomonitor, items)).toBe(false);
    });

    it('treats a foundational holding mods as installed', () => {
        expect(isCyberwareInstalled(rightEye, items)).toBe(true);
    });

    it('prefers the getter when it is present', () => {
        const explicit = cw('x', 'Explicit', {isFoundational: true, isInstalled: true});
        expect(isCyberwareInstalled(explicit, [explicit])).toBe(true);
        const notInstalled = cw('y', 'Shelved', {isFoundational: true, isInstalled: false});
        expect(isCyberwareInstalled(notInstalled, [notInstalled])).toBe(false);
    });
});

describe('top parents', () => {
    it('identifies core body locations as top parents', () => {
        expect(isTopParent(fashionware)).toBe(true);
    });

    it('does not treat a purchased foundational as a top parent', () => {
        expect(isTopParent(rightEye)).toBe(false);
        expect(isTopParent(chyron)).toBe(false);
    });
});

describe('grouping', () => {
    it('labels the CPR type codes the sheet actually carries', () => {
        expect(cyberwareTypeLabel('cyberEye')).toBe('Cybereye');
        expect(cyberwareTypeLabel('neuralWare')).toBe('Neuralware');
        expect(cyberwareTypeLabel('cyberwareExternal')).toBe('External Cyberware');
    });

    it('falls back for an unknown type instead of dropping the item', () => {
        expect(cyberwareTypeLabel('somethingNew')).toBe('Other Cyberware');
        expect(cyberwareTypeLabel(undefined)).toBe('Other Cyberware');
    });

    it('maps every child back to its parent', () => {
        const parents = buildParentMap(items);
        expect(parents.get('techhair')).toBe('fw');
        expect(parents.get('chyron')).toBe('reye');
        expect(parents.has('bio')).toBe(false);
    });
});

describe('the installed / not-installed split', () => {
    // Five identical Cyberarms, three fitted and two on the shelf, is the case
    // that showed them all as one undifferentiated list.
    const arm = (id, installed) => cw(id, 'Cyberarm', {
        type: 'cyberArm', isFoundational: true, core: false, size: 0,
        isInstalled: installed,
        installedItems: {allowed: true, list: [], slots: 4, usedSlots: 0},
    });
    const arms = [arm('a1', true), arm('a2', true), arm('a3', true), arm('a4', null), arm('a5', null)];

    it('counts only the fitted arms as installed', () => {
        const fitted = arms.filter((a) => isCyberwareInstalled(a, arms));
        expect(fitted.map((a) => a._id)).toEqual(['a1', 'a2', 'a3']);
    });

    it('leaves the spares uninstalled', () => {
        const spare = arms.filter((a) => !isCyberwareInstalled(a, arms));
        expect(spare.map((a) => a._id)).toEqual(['a4', 'a5']);
    });

    it('treats a null install state as unknown, not as installed', () => {
        // ActorSheet sends null when the derived getter was not serialised.
        expect(isCyberwareInstalled(arm('x', null), [])).toBe(false);
    });
});

describe('slot sizes', () => {
    it('honours an explicit size of zero instead of defaulting it to one', () => {
        const host = cw('host', 'Host', {installedItems: {list: ['free'], slots: 4, usedSlots: 0}});
        const free = cw('free', 'Zero Size', {size: 0});
        expect(usedSlotsOf(host, [host, free])).toBe(0);
    });

    it('still defaults to one when size is absent', () => {
        const host = cw('host2', 'Host', {installedItems: {list: ['nosize'], slots: 4, usedSlots: 0}});
        const nosize = cw('nosize', 'No Size', {});
        expect(usedSlotsOf(host, [host, nosize])).toBe(1);
    });
});
