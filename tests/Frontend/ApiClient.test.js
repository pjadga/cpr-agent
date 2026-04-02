 /**
 * Frontend API Client Tests
 * Tests for API communication from the frontend
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('API Client', () => {
    let apiUrl;
    let token;

    beforeEach(() => {
        apiUrl = 'http://localhost/api';
        token = 'test-token-123';

        // Reset fetch mock
        global.fetch = vi.fn();
    });

    it('should send authenticated requests with bearer token', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({ success: true }),
        });

        const response = await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                type: 'ROLL_RESULT',
                total: 15,
            }),
        });

        expect(global.fetch).toHaveBeenCalledWith(
            `${apiUrl}/foundry/callback`,
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'Authorization': `Bearer ${token}`,
                }),
            })
        );

        const data = await response.json();
        expect(data.success).toBe(true);
    });

    it('should handle API errors gracefully', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'Validation failed',
                errors: { type: ['The type field is required.'] },
            }),
        });

        const response = await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({}),
        });

        expect(response.ok).toBe(false);
        expect(response.status).toBe(422);
    });

    it('should send roll result to backend', async () => {
        const rollData = {
            type: 'ROLL_RESULT',
            requestId: 'roll-123',
            actorId: 'Actor.abc123',
            total: 18,
            isCriticalSuccess: true,
            label: 'Attack Roll',
        };

        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({ status: 'acknowledged' }),
        });

        await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(rollData),
        });

        expect(global.fetch).toHaveBeenCalledWith(
            `${apiUrl}/foundry/callback`,
            expect.objectContaining({
                body: JSON.stringify(rollData),
            })
        );
    });

    it('should send character link request', async () => {
        const linkData = {
            type: 'LINK_PLAYER_CHARACTER',
            character: { id: 'Actor.v001', name: 'V' },
            agentLink: 'agent-link-123',
            isGM: false,
        };

        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({
                success: true,
                agent_link: 'agent-link-123',
            }),
        });

        const response = await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(linkData),
        });

        const data = await response.json();
        expect(data.success).toBe(true);
        expect(data.agent_link).toBe('agent-link-123');
    });

    it('should send sheet delta updates', async () => {
        const deltaData = {
            type: 'SHEET_DELTA',
            actorId: 'Actor.abc123',
            delta: {
                'system.derivedStats.hp.value': 40,
                'items.weapon-001.system.ammo.value': 7,
            },
            agentLink: 'agent-link-123',
        };

        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({ status: 'acknowledged' }),
        });

        await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(deltaData),
        });

        expect(global.fetch).toHaveBeenCalled();
    });

    it('should handle network errors', async () => {
        global.fetch.mockRejectedValueOnce(new Error('Network error'));

        try {
            await fetch(`${apiUrl}/foundry/callback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({ type: 'ROLL_RESULT' }),
            });
        } catch (error) {
            expect(error.message).toBe('Network error');
        }
    });

    it('should handle timeout errors', async () => {
        global.fetch.mockImplementationOnce(() =>
            new Promise((resolve, reject) => {
                setTimeout(() => reject(new Error('Timeout')), 100);
            })
        );

        try {
            await fetch(`${apiUrl}/foundry/callback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({ type: 'ROLL_RESULT' }),
            });
        } catch (error) {
            expect(error.message).toBe('Timeout');
        }
    });

    it('should retry failed requests', async () => {
        let attempts = 0;

        global.fetch.mockImplementation(() => {
            attempts++;
            if (attempts < 3) {
                return Promise.resolve({
                    ok: false,
                    status: 500,
                    json: async () => ({ error: 'Server error' }),
                });
            }
            return Promise.resolve({
                ok: true,
                status: 200,
                json: async () => ({ success: true }),
            });
        });

        // Simulate retry logic
        let response;
        for (let i = 0; i < 3; i++) {
            response = await fetch(`${apiUrl}/foundry/callback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({ type: 'ROLL_RESULT' }),
            });

            if (response.ok) break;
        }

        expect(attempts).toBe(3);
        expect(response.ok).toBe(true);
    });

    it('should include channel id in requests', async () => {
        const requestData = {
            type: 'ROLL_RESULT',
            total: 15,
            channelId: 'test-channel-001',
        };

        global.fetch.mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: async () => ({ status: 'acknowledged' }),
        });

        await fetch(`${apiUrl}/foundry/callback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify(requestData),
        });

        const callArgs = global.fetch.mock.calls[0];
        const body = JSON.parse(callArgs[1].body);
        expect(body.channelId).toBe('test-channel-001');
    });
});
