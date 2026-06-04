<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ActorSheet extends FormRequest
{
    use HasConfiguration;

    /**
     * Retrieves and cleans character data for the Vue-ready sheet.
     */
    public function getCleanData(Request $request)
    {
        $user = auth()->user();

        // Try Redis cache first (High Speed)
        $characterData = Cache::get("user_char_{$user->id}");

        // Save character JSON data if provided in the request
        if ($request->has('actorData')) {
            $characterData = $request->get('actorData');
            Cache::put("user_char_{$user->id}", $characterData, 3600);

            $update = [
                'character_data' => $characterData,
            ];

            // If the user doesn't have an agent-link, create one now.
            if (empty($user->agent_link)) {
                $update['agent_link'] = (string) Str::uuid();
            }

            $user->update($update);
            $user->refresh();
        }

        if (empty($characterData)) {
            $characterData = $user->character_data;
        }

        // Ensure we only return data for the assigned character if one is set
        if ($user->default_actor_id && ! empty($characterData)) {
            // Verify that the cached data matches the assigned character
            if (($characterData['_id'] ?? '') !== $user->default_actor_id) {
                $characterData = null;
            }
        }

        $isOffline = $request->has('actorData');

        if (empty($characterData)) {
            return response()->json([
                'success' => false,
                'message' => 'Data successfully submitted!',
                'error' => "Can't Find Actor and no offline data available",
            ], 400);
        }

        return [
            // Unique ID for the current data state
            'requestId' => uniqid('entity_', true),

            'isOffline' => $isOffline,

            'type' => $characterData['type'] ?? 'character',
            'uuid' => $characterData['_id'] ?? '',
            'tokenId' => $user->default_token_id,
            'agentLink' => $user->agent_link,
            'isGM' => (bool) $user->is_gm,

            'data' => [
                'name' => $characterData['name'] ?? 'Unknown Character',
                'img' => $characterData['img'] ?? '',

                'system' => [
                    'stats' => $characterData['system']['stats'] ?? [],
                    'derivedStats' => $characterData['system']['derivedStats'] ?? [],
                    'reputation' => $characterData['system']['reputation'] ?? ['value' => 0],
                    'wealth' => $characterData['system']['wealth'] ?? ['value' => 0],
                    'transactions' => $characterData['system']['wealth']['transactions'] ?? [],
                    'improvementPoints' => $characterData['system']['improvementPoints'] ?? ['value' => 0],
                    'roleInfo' => $characterData['system']['roleInfo'] ?? [],
                    'lifepath' => $characterData['system']['lifepath'] ?? [],
                    'information' => $characterData['system']['information'] ?? ['notes' => ''],
                    'externalData' => $characterData['system']['externalData'] ?? [],
                ],

                // Process and whitelist item fields
                'items' => $this->processItems($characterData['items'] ?? []),
            ],
        ];
    }

    /**
     * Iterates through items and cleans them using array_map
     */
    private function processItems(array $items)
    {
        return array_map([$this, 'cleanItem'], $items);
    }

    /**
     * Whitelists specific item fields to keep the payload small
     */
    private function cleanItem($item)
    {
        $sys = $item['system'] ?? [];

        return [
            '_id' => $item['_id'] ?? null,
            'name' => $item['name'] ?? 'Unknown Item',
            'type' => $item['type'] ?? 'misc',
            'img' => $item['img'] ?? '',

            'system' => [
                // Text & Econ
                'description' => [
                    'value' => $sys['description']['value'] ?? '',
                ],
                'price' => [
                    'market' => $sys['price']['market'] ?? 0,
                ],
                'amount' => $sys['amount'] ?? 0,
                'source' => $sys['source'] ?? 0,
                'concealable' => $sys['concealable'] ?? false,

                // Skill Specifics
                'level' => $sys['level'] ?? 0,
                'stat' => $sys['stat'] ?? '',
                'category' => $sys['category'] ?? '', // Important for your new grouping logic
                'skillType' => $sys['skillType'] ?? '',
                'abilities' => $sys['abilities'] ?? [],

                // Combat / Weapon Specifics
                'damage' => $sys['damage'] ?? '',
                'weaponSkill' => $sys['weaponSkill'] ?? '',
                'weaponType' => $sys['weaponType'] ?? '',
                'rof' => $sys['rof'] ?? 0,
                'magazine' => $sys['magazine'] ?? 0,
                'ammoVariety' => $sys['ammoVariety'] ?? [],
                'variety' => $sys['variety'] ?? '',
                'ammoType' => $sys['ammoType'] ?? '',
                'equipped' => $sys['equipped'] ?? '',
                'isWeapon' => $sys['isWeapon'] ?? false,
                'fireModes' => $sys['fireModes'] ?? [],

                // Cyberware Specifics
                'installLocation' => $sys['installLocation'] ?? '',
                'installedItems' => $sys['installedItems'] ?? [], // Keeps slot data
                'cyberwareType' => $sys['type'] ?? '',
                'class' => $sys['class'] ?? '',
                'isRezzed' => $sys['isRezzed'] ?? false,
                'def' => $sys['def'] ?? 0,
                'atk' => $sys['atk'] ?? 0,
                'rez' => $sys['rez'] ?? 0,
                'per' => $sys['per'] ?? 0,
                'spd' => $sys['spd'] ?? 0,

                // Armor Specifics
                'armor-head' => $sys['headLocation'] ?? [],
                'armor-body' => $sys['bodyLocation'] ?? [],
                'isHeadLocation' => $sys['isHeadLocation'] ?? [],
                'isBodyLocation' => $sys['isBodyLocation'] ?? [],
                'isShield' => $sys['isShield'] ?? false,
                'shieldHitPoints' => $sys['shieldHitPoints'] ?? [],

                // Role Specifics
                'rank' => $sys['rank'] ?? 0,
                'mainRoleAbility' => $sys['mainRoleAbility'] ?? '',
                'usage' => $sys['usage'] ?? '',

                // Critical Injury Specifics
                'quickFix' => $sys['quickFix'] ?? null,
                'treatment' => $sys['treatment'] ?? null,

            ],
        ];
    }
}
