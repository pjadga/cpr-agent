# How to Use
* Save the page to your Homescreen if using a mobile or tablet. It will remove the address bar and it will have more of an 'app' feel.
* On Safari, Hit the 3 dots in the bottom right -> Share -> Scroll Down -> Add to Home Screen.
* From Google: "
  To the right of the address bar, tap More More and then Add to home screen and then Create shortcut."

## 1. Preparation
* Ensure you have the [Cyberpunk Red module](https://foundryvtt.com/packages/cyberpunk-red-core) installed in Foundry VTT.
* Install the [ThreeHats API module](https://foundryvtt.com/packages/foundry-rest-api) in your Foundry VTT instance.

## 2. Account Setup
Sign up to this app. Your personal data is not used for any purposes other than authentication.
*Note: Account deletion is accessible at any time through your account settings.*

## 3. Configuration
1. Navigate to the **Config** page.
2. Input your **Foundry URL**.
3. Input your **API Key** (this must be the same one added to your Foundry ThreeHats module).
4. Click **Save**.

## 4. Foundry Module Settings
In the ThreeHats module settings within Foundry VTT, add the following as the relay link:
`wss://agent-relay.surrealdisco.co.uk`

*This relay is self-hosted through IONOS on a VPS. Call frequency is monitored to improve the app and manage costs.*

## 5. Connecting Characters
1. Once the GM account is up and running, refresh the **Config** page.
2. A **Foundry Client** should now appear in the dropdown menu.
3. Selecting that client will load the **Playable Characters** and **Player Owned characters** currently in the session.

## Workarounds
* The Modifiers window opens in Foundry whenever I roll.
  * Head over to the Settings in Foundry - Cyberpunk RED - CORE, and toggle the "Invert Function of CTRL".


# Cyberpunk Red Companion App

A hostable companion app for Cyberpunk Red, designed to bridge the gap between players at a physical table and a Foundry VTT session. This app displays character stats, handles rolls, and allows for token interaction directly from a mobile device or desktop application.

## State
Currently functioning but actively being worked on. 
### Known issues/upcoming features
* Weapon equip/conceal - The Control section shows all weapons whether they are 'equipped' or not. I'm not sure if this has any impact further than RP.
* Ping targets not working yet.
* Colour themes
* Long goal - Originally I had the Agent idea where there's more of a 'phone' aspect. I think for RP purposes the GM could upload scream sheets, stories, add phone numbers of fixers/contacts etc. Getting a text mid gig from a fixer telling em to gtfo would be pretty ace. I'm imagining it working with notes + handouts. Though I feel this is on my way to making a Foundry module of my own...

## Features
* **Character Management**: Displays relevant character stats, skills, and roles, all rollable from the app.
* **Gear & Cyberware**: Displays equipped gear, ammo count, costs, damage, and descriptions in an easy-to-read format.
* **Token Control**: Directional control of tokens via an on-screen D-pad.
* **Combat Support**: 
    * Listed tokens on map with relative positions.
    * Calculated DV values per weapon.
    * Weapon selection, reloading, and multiple firing modes.
    * Hit and Damage rolls based on target and selected weapon.
* **Netrunning Support**
  * Cyberdeck configuration - rez/de-rez and installation of programs.
  * Net actions and Programs rollable.
## Setup & Installation

### Quickest way
* Download the relevant app to your desktop (dropbox links below for Windowws and Linux 64bit).
* Use the [Threehats API](https://foundryvtt-rest-api-relay.fly.dev/), create an account and get the API key. Note that the limits will get hit very quickly as the app has to make a fair few calls to keep up with the requests. I've implemented a few caching techniques but it's still fairly hungry.
* Plug that info into the app (see Configuration below), boot Foundry up (make sure you've got the [module](https://foundryvtt.com/packages/foundry-rest-api) installed) and see if it works!

### Availability
The app is available through several methods (details below on DDEV):
* **Self-Hosting**: Using DDEV (recommended for local/shared development).
* **Desktop Executables**: EXE (Windows) and Deb/Linux executables are available - [Dropbox Link](https://www.dropbox.com/scl/fo/qn6pnahxe4vrl2dmxjl9y/APmV2t0u0Et--HFbER7Prpc?rlkey=lag3gxox4hq805elce1d7gxzg&st=ryb211m1&dl=0). The Win EXE will throw an unsigned window at you, click More Info and Run Anyway
* **Mobile**: iOS and Android App Store versions are coming later.

### Self-Hosting with DDEV
For the best experience while self-hosting and sharing with your players:
1. Ensure you have [DDEV](https://ddev.readthedocs.io/) installed.
2. Start the project: `ddev start`. This will give you a URL to visit.
3. Install dependencies: `ddev composer install` and `ddev npm install`.
4. Providing there's been no errors, run `ddev artisan migrate:fresh`, `ddev npm run build`. This will refresh the database and build the scripts and styles.
5. To share the app with players over the internet:
   * Use `ddev share`, check [here](https://docs.ddev.com/en/stable/users/topics/sharing/) for the documentation. 
   * This utilizes **ngrok** to provide a public URL that your players can use to access the companion app.
* Please note that this has been tested with live URLs, I will be doing more testing using local Foundry & relay envrionments and a share DDEV/NGROK instance.

## Relay Setup
The app requires a live Foundry VTT session to link to, with the [ThreeHats Foundry API](https://github.com/ThreeHats/foundryvtt-rest-api) module installed.

### Best Practices for Relay
To ensure a stable connection between the app and your Foundry session, setting up a relay is highly recommended. 

For a comprehensive guide on setting up a relay (using DuckDNS as an example), please refer to:
**[Foundry VTT REST API Relay - DuckDNS Example](https://foundryvtt-rest-api-relay.fly.dev/docs/relay-app-duckdns-example)**

### Configuration
1. On the first run, create an account and you will be shown the configration screen. Fill in the relay API, relay URL, and the Foundry URL.
2. Once the server fields are saved, they will be the same for all users logging on to the app (if hosted).
3. If your Foundry session is up and running, and the relay key matches the Three Hats API module, when the server details are saved the Client and Character lists will populate.
4. Select a character, and you should be ready to roll! Hit the Return to Interface button to go to the character sheet.
5. You can return here at any time by clicking Profile in the bottom right.

## Technical Foundation
This app is built with Laravel and [NativePHP](https://nativephp.com/). It uses a [PHP wrapper](https://packagist.org/packages/surrealtech/threehats-api-php-wrapper) for the ThreeHats API to communicate with Foundry VTT.

The setup I've been working with is the Relay & Foundry hosted on my home server (Unraid - using docker-compose), and running that through Swag + Cloudflare to obfuscate the SSL ports and provide DNS services. The relay and Foundry do not communicate locally, all calls are made online. 

I tested the app on my VPS, and after cloning the repo + running a few scripts
