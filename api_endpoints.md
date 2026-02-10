### Possible API Endpoints


#### 1. Actor Management
*   **`GET /actors/{actorId}`**: Retrieve full actor data (stats, skills, derived stats, inventory).
*   **`PATCH /actors/{actorId}`**: Update actor fields (e.g., current HP, Humanity, Luck, Eurobucks).
*   **`GET /actors/{actorId}/skills`**: Retrieve a list of all skills and their current levels.
*   **`GET /actors/{actorId}/inventory`**: Retrieve all items in the actor's inventory, filtered by type or status (equipped).

#### 2. Rolls & Actions - All rolls need to return their rolled value (and modifiers?)
*   **`POST /actors/{actorId}/roll/stat/{statName}`**: Trigger a stat roll.
*   **`POST /actors/{actorId}/roll/skill/{skillName}`**: Trigger a skill roll.
*   **`POST /actors/{actorId}/roll/weapon/{weaponId}`**: Trigger an attack roll with a specific weapon.
*   **`POST /actors/{actorId}/roll/damage/{weaponId}`**: Trigger a damage roll for a specific weapon.
*   **`POST /actors/{actorId}/roll/facedown`**: FACEDOWN
*   **`POST /actors/{actorId}/roll/role/{roleSubtype}`**: Role Roll(tm) and subtype if necessary
*   **`POST /actors/{actorId}/roll/deathsave`**: Actor death save
*   **`POST /actors/{actorId}/roll/interface`**: Interface abilities.
*   **`POST /actors/{actorId}/roll/program/{programName}/attack`**: Roll Program attack
*   **`POST /actors/{actorId}/roll/program/{programName}/damage`**: Roll Program damage
*   **`POST /actors/{actorId}/roll/program/{programName}/defense`**: Roll Program defense

#### 2.5. Netspace
*   **`Get /actors/{actorId}/programs`**: List purchased programs
*   **`Get /actors/{actorId}/program/{programName}`**: Retrieves program data (stats/rezzed/loaded etc)
*   **`POST /actors/{actorId}/program/{programName}/rez`**: Rez Program
*   **`POST /actors/{actorId}/program/{programName}/derez`**: Derez program
*   **`POST /actors/{actorId}/program/{programName}/install`**: Install Program
*   **`POST /actors/{actorId}/program/{programName}/remove`**: Remove program

#### 3. Combat & State
*   **`POST /actors/{actorId}/combat/initiative`**: Roll initiative for the actor.
*   **`POST /actors/{actorId}/weapon/{weaponId}/reload`**: Reload a specific weapon.
*   **`GET /dv-tables/{weaponType}`**: Retrieve DV tables for different weapon types at various ranges. (This is already hardcoded - may not be needed)

#### 4. Item Management
*   **`GET /items/{itemId}`**: Retrieve details of a specific item/weapon/gear.
*   **`PATCH /items/{itemId}`**: Update item properties (e.g., remaining ammo, reliability).
*   **`POST /actors/{actorId}/inventory/equip/{itemId}`**: Toggle the equipped status of an item.

#### 5. Hooks (Poss for my module)
* Character sheet has changed - data object of Actor/ID and item that has changed. Used in updating Char sheets

##### My own future module
Any GET call needs to run through a caching system. There's so much to be saved between the app and Foundry without a full Actor sheet being sent 
each time. 
