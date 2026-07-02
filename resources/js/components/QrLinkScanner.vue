<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { useQrScanner } from '../composables/useQrScanner';
import {
    diagnostic,
    parseQrPayload,
    classifyClaimError,
    probeFoundry,
} from '../composables/useFoundryDiagnostics';

const props = defineProps({
    agentLink: { type: String, default: '' },
});

const { scanning, start, stop } = useQrScanner();

// idle | scanning | claiming | checking | success | error | warning
const state = ref('idle');
const errorCode = ref(null);
const warningCode = ref(null);
const sessionName = ref('');
const video = ref(null);
const pasteCode = ref('');

const fail = (code) => {
    stop();
    errorCode.value = code;
    state.value = 'error';
};

async function beginScan() {
    errorCode.value = null;
    warningCode.value = null;
    state.value = 'scanning';
    await start(video.value, onDecode, fail);
}

async function onDecode(text) {
    const { payload, error } = parseQrPayload(text);
    if (error) return fail(error);

    stop();
    state.value = 'claiming';
    await claim(payload.nonce);
}

// Manual paste path (PC / no camera): same validation + claim as a scan.
async function submitPaste() {
    const { payload, error } = parseQrPayload(pasteCode.value);
    if (error) return fail(error);

    state.value = 'claiming';
    await claim(payload.nonce);
}

async function claim(nonce) {
    let res;
    try {
        res = await window.axios.post('/api/pairing/claim', { nonce });
    } catch (e) {
        const status = e.response?.status ?? 0;
        return fail(classifyClaimError(status, e.response?.data));
    }

    const data = res.data || {};
    sessionName.value = data.foundry_user_name || data.channel_id || '';

    // Claim succeeded — the player is now configured. Verify Foundry reachability
    // and wait briefly for the GM's Bridge to confirm the link round-trip.
    state.value = 'checking';

    const reach = await probeFoundry(data.foundry_url);
    const confirmed = await waitForGmConfirmation(data.channel_id);

    if (reach) {
        // Reachability issues are warnings: data still syncs over the websocket.
        warningCode.value = reach;
        state.value = 'warning';
    } else if (!confirmed) {
        warningCode.value = 'no_gm_confirmation';
        state.value = 'warning';
    } else {
        state.value = 'success';
    }
}

/**
 * Best-effort: listen on the session channel for the GM Bridge's PAIR_CONFIRMED.
 * Resolves false on timeout or if Echo/subscription is unavailable.
 */
function waitForGmConfirmation(channelId, timeoutMs = 8000) {
    return new Promise((resolve) => {
        if (!channelId || !window.Echo) return resolve(false);

        let done = false;
        let channel;
        const finish = (ok) => {
            if (done) return;
            done = true;
            try { window.Echo.leave(`cpr-agent.${channelId}`); } catch { /* noop */ }
            resolve(ok);
        };

        try {
            channel = window.Echo.private(`cpr-agent.${channelId}`);
            channel.listen('.FoundryResponse', (e) => {
                const p = e?.payload || e || {};
                if (p.type === 'PAIR_CONFIRMED' && (!props.agentLink || p.agentLink === props.agentLink)) {
                    finish(true);
                }
            });
        } catch {
            return resolve(false);
        }

        setTimeout(() => finish(false), timeoutMs);
    });
}

function retry() {
    state.value = 'idle';
    errorCode.value = null;
    warningCode.value = null;
}

function done() {
    window.location.reload();
}

onBeforeUnmount(stop);
</script>

