import { ref, onMounted } from 'vue';

const netPoolData = ref([]);

export function useNetPool() {
    const fetchNetPool = async () => {
        try {
            await window.axios.post('/api/get-net-pool');
        } catch (err) {
            console.error('Error fetching net pool:', err);
        }
    };

    const handleNetPoolData = (payload) => {
        if (payload.type === 'NET_POOL_DATA' && payload.pools) {
            netPoolData.value = payload.pools;
        }
    };

    return {
        netPoolData,
        fetchNetPool,
        handleNetPoolData
    };
}
