/**
 * Frontend test setup file
 * This file runs before all frontend tests
 */

// Mock window.Echo for Laravel Echo tests
global.Echo = {
    private: () => ({
        listen: () => ({}),
    }),
    connector: {
        pusher: {
            connection: {
                bind: () => {
                },
            },
        },
    },
    disconnect: () => {
    },
};

// Mock window.Pusher
global.Pusher = class Pusher {
    constructor() {
        this.connection = {
            bind: () => {
            },
        };
    }
};

// Mock localStorage
const localStorageMock = {
    getItem: (key) => null,
    setItem: (key, value) => {
    },
    removeItem: (key) => {
    },
    clear: () => {
    },
};
global.localStorage = localStorageMock;

// Mock sessionStorage
global.sessionStorage = localStorageMock;

// Mock fetch for API calls
global.fetch = async (url, options) => {
    return {
        ok: true,
        status: 200,
        json: async () => ({success: true}),
        text: async () => 'OK',
    };
};
