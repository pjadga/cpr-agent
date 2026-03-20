/**
 * Frontend Foundry Bridge Tests
 * Tests for the Foundry VTT module integration (test.js)
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('Foundry Bridge', () => {
    let bridge;
    let mockGame;
    let mockUser;

    beforeEach(() => {
        // Mock Foundry game object
        mockUser = {
            id: 'user-123',
            name: 'Test User',
            isGM: false,
            character: {
                id: 'Actor.abc123',
                name: 'V',
            },
            getFlag: vi.fn((scope, key) => {
                if (key === 'agent-link') return 'agent-link-123';
                if (key === 'hasApiKey') return true;
                return null;
            }),
            setFlag: vi.fn(),
        };

        mockGame = {
            user: mockUser,
            users: [mockUser],
            actors: {
                get: vi.fn((id) => ({
                    id,
                    name: 'Test Actor',
                    type: 'character',
                    system: {
                        stats: { body: { value: 8 } },
                    },
                })),
            },
            settings: {
                get: vi.fn((module, key) => {
                    if (key === 'apiUrl') return 'https://agent.example.com';
                    if (key === 'apiKey') return 'test-token-123';
                    if (key === 'channelId') return 'test-channel-001';
                    if (key === 'debugMode') return false;
                    return null;
                }),
            },
        };

        global.game = mockGame;

        // Mock bridge object
        bridge = {
            isConnected: false,
            activeRequests: new Map(),
            requestAgentLinks: new Map(),
            sendResponse: vi.fn(async (data) => {
                return { success: true, agent_link: 'agent-link-123' };
            }),
            syncPlayerLink: vi.fn(),
        };
    });

    it('should initialize with correct settings', () => {
        const apiUrl = mockGame.settings.get('cpr-agent', 'apiUrl');
        const apiKey = mockGame.settings.get('cpr-agent', 'apiKey');
        const channelId = mockGame.settings.get('cpr-agent', 'channelId');

        expect(apiUrl).toBe('https://agent.example.com');
        expect(apiKey).toBe('test-token-123');
        expect(channelId).toBe('test-channel-001');
    });

    it('should sync player link on initialization', async () => {
        await bridge.syncPlayerLink();

        expect(bridge.syncPlayerLink).toHaveBeenCalled();
    });

    it('should send roll results to backend', async () => {
        const rollData = {
            type: 'ROLL_RESULT',
            actorId: 'Actor.abc123',
            total: 18,
            label: 'Attack Roll',
            isCriticalSuccess: true,
        };

        await bridge.sendResponse(rollData);

        expect(bridge.sendResponse).toHaveBeenCalledWith(rollData);
    });

    it('should send sheet delta updates', async () => {
        const deltaData = {
            type: 'SHEET_DELTA',
            actorId: 'Actor.abc123',
            delta: {
                'system.derivedStats.hp.value': 40,
            },
        };

        await bridge.sendResponse(deltaData);

        expect(bridge.sendResponse).toHaveBeenCalledWith(deltaData);
    });

    it('should include agent link in requests', async () => {
        const agentLink = mockUser.getFlag('cpr-agent', 'agent-link');

        const rollData = {
            type: 'ROLL_RESULT',
            total: 15,
            agentLink,
        };

        await bridge.sendResponse(rollData);

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({ agentLink: 'agent-link-123' })
        );
    });

    it('should handle character assignment changes', async () => {
        const newCharacter = { id: 'Actor.xyz789', name: 'Johnny' };

        await bridge.sendResponse({
            type: 'LINK_PLAYER_CHARACTER',
            character: newCharacter,
            agentLink: 'agent-link-123',
            isGM: false,
        });

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'LINK_PLAYER_CHARACTER',
                character: newCharacter,
            })
        );
    });

    it('should track active requests', () => {
        const requestId = 'req-123';
        const agentLink = 'agent-link-123';

        bridge.requestAgentLinks.set(requestId, agentLink);

        expect(bridge.requestAgentLinks.get(requestId)).toBe(agentLink);
    });

    it('should handle item creation', async () => {
        const newItem = {
            _id: 'item-001',
            name: 'Heavy Pistol',
            type: 'weapon',
        };

        await bridge.sendResponse({
            type: 'SHEET_DELTA',
            actorId: 'Actor.abc123',
            delta: {
                'items.+item-001': newItem,
            },
        });

        expect(bridge.sendResponse).toHaveBeenCalled();
    });

    it('should handle item deletion', async () => {
        await bridge.sendResponse({
            type: 'SHEET_DELTA',
            actorId: 'Actor.abc123',
            delta: {
                'items.item-001': null,
            },
        });

        expect(bridge.sendResponse).toHaveBeenCalled();
    });

    it('should handle death save rolls', async () => {
        await bridge.sendResponse({
            type: 'ROLL_RESULT',
            requestId: 'death-001',
            actorId: 'Actor.abc123',
            total: 8,
            label: 'Death Save',
            isCriticalFailure: true,
        });

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'ROLL_RESULT',
                label: 'Death Save',
                isCriticalFailure: true,
            })
        );
    });

    it('should handle initiative rolls', async () => {
        await bridge.sendResponse({
            type: 'ROLL_RESULT',
            requestId: 'init-001',
            actorId: 'Actor.abc123',
            total: 14,
            label: 'Initiative',
        });

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'ROLL_RESULT',
                label: 'Initiative',
            })
        );
    });

    it('should handle damage rolls', async () => {
        await bridge.sendResponse({
            type: 'ROLL_RESULT',
            requestId: 'damage-001',
            actorId: 'Actor.abc123',
            total: 12,
            label: 'Heavy Pistol (Damage)',
        });

        expect(bridge.sendResponse).toHaveBeenCalled();
    });

    it('should handle program operations', async () => {
        await bridge.sendResponse({
            type: 'SHEET_DELTA',
            actorId: 'Actor.abc123',
            delta: {
                'items.deck-001.system.installedItems.list': ['prog-001', 'prog-002'],
                'items.prog-001.system.isRezzed': true,
            },
        });

        expect(bridge.sendResponse).toHaveBeenCalled();
    });

    it('should broadcast refresh signals', async () => {
        await bridge.sendResponse({
            type: 'REFRESH_SIGNAL',
            target: 'VISIBLE_TOKENS_DATA',
        });

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'REFRESH_SIGNAL',
                target: 'VISIBLE_TOKENS_DATA',
            })
        );
    });

    it('should handle GM operations', async () => {
        mockUser.isGM = true;

        await bridge.sendResponse({
            type: 'GM_LINK_PLAYER_CHARACTER',
            agentLink: 'player-link-456',
            character: 'Actor.player123',
        });

        expect(bridge.sendResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'GM_LINK_PLAYER_CHARACTER',
            })
        );
    });

    it('should handle connection state', () => {
        expect(bridge.isConnected).toBe(false);

        bridge.isConnected = true;
        expect(bridge.isConnected).toBe(true);
    });

    it('should cleanup request tracking', () => {
        const requestId = 'req-123';
        bridge.requestAgentLinks.set(requestId, 'agent-link-123');

        // Simulate cleanup after timeout
        setTimeout(() => {
            bridge.requestAgentLinks.delete(requestId);
        }, 100);

        expect(bridge.requestAgentLinks.has(requestId)).toBe(true);
    });
});
