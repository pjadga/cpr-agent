<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import { vIcon } from '../../directives/icon';

const props = defineProps({
    value: { type: Number, default: 0 },
    transactions: { type: Array, default: () => [] },
});

const sortedTransactions = computed(() => {
    return [...props.transactions].reverse();
});

const emit = defineEmits(['updated']);

const amount = ref(null);
const reason = ref('');
const busy = ref(false);
const error = ref('');

const canSubmit = computed(
    () => !busy.value && Number.isFinite(Number(amount.value)) && Number(amount.value) >= 0,
);

async function submit(action) {
    if (!canSubmit.value) {
        error.value = 'Enter a valid number';
        return;
    }
    error.value = '';
    busy.value = true;
    try {
        await axios.post('/update-eb', {
            action,
            value: Number(amount.value),
            reason: reason.value,
        });
        amount.value = null;
        reason.value = '';
        emit('updated');
        if (action === 'set' || action === 'add' || action === 'subtract') {
            // Give it a tiny bit of time for Foundry to process and Echo to maybe fire,
            // though refreshSheet will handle it anyway.
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to update eurobucks';
    } finally {
        busy.value = false;
    }
}

function formatAmount(t) {
    // ledger records typically carry the signed delta; show "+" for positives
    // the array from the actor sheet is system.wealth.transactions, and has the amount change and reason in a tuple [amount, reason]
    const val = Array.isArray(t) ? t[0] : (t.value ?? t.amount ?? 0);
    const n = parseFloat(val);
    if (isNaN(n)) return val;
    return n > 0 ? `+${n}` : `${n}`;
}
</script>

<template>
    <section class="eurobucks-panel" @click="$emit('updated')">
        <div class="w-full max-w-xs bg-cpr-black-deep border border-cpr-border shadow-2xl" @click.stop>
            <div class="flex items-center justify-between px-3 py-2 border-b border-cpr-border bg-cpr-black/40">
                <span class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Eurobucks</span>
                <button class="text-cpr-text-muted hover:text-cpr-yellow" @click="$emit('updated')">
                    <i v-icon data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                <div class="text-center">
                    <div class="text-xs text-cpr-text-dim uppercase font-bold tracking-widest">Current Wealth</div>
                    <div class="text-4xl font-black font-numbers text-cpr-lime">€$ {{ value }}</div>
                </div>

                <div class="space-y-2">
                    <input
                        v-model.number="amount"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="Amount"
                        class="w-full bg-cpr-black border border-cpr-border px-3 py-2 text-cpr-yellow placeholder-gray-600 focus:outline-none focus:border-cpr-yellow/50"
                    />
                    <input
                        v-model="reason"
                        type="text"
                        placeholder="Reason (optional)"
                        class="w-full bg-cpr-black border border-cpr-border px-3 py-2 text-cpr-yellow placeholder-gray-600 focus:outline-none focus:border-cpr-yellow/50"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <button type="button" :disabled="!canSubmit" @click="submit('add')"
                            class="w-full py-2 bg-green-600/10 border border-green-600 text-cpr-lime text-xs font-bold uppercase hover:bg-green-600 hover:text-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Add Funds
                    </button>
                    <button type="button" :disabled="!canSubmit" @click="submit('subtract')"
                            class="w-full py-2 bg-cpr-red/10 border border-cpr-red text-cpr-red text-xs font-bold uppercase hover:bg-cpr-red hover:text-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Remove Funds
                    </button>
                    <button type="button" :disabled="!canSubmit" @click="submit('set')"
                            class="w-full py-2 bg-cpr-yellow/10 border border-cpr-yellow text-cpr-yellow text-xs font-bold uppercase hover:bg-cpr-yellow hover:text-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Set Total
                    </button>
                </div>

                <p v-if="error" class="text-cpr-red text-[10px] uppercase font-bold text-center italic">{{ error }}</p>

                <div class="eb-ledger border-t border-cpr-border pt-3">
                    <h4 class="text-[10px] font-bold text-cpr-text-dim uppercase mb-2 tracking-widest">Transaction History</h4>
                    <ul v-if="sortedTransactions && sortedTransactions.length" class="space-y-1">
                        <li v-for="(t, i) in sortedTransactions" :key="i" class="flex justify-between items-baseline gap-2">
                            <span class="font-numbers text-sm" :class="parseFloat(Array.isArray(t) ? t[0] : (t.value ?? t.amount)) < 0 ? 'text-cpr-red' : 'text-cpr-lime'">
                                {{ formatAmount(t) }}
                            </span>
                            <span class="text-[10px] text-cpr-text-dim truncate flex-1 text-right">{{ (Array.isArray(t) ? t[1] : t.reason) || '—' }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-[10px] text-cpr-text-dim italic text-center py-2">No transactions recorded.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.eb-ledger {
    max-height: 150px;
    overflow-y: auto;
}

/* Custom scrollbar for the ledger */
.eb-ledger::-webkit-scrollbar {
    width: 2px;
}
.eb-ledger::-webkit-scrollbar-track {
    background: transparent;
}
.eb-ledger::-webkit-scrollbar-thumb {
    background: #444;
}
.eb-ledger::-webkit-scrollbar-thumb:hover {
    background: #666;
}
</style>
