# CPR Agent - [Connect](https://agent.surrealdisco.co.uk/)

# Cyberpunk Red Companion App

A hostable companion app for Cyberpunk Red, designed to bridge the gap between players at a physical table and a Foundry VTT session. This app displays character stats, handles rolls, and allows for token interaction directly from a mobile device or desktop application.

## State
Currently functioning but actively being worked on. 
### Known issues/upcoming features
* Ping targets not working yet.
* Long goal - Originally I had the Agent idea where there's more of a 'phone' aspect. I think for RP purposes the GM could upload scream sheets, stories, add phone numbers of fixers/contacts etc. Getting a text mid gig from a fixer telling em to gtfo would be pretty ace. I'm imagining it working with notes + handouts. Though I feel this is on my way to making a Foundry module of my own...

## Features
* **Character Management**: Displays relevant character stats, skills, and roles, all rollable from the app.
* **Gear & Cyberware**: Displays equipped gear, ammo count, costs, damage, and descriptions in an easy-to-read format. Equipped states & ammo types are now changeable.
* **ER Mission Kit rolls**: Currently supports Quickhack (un-named, will update as they are supported within the CPR Module), Jack in + Breach.
* **Token Control**: Directional control of tokens via an on-screen D-pad.
* **Combat Support**: 
    * Listed tokens on map with relative positions.
    * Calculated DV values per weapon.
    * Weapon selection, reloading, and multiple firing modes.
    * Hit and Damage rolls based on target and selected weapon.
* **Netrunning Support**
  * Cyberdeck configuration - rez/de-rez and installation of programs.
  * Net actions and Programs rollable.
*  **Local Caching within Foundry**
*  **Dice Roll returns**
*  **Lifepaths and Notes section available**
*  **Item carry control (Carry, equip)_**
*  **Critical Injuries list (ouch)**
*  **Dice Roller + Dice Modifiers**

# Please check the (Guide)[GUIDE.md] for an indepth look at setup, self-hosting and technicals

## Technical Foundation
This Agent is built with Vue.js, PHP (Laravel along with Reverb + Sanctum), Tailwind CSS, and a ton of JS to make the Foundry Module. I used to use the [ThreeHats API](https://github.com/ThreeHats/foundryvtt-rest-api) and my own [PHP Wrapper](https://packagist.org/packages/surrealtech/threehats-api-php-wrapper)
to take care of the communication, however I wanted more control over returns and data storage! Please give them a look if a more system agnostic API is what you're looking for.
