<?php

namespace App\Http\Controllers\Data;

use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActorSheet extends FormRequest
{

    /**
     * Main entry point to get the simplified Vue-ready JSON
     */
    public function getCleanData(Request $request)
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'actorUuid' => 'required|string|max:255'
        ]);

        $client = new FoundryClient("test-api-key", "https://foundry-api.surrealdisco.co.uk");
        $data = $client->entity->getActorSheetAsJson("foundry-xECODN3n8rO4E5Cj", $validatedData["actorUuid"]);

        $characterData = $data['data'] ?? [];

        if (empty($characterData)) {
            return ["error" => "Invalid Character Data Structure"];
        }

        return [
            // Generate a request ID for tracking (useful for React/Vue keys)
            'requestId' => uniqid('entity_'),

            // Pass through IDs
            'type'      => $this->rawJson['type'] ?? 'character',
            'uuid'      => $this->rawJson['uuid'] ?? '',

            // The core data object your Vue app expects
            'data'      => [
                'name' => $characterData['name'] ?? 'Unknown Character',
                'img'  => $characterData['img'] ?? $this->rawJson['img'] ?? '', // Sometimes img is on root, sometimes in data

                'system' => [
                    'stats'             => $characterData['system']['stats'] ?? [],
                    'derivedStats'      => $characterData['system']['derivedStats'] ?? [],
                    'reputation'        => $characterData['system']['reputation'] ?? ['value' => 0],
                    'wealth'            => $characterData['system']['wealth'] ?? ['value' => 0],
                    'improvementPoints' => $characterData['system']['improvementPoints'] ?? ['value' => 0],
                    'roleInfo'          => $characterData['system']['roleInfo'] ?? [],

                    // Crucial for the "Equipped" section in your Vue app
                    'externalData'      => $characterData['system']['externalData'] ?? [],
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
            '_id'    => $item['_id'] ?? null,
            'name'   => $item['name'] ?? 'Unknown Item',
            'type'   => $item['type'] ?? 'misc',
            'img'    => $item['img'] ?? '',

            'system' => [
                // Text & Econ
                'description' => [
                    'value' => $sys['description']['value'] ?? ''
                ],
                'price' => [
                    'market' => $sys['price']['market'] ?? 0
                ],

                // Skill Specifics
                'level'     => $sys['level'] ?? 0,
                'stat'      => $sys['stat'] ?? '',
                'category'  => $sys['category'] ?? '', // Important for your new grouping logic
                'skillType' => $sys['skillType'] ?? '',

                // Combat / Weapon Specifics
                'damage'      => $sys['damage'] ?? '',
                'weaponSkill' => $sys['weaponSkill'] ?? '',
                'weaponType'  => $sys['weaponType'] ?? '',
                'rof'         => $sys['rof'] ?? 0,

                // Cyberware Specifics
                'installLocation' => $sys['installLocation'] ?? '',
                'installedItems'  => $sys['installedItems'] ?? [], // Keeps slot data

                // Armor Specifics
                'sp' => $sys['sp'] ?? 0,

                // Role Specifics
                'rank'            => $sys['rank'] ?? 0,
                'mainRoleAbility' => $sys['mainRoleAbility'] ?? '',
            ]
        ];
    }
}
