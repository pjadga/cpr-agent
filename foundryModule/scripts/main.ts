/**
 * CPRA Foundry Bridge
 * Logic for communicating with the Cyberpunk Red Companion App
 */

Hooks.once('init', () => {
    console.log("CPRA Foundry Bridge | Initializing");
});

Hooks.once('ready', () => {
    if (!game.user?.isGM) {
        return;
    }

    console.log("CPRA Foundry Bridge | GM Session Active - Establishing Link");

    // We'll use a simple socket connection back to the Laravel app.
    // In a real scenario, this would be configured via module settings.
    const socketUrl = (game.settings.get('cpra-foundry-bridge', 'socketUrl') as string) || window.location.origin;

    // For now, let's define the actions we can perform
    const actions: Record<string, Function> = {
        executeJs: async (params: any) => {
            try {
                // Wrap in async function to allow 'await' in the executed code
                const AsyncFunction = Object.getPrototypeOf(async function(){}).constructor;
                const fn = new AsyncFunction(params.code);
                const result = await fn();
                return { success: true, result };
            } catch (error: any) {
                console.error("CPRA | executeJs Error:", error);
                return { success: false, error: error.message };
            }
        },

        getClientId: () => {
            return { clientId: game.sessionId };
        },

        getLastRoll: () => {
            // @ts-ignore
            const lastRollMessage = game.messages.contents.slice().reverse().find(m => m.rolls.length > 0);
            // @ts-ignore
            return lastRollMessage ? lastRollMessage.rolls[0].total : null;
        },

        getActorSheetAsJson: (params: any) => {
            // @ts-ignore
            const actor = fromUuidSync(params.actorUuid);
            return actor ? { data: actor.toObject() } : { success: false, error: "Actor not found" };
        },

        getEntity: (params: any) => {
            let entity;
            if (params.type === 'uuid') {
                // @ts-ignore
                entity = fromUuidSync(params.id);
            } else {
                // @ts-ignore
                entity = game.actors.get(params.id);
            }
            return entity ? entity.toObject() : { success: false, error: "Entity not found" };
        }
    };

    // Placeholder for Socket implementation
    // This could use Socket.io, Pusher, or raw WebSockets depending on the Laravel setup.
    console.log("CPRA Foundry Bridge | Supported Actions:", Object.keys(actions));

    // Example of how a message would be handled:
    /*
    socket.on('cpra-request', async (data: any) => {
        if (actions[data.action]) {
            const response = await actions[data.action](data);
            socket.emit('cpra-response', { requestId: data.requestId, ...response });
        }
    });
    */
});

// Register Module Settings
Hooks.once('setup', () => {
    // @ts-ignore
    game.settings.register('cpra-foundry-bridge', 'socketUrl', {
        name: 'Companion App Socket URL',
        hint: 'The URL of the Laravel application providing the socket bridge.',
        scope: 'world',
        config: true,
        type: String,
        default: ''
    });
});
