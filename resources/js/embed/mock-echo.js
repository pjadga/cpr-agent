// ==================================================================
// MOCK ECHO
//
// A stand-in for laravel-echo used when the Agent runs embedded inside
// Foundry (see resources/js/embed/transport.js). In embedded mode there
// is no Reverb/WebSocket — Foundry data arrives as postMessage envelopes
// from the Bridge module. This shim implements the tiny slice of the Echo
// API the app actually uses — `.private(ch).listen('.FoundryResponse', cb)`
// (and `.channel(...)`) — so every existing listener keeps working
// unchanged; we just feed the callbacks from postMessage instead of a
// socket.
// ==================================================================

class MockChannel {
    constructor(hub) { this.hub = hub; }

    listen(event, callback) {
        this.hub._on(event.replace(/^\./, ''), callback);
        return this;
    }

    stopListening(event) {
        this.hub._off(event.replace(/^\./, ''));
        return this;
    }

    // Lifecycle hooks Echo exposes; harmless no-ops here.
    subscribed() { return this; }
    error() { return this; }
    listenForWhisper() { return this; }
}

export class MockEcho {
    constructor() {
        this.listeners = new Map();  // eventName -> Set<callback>
        this.lastActorData = null;   // replayed to late subscribers
    }

    _on(event, cb) {
        if (!this.listeners.has(event)) this.listeners.set(event, new Set());
        this.listeners.get(event).add(cb);
        // Replay the most recent ACTOR_DATA so a listener that subscribes
        // after the Bridge's initial push still paints the sheet.
        if (this.lastActorData) {
            try { cb({ payload: this.lastActorData }); } catch (e) { /* noop */ }
        }
    }

    _off(event) { this.listeners.delete(event); }

    /**
     * Deliver a bridge message to every registered listener as an Echo-shaped
     * event ({ payload }). Listeners filter by payload.type internally, so
     * broadcasting to all of them is safe regardless of the event name they
     * originally subscribed with.
     */
    deliver(payload) {
        if (!payload) return;
        if (payload.type === 'ACTOR_DATA' && payload.actor) {
            this.lastActorData = payload;
        }
        for (const cbs of this.listeners.values()) {
            for (const cb of cbs) {
                try { cb({ payload }); } catch (e) { console.error('[EmbedEcho] listener error', e); }
            }
        }
    }

    // --- Echo surface used by the app ---
    private() { return new MockChannel(this); }
    channel() { return new MockChannel(this); }
    leave() {}
    leaveChannel() {}
    disconnect() {}
}
