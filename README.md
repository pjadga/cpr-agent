# Cyberpunk Red Companion App

A hostable companion app for Cyberpunk Red, designed to bridge the gap between players at a physical table and a Foundry VTT session. This app displays character stats, handles rolls, and allows for token interaction directly from a mobile device or desktop application.

## State
Currently functioning but actively being worked on. If you can host a Laravel site, you can get this running.

## Features
* **Character Management**: Displays relevant character stats, skills, and roles, all rollable from the app.
* **Gear & Cyberware**: Displays equipped gear, ammo count, costs, damage, and descriptions in an easy-to-read format.
* **Token Control**: Directional control of tokens via an on-screen D-pad.
* **Combat Support**: 
    * Listed tokens on map with relative positions.
    * Calculated DV values per weapon.
    * Weapon selection, reloading, and multiple firing modes.
    * Hit and Damage rolls based on target and selected weapon.

## Setup & Installation

### Availability
The app is available through several methods (details below on DDEV):
* **Self-Hosting**: Using DDEV (recommended for local/shared development).
* **Desktop Executables**: EXE (Windows) and Deb/Linux executables are available.
  * 
* **Mobile**: iOS and Android App Store versions are coming later.

### Self-Hosting with DDEV
For the best experience while self-hosting and sharing with your players:
1. Ensure you have [DDEV](https://ddev.readthedocs.io/) installed.
2. Clone the repository and run `ddev config` if not already configured.
3. Start the project: `ddev start`.
4. Install dependencies: `ddev composer install` and `ddev npm install`.
5. To share the app with players over the internet:
   * Use `ddev share`. 
   * This utilizes **ngrok** to provide a public URL that your players can use to access the companion app.

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
