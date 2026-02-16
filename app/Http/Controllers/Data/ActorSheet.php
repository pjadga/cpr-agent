<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActorSheet extends FormRequest
{
    use HasConfiguration;

    /**
     * Main entry point to get the simplified Vue-ready JSON
     */
    public function getCleanData(Request $request)
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'actorUuid' => 'required|string|max:255'
        ]);

        $user = auth()->user();
        $characterData = $user->character_data;
        $isOffline = true; // Defaulting to true as we are not fetching live data here anymore

        if (empty($characterData)) {
            return response()->json([
                'success' => false,
                'message' => 'Data successfully submitted!',
                'error' => "Can't Find Actor and no offline data available",
            ], 400);
        }

        return [
            // Generate a request ID for tracking (useful for React/Vue keys)
            'requestId' => uniqid('entity_'),

            'isOffline' => $isOffline,

            // Pass through IDs
            'type' => $characterData['type'] ?? 'character',
            'uuid' => $characterData['uuid'] ?? '',

            // The core data object your Vue app expects
            'data' => [
                'name' => $characterData['name'] ?? 'Unknown Character',
                'img' => $characterData['img'] ?? '',

                'system' => [
                    'stats' => $characterData['system']['stats'] ?? [],
                    'derivedStats' => $characterData['system']['derivedStats'] ?? [],
                    'reputation' => $characterData['system']['reputation'] ?? ['value' => 0],
                    'wealth' => $characterData['system']['wealth'] ?? ['value' => 0],
                    'improvementPoints' => $characterData['system']['improvementPoints'] ?? ['value' => 0],
                    'roleInfo' => $characterData['system']['roleInfo'] ?? [],
                    // Crucial for the "Equipped" section in your Vue app
                    'externalData' => $characterData['system']['externalData'] ?? [],
                ],

                // Process and clean the items array
                'items' => $this->processItems($characterData['items'] ?? [])
            ]
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
                    'value' => $sys['description']['value'] ?? ''
                ],
                'price' => [
                    'market' => $sys['price']['market'] ?? 0
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
                'isShield' => $sys['isShield'] ?? false,
                'shieldHitPoints' => $sys['shieldHitPoints'] ?? [],


                // Role Specifics
                'rank' => $sys['rank'] ?? 0,
                'mainRoleAbility' => $sys['mainRoleAbility'] ?? '',

                'usage' => $sys['usage'] ?? '',
            ]
        ];
    }
}