<template>
    <div class="cpr-qr-scanner">
        <!-- Idle -->
        <div v-if="state === 'idle'">
            <button
                type="button"
                @click="beginScan"
                class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-6 py-3 uppercase text-sm font-bold w-full"
            >
                <i class="fas fa-qrcode mr-2"></i> Scan to Link Foundry
            </button>

            <!-- No camera? Paste the GM's link code instead. -->
            <p class="text-cpr-text-dim text-xs mt-3 mb-1 uppercase tracking-wider">No camera? Paste the link code</p>
            <div class="flex gap-2 items-stretch">
                <input
                    v-model="pasteCode"
                    type="text"
                    placeholder="Paste link code from the GM"
                    class="cyber-input-field flex-1 font-mono text-xs"
                    @keyup.enter="pasteCode.trim() && submitPaste()"
                />
                <button
                    type="button"
                    :disabled="!pasteCode.trim()"
                    @click="submitPaste"
                    class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-5 uppercase text-xs font-bold disabled:opacity-40"
                >
                    Link
                </button>
            </div>
        </div>

        <!-- Scanning -->
        <div v-show="state === 'scanning'" class="text-center">
            <p class="text-cpr-text-dim text-sm mb-2">Point your camera at the GM's QR code…</p>
            <video
                ref="video"
                class="w-full max-w-xs mx-auto rounded-lg border-2 border-cpr-teal bg-black aspect-square object-cover"
                muted
            ></video>
            <button type="button" @click="retry" class="mt-3 text-xs text-cpr-text-dim underline">Cancel</button>
        </div>

        <!-- Working -->
        <div v-if="state === 'claiming' || state === 'checking'" class="text-center py-6">
            <i class="fas fa-spinner fa-spin text-cpr-teal text-2xl"></i>
            <p class="text-cpr-text-dim text-sm mt-2">
                {{ state === 'claiming' ? 'Linking your Agent…' : 'Checking the connection…' }}
            </p>
        </div>

        <!-- Success -->
        <div v-if="state === 'success'" class="text-center py-4 border-2 border-cpr-teal rounded-lg bg-cpr-teal-dark/20">
            <i class="fas fa-check-circle text-cpr-teal text-3xl"></i>
            <h3 class="text-cpr-teal font-bold uppercase tracking-widest text-sm mt-2">Linked!</h3>
            <p class="text-cpr-text-dim text-sm mt-1">Connected to {{ sessionName || 'your session' }}.</p>
            <button type="button" @click="done" class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-6 py-2 uppercase text-xs font-bold mt-3">
                Continue
            </button>
        </div>

        <!-- Warning (linked, but reachability/confirmation caveat) -->
        <div v-if="state === 'warning'" class="py-4 border-2 border-cpr-yellow rounded-lg bg-cpr-yellow/10 px-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle text-cpr-teal"></i>
                <h3 class="text-cpr-teal font-bold uppercase tracking-widest text-xs">Agent linked</h3>
            </div>
            <p class="text-cpr-yellow font-bold text-sm mt-3">{{ diagnostic(warningCode).title }}</p>
            <p class="text-cpr-text-dim text-xs mt-1">{{ diagnostic(warningCode).detail }}</p>
            <p class="text-cpr-text text-xs mt-2"><strong>Fix:</strong> {{ diagnostic(warningCode).hint }}</p>
            <button type="button" @click="done" class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-6 py-2 uppercase text-xs font-bold mt-3">
                Continue anyway
            </button>
        </div>

        <!-- Error -->
        <div v-if="state === 'error'" class="py-4 border-2 border-cpr-red-dark rounded-lg bg-cpr-red-dark/10 px-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-cpr-red"></i>
                <h3 class="text-cpr-red font-bold uppercase tracking-widest text-xs">{{ diagnostic(errorCode).title }}</h3>
            </div>
            <p class="text-cpr-text-dim text-xs mt-2">{{ diagnostic(errorCode).detail }}</p>
            <p class="text-cpr-text text-xs mt-2"><strong>Fix:</strong> {{ diagnostic(errorCode).hint }}</p>
            <button type="button" @click="retry" class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-6 py-2 uppercase text-xs font-bold mt-3 hover:bg-cpr-red">
                Try again
            </button>
        </div>
    </div>
</template>
