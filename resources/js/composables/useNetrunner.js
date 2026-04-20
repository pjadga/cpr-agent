import {computed, ref} from 'vue';

export function useNetrunner(charData, sendRequest, throwError) {
    const showCyberdeckConfig = ref(false);
    const expandedPrograms = ref({});

    // 1. Get the Active Deck with populated Programs
    const activeCyberdeck = computed(() => {
        if (!charData.value) return null;
        const deck = charData.value.data.items.find(i => i.type === 'cyberdeck' && i.system.equipped === 'equipped');
        if (!deck) return null;

        const installedList = deck.system.installedItems?.list || [];
        const enrichedPrograms = installedList.map(id => {
            return charData.value.data.items.find(i => i._id === id && i.type === 'program');
        }).filter(Boolean);

        return {...deck, installedPrograms: enrichedPrograms};
    });

    // 2. Get Uninstalled Programs (Inventory)
    const availablePrograms = computed(() => {
        if (!charData.value || !activeCyberdeck.value) return [];
        const installedIds = activeCyberdeck.value.system.installedItems?.list || [];
        return charData.value.data.items.filter(i =>
            i.type === 'program' && !installedIds.includes(i._id)
        );
    });

    // 3. Categorize Installed Programs
    const programCategories = computed(() => {
        if (!activeCyberdeck.value) return {};
        const cats = {
            'Anti-Personnel': [],
            'Anti-Program': [],
            'Booster': [],
            'Defender': [],
            'Black ICE': []
        };

        activeCyberdeck.value.installedPrograms.forEach(prog => {
            const cls = (prog.system.class || '').toLowerCase();
            const type = (prog.type || '').toLowerCase();
            const name = (prog.name || '').toLowerCase();

            if (type === 'blackice' || cls.includes('blackice')) {
                cats['Black ICE'].push(prog);
            } else if (cls.includes('defender') || ['shield', 'armor'].some(n => name.includes(n))) {
                cats['Defender'].push(prog);
            } else if (cls.includes('antipersonnel')) {
                cats['Anti-Personnel'].push(prog);
            } else if (cls.includes('antiprogram') || cls.includes('attacker')) {
                cats['Anti-Program'].push(prog);
            } else if (cls.includes('booster')) {
                cats['Booster'].push(prog);
            } else {
                cats['Anti-Program'].push(prog);
            }
        });
        return cats;
    });

    const isEffectivelyRezzed = (prog) => {
        if (prog.system.isRezzed) return true;
        const cls = (prog.system.class || '').toLowerCase();
        return ['antipersonnel', 'antiprogram', 'attacker'].some(c => cls.includes(c));
    };

    const canBeRezzed = (prog) => {
        const cls = (prog.system.class || '').toLowerCase();
        const name = (prog.name || '').toLowerCase();
        return ['defender', 'booster', 'blackice'].some(c => cls.includes(c)) ||
            ['shield', 'armor'].some(n => name.includes(n));
    };

    const installProgram = (program) => {
        if (!activeCyberdeck.value) return;
        const slots = activeCyberdeck.value.system.installedItems?.slots || 0;
        const used = activeCyberdeck.value.system.installedItems?.usedSlots || 0;
        if (used >= slots) {
            throwError("Cyberdeck memory full.");
            return;
        }
        sendRequest({deckId: activeCyberdeck.value._id, programId: program._id}, '/installprogram');
    };

    const uninstallProgram = (program) => {
        if (!activeCyberdeck.value) return;
        sendRequest({deckId: activeCyberdeck.value._id, programId: program._id}, '/uninstallprogram');
    };

    const toggleRez = (program) => {
        sendRequest({programId: program._id, isRezzed: !program.system.isRezzed}, '/toggleprogramrez');
    };

    const rollProgram = (program, activeDeck, controlState) => {
        if (controlState) controlState.value.selectedProgram = program;
        sendRequest({
            cyberdeckId: activeDeck._id,
            programId: program._id,
            programName: program.name,
            type: program.type,
            executionType: (['defender', 'booster', 'blackice'].some(c => program.system.class.includes(c)) ? "def" : "atk"),
        }, '/programroll');
    };

    const toggleProgramDetails = (id) => {
        expandedPrograms.value[id] = !expandedPrograms.value[id];
    };

    return {
        activeCyberdeck,
        availablePrograms,
        programCategories,
        showCyberdeckConfig,
        expandedPrograms,
        isEffectivelyRezzed,
        canBeRezzed,
        installProgram,
        uninstallProgram,
        toggleRez,
        rollProgram,
        toggleProgramDetails
    };
}
