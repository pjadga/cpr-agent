# STATE
Currently unfinished, functioning but being actively worked on by myself in my spare time.
I will create a write up on how to get it running once I'm happy with the services provided by it.

For the time being, if you can host a Laravel site, you can get this running.

# Introduction
This started as a proof of concept idea, when around a single screen (say, a table or a tv) h
how would the players interact?
A companion app was the obvious choice - something that displayed stats and rolls, and coming from my background in backend web developemnt,
I wanted the app to be a hostable service. And thanks to [ThreeHats Foundry API](https://github.com/ThreeHats/foundryvtt-rest-api) and a 
hastily written [PHP wrapper](https://packagist.org/packages/surrealtech/threehats-api-php-wrapper) I have a hostable app that can fire and return data to 
a session of Foundry via the THF api.

# Cyberpunk Red companion app 
* Displays relevant character stats, skills and roles, all rollable from the app.
* Displays equipped gear, ammo count, costs, damage, amounts and descriptions of most gear.
* All gear & cyberware displayed in an easy to read format.
* Directional control of tokens via an on-screen D-pad.
* Listed tokens on map, their position relative to your token & calculated DV values per weapon.
* Weapon select, reloading & firing modes.
* Hit rolls, Damage rolls based on target & selected weapon

# App configuration
The app requires a live foundry session to link to, and the ThreeHats module installed. 
Each app user has a login, to which they save their character & Foundry session.
The site 'admin' can save the base URLS for the API and Foundry session, along with the Client ID (usually found when setting up the ThreeHats API).
