# CPR Agent - [Connect](https://agent.surrealdisco.co.uk/)

# How to Use
* Save the page to your Homescreen if using a mobile or tablet. It will remove the address bar and it will have more of an 'app' feel.
* On Safari, Hit the 3 dots in the bottom right -> Share -> Scroll Down -> Add to Home Screen.
* From Google: "
  To the right of the address bar, tap More More and then Add to home screen and then Create shortcut."

## 1. Preparation
* Ensure you have the [Cyberpunk Red module](https://foundryvtt.com/packages/cyberpunk-red-core) installed in Foundry VTT.
* Install the Cyberpunk Red Agent Bridge module using `https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json`

## 2. Account Setup
Sign up to this app. Your personal data is not used for any purposes other than authentication.
*Note: Account deletion is accessible at any time through your account settings.*

## 3. Configuration
1. Navigate to the **Settings** page (top right menu).
2. Generate an **API Key**. This will be unique to your Agent.
3. **GMs**: Copy your **Channel ID** from the Settings page and paste it into the **Cyberpunk Agent Bridge** module settings in Foundry VTT. Share this Channel ID with your players.
4. **Players**: Paste the **Channel ID** provided by your GM into your own Settings page and click **Commit Sync**.
5. **Players**: Paste your individual **API Key** into the **Cyberpunk Agent Bridge** module settings in Foundry VTT.

## 4. Character Assignment & Sync
1. In Foundry VTT, the GM uses the **Agent Character Assignment** tool within the Agent Bridge module.
2. The GM assigns a playable character to each player who has linked their API key.
3. Once assigned, the player's **Agent Link** (a long unique code) will appear in the menu.
4. The player is now **fully synced**. Character data, stats, and rolls will flow between Foundry and the Agent interface in real-time.


The CPR Agent Bridge was built with my VPS in mind, so it will come configured out of the box. However for those wishing to
self-host the agent app, here are the main ones to change:
* Controller URL - point towards the Agent host
* Host override - if you are hosting a separate Reverb proxy
* Reverb Port - Blank for auto-detection between ports 443/80 (TLS/insecure). If self hosting, use 8080.
* Reverb Use SSL - toggles 'wss' protocol vs 'ws'. Keep off if hosting without an SSL certificate.
* Reverb App Key - Leave as is (it's the public facing key for my server) or make you're own if self-hosting.
* API Key - This **has** to be generated via the Agent app (Laravel Sanctum). Saving a random string will not work.
* Debug mode - sweet console logs showing pretty much everything.


## Workarounds/Troubleshooting
* The Modifiers window opens in Foundry whenever I roll.
  * Head over to the Settings in Foundry - Cyberpunk RED - CORE, and toggle the "Invert Function of CTRL".
* There are only coloured squares for the images?
  * This is a fallback for if the Foundry server is either not running or on a http connection and unable to share images due to CORS. NGROK is supported, so if tunnelling; make sure the Foundry URL in Config does NOT have "/game" at the end. It needs to be the vanilla URL.

## A few notes on NGROK & Images not showing up
As the Agent app is hosted on a live VPS, images from a local Foundry setup will generally not work, unless the session is running on the same computer. 

The quickest (currently) way round this is to install Ngrok and using `ngrok http 30000` in a command line; host your local Foundry. This will provide a long URL that you can plug into the Agents Foundry URL in config.

Ngrok is specifically supported - I've added certain headers in the call to allow images through without triggering their splash page (and CORS responses). I'll be adding more in the future to try and get around the intial pain with setting up.


# Cyberpunk Red Companion App

A hostable companion app for Cyberpunk Red, designed to bridge the gap between players at a physical table and a Foundry VTT session. This app displays character stats, handles rolls, and allows for token interaction directly from a mobile device or desktop application.

## State
Currently functioning but actively being worked on. 
### Known issues/upcoming features
* Ping targets not working yet.
* Colour themes
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


### Availability
The app is available online at the link at the top, and via self-hosting on DDEV (details below on DDEV):
* **Self-Hosting**: Using DDEV (recommended for local/shared development).

### Self-Hosting with DDEV
For the best experience while self-hosting and sharing with your players:
1. Ensure you have [DDEV](https://ddev.readthedocs.io/) installed.
2. Start the project: `ddev start`. This will give you a URL to visit.
3. Install dependencies: `ddev composer install` and `ddev npm install`.
4. Providing there's been no errors, run `ddev artisan migrate:fresh`, `ddev npm run build`. This will refresh the database and build the scripts and styles.
5. Sharing with players over the internet - **Originally this was done with NGROK, however, due to the necessity for ports to be opened, the best way to share would be a CloudFlared Tunnel. Once I've tested the setup I will post back.**


## Technical Foundation
This Agent is built with Vue.js, PHP (Laravel along with Reverb + Sanctum), Tailwind CSS, and a ton of JS to make the Foundry Module. I used to use the [ThreeHats API](https://github.com/ThreeHats/foundryvtt-rest-api) and my own [PHP Wrapper](https://packagist.org/packages/surrealtech/threehats-api-php-wrapper)
to take care of the communication, however I wanted more control over returns and data storage! Please give them a look if a more system agnostic API is what you're looking for.
