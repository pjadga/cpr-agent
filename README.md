# CPR Agent - [Live Agent](https://agent.surrealdisco.co.uk/)

# Cyberpunk Red Companion App

A hostable companion app for Cyberpunk Red, designed to bridge the gap between players at a physical table and a Foundry VTT session. This app displays character stats, handles rolls, and allows for token interaction directly from a mobile device or desktop application.

## State

Currently functioning but actively being worked on.

### Known issues/upcoming features

* Ping targets not working yet.
* Long goal - Originally I had the Agent idea where there's more of a 'phone' aspect. I think for RP purposes the GM could upload scream sheets, stories, add phone numbers of fixers/contacts etc. Getting a text mid gig from a fixer telling em to gtfo would be pretty ace. I'm imagining it working with notes + handouts. Though I feel this is on my way to making a Foundry module of my own...

## Features

* **Character Management**: Displays relevant character stats, skills, and roles, all rollable from the app.
* **Gear & Cyberware**: Displays equipped gear, ammo count, costs, damage, and descriptions in an easy-to-read format. Equipped states & ammo types are now changeable. Most homebrew gear should 'just' work, if it follows the CPR models.
* **ER Mission Kit rolls**: Currently supports Quickhack (un-named, will update as they are supported within the CPR Module), Jack in + Breach.
* **NetPools**: Create a NetPool within Foundry's Journal system.
* PDF and Rich Text are supported for content display, as well as custom Icons for the 'terminal'.
* **Token Control**: Directional control of tokens via an on-screen D-pad.
* **Combat Support**:
  * Listed tokens on map with relative positions.
  * Calculated DV values per weapon.
  * Weapon selection, reloading, and multiple firing modes.
  * Hit and Damage rolls based on target and selected weapon.
* **Netrunning Support**
  * Cyberdeck configuration - rez/de-rez and installation of programs.
  * Net actions and Programs rollable.
* **Local Caching within Foundry**
* **Dice Roll returns**
* **Lifepaths and Notes section available**
* **Item carry control (Carry, equip)**
* **Critical Injuries list (ouch)**
* **Dice Roller + Dice Modifiers**
* **Vector based UI**
* **Shadow Animations, Graphics toggles, Radial menu + toggles**

## Documentation

Full documentation lives in the **[wiki](wiki/)**.

* **[Herd Setup Guide](wiki/setup-herd.md)**, self-host your own Agent on Windows or macOS. Start here.
* **[Getting Started](wiki/getting-started.md)**, accounts, QR pairing, linking players, data pools and troubleshooting.
* **[Dev Env Setup: DDEV](wiki/guide.md)**, the DDEV install, connecting Foundry, sharing, settings reference and the Bridge deep dive.
* **WIP** **[Standalone](wiki/standalone.md)**, run it as a single binary with no dependencies.
* **[Architecture](wiki/architecture.md)** · **[Internal API: Agent](wiki/internal-api-agent.md)** · **[External API: Future](wiki/external-api-future.md)** · **[Testing](wiki/testing.md)**

Not hosting anything? Use the [hosted Agent](https://agent.surrealdisco.co.uk), you only need to share your Foundry instance.

## Technical Foundation

This Agent is built with Vue.js, PHP (Laravel along with Reverb + Sanctum), Tailwind CSS, and a ton of JS to make the Foundry Module. I used to use the [ThreeHats API](https://github.com/ThreeHats/foundryvtt-rest-api) and my own [PHP Wrapper](https://packagist.org/packages/surrealtech/threehats-api-php-wrapper)
to take care of the communication, however I wanted more control over returns and data storage! Please give them a look if a more system agnostic API is what you're looking for.

## AI use

Quick overview on how AI was used in this project.

I found Claude to be very useful when it comes to learning new systems. In this case, I knew I wanted to learn Vue.js as a framework to put under my belt of languages, so after some time spent watching vids, I dug in. A lot of the time I would write code, get stuck, try to work it out, get stuck again, then ask Claude for some help. That would point me in the right direction. Essentially it became the quick version of going to Stack Overflow.

The ideas within this app are mine, the theories on how things work, the UI, websocketing, the mapped caching (deltas), laravel work, UX & flow; all mine.

Claude helped with testing, creating some quite frankly overkill python scripts for putting the SVGs in place (although it did help solve the stretching issue - vectors will be any size, but if the ratio is off, they look weird. Claude wrote a script to add in stretch zones, as per my request. Pretty cool. Learnt something as well), refactoring and general templating.

On the latter, in development, it's real useful for me to get some trashy UI up and running so I can focus on the, for me, more rewarding backend functionality. I'm not a designer, PHP was always more fun than front-end. Once I got around to doing the vectors (https://www.vecteezy.com/ - paid for) it kicked my love of animation into overdrive. GSAP (greensock) is SO much fun to workwith. Especially coming from the days of faffy CSS and jquery animations.

I know this may put some off, sometimes any use is a bad thing, whether thats morally or perhaps it appears as laziness. I know for myself, my ADHD sometimes makes coding very difficult, and AI can be an easy way out. But coding was always about problem-solving, and unless that is done yourself, there is ZERO payout. No dopamine, much boring. Above is the balance I've struck for myself to continue to do the things I love.

I was fortunate enough (both in position and timing) to have picked up a 4090 before the ram-pocolypse. If we're going on % increase, it's the best investment I've ever made /s. I have a modified QWEN model running in the background now, it runs as my key-monkey, now doing the above. If possible, always self-host.

Thanks for coming to my ted talk? That was far longer than it was meant to be.

## Support

If this project is useful to you and you'd like to support its development, you can buy me a coffee:
Tips go towards caffeine and the running of the hosting.

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/V8M8238Q2V)
