<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use App\Support\Foundry\FoundryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetRollModifiers extends FormRequest
{
    use HasConfiguration;

    public function getRollModifiers(Request $request)
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();

        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255',
        ]);

        // Use user's client_id if available, otherwise fallback to a default or dummy for testing
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $weaponId = $validatedData['selectedWeapon']["_id"];

        //language=JS
        $js = "
        const actorId = '$actorId';
        const weaponId = '$weaponId';

        const actor = game.actors.get(actorId);
        const weapon = actor?.items?.get(weaponId);

        if (!actor || !weapon) {
            return { error: 'Actor or weapon not found' };
        }

        // Get stats, skills and mods
        const weaponSkillName = weapon.system.weaponSkill;
        const skillItem = actor.items.find(i => i.type === 'skill' && i.name === weaponSkillName);
        const skillLevel = skillItem ? (Number(skillItem.system.level)||0) : 0;
        const statName = skillItem ? (skillItem.system.stat || 'ref') : 'ref';
        const statValue = Number(actor.system.stats[statName]?.value) || 0;
        const weaponAcc = Number(weapon.system.attackmod) || 0;

        let totalPenalty = 0;
        const effectList = [];

        // Get HP and work out wounded states. Take into account stims
        const hp = Number(actor.system.derivedStats.hp.value) || 0;
        const seriouslyThreshold = Number(actor.system.derivedStats.seriouslyWounded) || 0;
        const stim = actor.items.find(i => i.name === 'Stim' && i.type === 'drug');
        const stimEnabled = stim && stim.effects.some(e => e.disabled === false);

        if (hp <= 0) {
            totalPenalty -= 4;
            effectList.push({ title: 'Mortally Wounded', penalty: -4, description: 'All actions at -4.' });
        } else if (hp <= seriouslyThreshold) {
            const penaltyValue = stimEnabled ? 0 : -2;
            totalPenalty += penaltyValue;
            effectList.push({
                title: stimEnabled ? 'Seriously Wounded (Stim Active)' : 'Seriously Wounded',
                penalty: penaltyValue,
                description: stimEnabled ? 'Ignoring penalty due to Stim drug.' : 'All actions at -2.'
            });
        }

        //Crit Injury addition - heuristic based on text
        const critInjuries = actor.items.filter(i => i.type === 'criticalInjury');
        critInjuries.forEach(injury => {
            const title = injury.name || 'Injury';
            const description = (injury.system.description?.value || '').replace(/<[^>]*>/g, '');

            let injuryPenalty = 0;
            const lowerTitle = String(title).toLowerCase();
            if (description.includes('-2') || lowerTitle.includes('arm') || lowerTitle.includes('eye')) {
                injuryPenalty = -2;
            }

            totalPenalty += injuryPenalty;
            effectList.push({ title, description, penalty: injuryPenalty });
        });

        // Final Object for App with breakdown
        const result = {
            actorName: actor.name,
            baseBonus: statValue + skillLevel + weaponAcc,
            breakdown: {
                statName,
                statValue,
                skillName: weaponSkillName,
                skillLevel,
                weaponAcc
            },
            totalPenalty: totalPenalty,
            finalModifier: statValue + skillLevel + weaponAcc + totalPenalty,
            injuries: effectList
        };

        return result;
        ";

        // Cache for a very short time (character states can change frequently)
        $cacheKey = sprintf('rollmods:%s:%s:%s', $clientId, $actorId, $weaponId);
        $ttl = now()->addSeconds(3);
        $return = Cache::remember($cacheKey, $ttl, function () use ($client, $clientId, $js) {
            return $client->utilities->executeJs($clientId, $js);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return
        ], 201);
    }
}
