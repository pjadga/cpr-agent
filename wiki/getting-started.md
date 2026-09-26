---
title: Getting Started
order: 2
---
# Getting Started

The CPR Agent is a companion app that sits between your players and your Foundry VTT game. Players get their stats, gear and rolls on their phone or tablet, and everything they do lands back in Foundry.

This page gets you from zero to Agent. It works the same whether you're on the [hosted Agent](https://agent.surrealdisco.co.uk) or your own.

> **Want to host your own Agent?** Get it running first with the [Herd guide](/wiki/setup-herd) (Windows and macOS) or [Dev Env Setup: DDEV](/wiki/guide) (Linux, or DDEV people), then come back here.

## Contents

1. [First-Time Setup](#first-time-setup)
2. [Quick Connect: QR Pairing](#quick-connect-qr-pairing)
3. [Data Pools](#data-pools)
4. [Troubleshooting](#troubleshooting)

---

## First-Time Setup

### 1. What you need


|                        |                                                                                                    |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| **Cyberpunk Red Core** | The[Foundry system](https://foundryvtt.com/packages/cyberpunk-red-core), installed in Foundry VTT. |
| **Agent Bridge**       | The Foundry module that does the talking. Manifest URL below.                                      |
| **An Agent account**   | Sign up on the [hosted Agent](https://agent.surrealdisco.co.uk/) or your own self-hosted one.     |

Bridge manifest URL:

```
https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json
```

### 2. GM setup

> **The quick way** is [QR Pairing](#quick-connect-qr-pairing). The manual steps below (and the player and character bits after them) are the fallback for when there's no camera or you'd rather do it by hand.

1. In the Agent, go to **Settings**.
2. Enter your **Foundry URL**, generate an **API Key**, and save. Saving creates your session for you.
3. Copy your **Channel ID**.
4. In Foundry, go to **Configure Settings → Agent Bridge**.
5. Paste in the **Agent URL**, **Channel ID** and **API Key**.
6. Share the **Channel ID** with your players.

### 3. Player setup

1. Log in to the Agent and go to **Settings**.
2. Generate your own **API Key** and copy it.
3. Paste the **Channel ID** your GM gave you and hit **Commit Sync**.
4. In Foundry, go to **Configure Settings → Agent Bridge** and enter your own **API Key**.

### 4. Character assignment

1. As the GM, open **Agent Bridge → Manage Characters** in Foundry.
2. Assign a character to each player who has linked their API key.
3. The player's **Agent Link** will show up once they're assigned. They need to be online in Foundry for this, so if it doesn't appear, get them to refresh.
4. Done. Stats and rolls now flow in real time, and from here on only the GM needs Foundry open.

### 5. Make it feel like an app

Save the Agent to your home screen on your phone or tablet. It ditches the address bar and feels a lot more like a proper app.

- **Safari:** the three dots in the bottom right → **Share** → scroll down → **Add to Home Screen**.
- **Chrome:** the menu to the right of the address bar → **Add to home screen** → **Create shortcut**.

---

## Quick Connect: QR Pairing

The manual way works, but it's fiddly. Especially with a table full of players squinting at their phones. QR Pairing skips all of it: players scan a code, and their Agent sets itself up and links to Foundry on its own.

There are **two different codes** here, and it's worth not mixing them up. One is for you (the GM) to set up your Bridge. The other is for each player to link their Agent.

### Code #1: The Foundry Setup Code (GM → Bridge)

This one sets up the **Bridge module itself**, so you're not typing settings into Foundry by hand.

1. At the top of the Agent's **Settings**, enter your **Foundry URL**, generate an **API Key**, and save.
2. Hit **Get Foundry Setup Code**. You'll get a short code, something like `KX7M-4PWD`.
3. In Foundry, open **Agent Bridge → Quick Setup** and type it in. It works even when the Agent is on your phone.
4. The Bridge fills in your Agent URL, Channel ID, API Key and Reverb key in one go.

If the Agent and Foundry are on the same machine, there's also a longer copy and paste version of the code.

**This code fetches your secret API key, so it's GM-only.** Never hand one to a player.

### Code #2: The Pairing QR (Foundry → Player's Agent)

Once your Bridge is online, each player links their Agent by scanning their own QR code:

1. As the GM, open **Agent Bridge → Manage Characters** in Foundry and hit the **QR button** next to the player you want to link.
2. The player opens the Agent, goes to **Settings → Join a Session**, and taps **Scan to Link Foundry**.
3. They point their camera at the QR. No camera? There's a copyable **link code** under the scan button. Paste that instead.
4. That's it. Their Agent picks up the Channel ID and Foundry URL, and Foundry gets told which character is theirs. No manual assignment needed.

### What actually happens (the handshake)

For the nerds (hi), here's the round trip behind that one scan:

1. **Register.** When you show the QR, your Bridge mints a one-time random **nonce** and registers it with the Agent (`POST /api/pairing/register`, using your GM API key). The Agent stores it against your session, the Foundry user and the Foundry URL, with a **5 minute expiry**. The QR itself only holds `{ v: 1, nonce, agentUrl }`, so no secrets travel in the image.
2. **Claim.** The player's Agent reads the nonce and calls `POST /api/pairing/claim`. The server checks the code is real, unclaimed and in date, then sets the player up: their **Channel ID**, an **Agent Link** if they don't have one yet, and the Foundry URL.
3. **Auto-link.** The server broadcasts a `PAIR_AGENT` event on your session channel (`cpr-agent.{channel_id}`). Your Bridge hears it and records which Agent Link belongs to which Foundry user. That's exactly the step you'd otherwise do by hand in *Manage Characters*.
4. **Confirm.** The player's Agent checks it can reach Foundry and waits a few seconds for your Bridge to send back a `PAIR_CONFIRMED`. That's how it can say "Linked!" and actually mean it.

### Safety rails

- **One-time use.** Each code can be claimed once. The same player scanning again is fine; anyone else gets a polite "already used".
- **Short-lived.** Codes expire after 5 minutes. If one goes stale, just make a fresh one.
- **GM-owned only.** A GM can only register pairings for a session they actually own.
- **Server-matched.** If a player scans a QR meant for a *different* Agent than the one they're logged in to, the Agent spots it and refuses.

### When the QR won't play ball

The scanner tries to tell you what went wrong in plain English, and how to fix it. The usual suspects:


| What it says                             | What to do                                                                                                                                                                                        |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Camera blocked (insecure connection)** | Browsers only allow the camera over HTTPS. Load the Agent from its`https://` address (or `localhost`), not a bare `http://` IP.                                                                   |
| **Code expired / already used**          | Ask the GM for a fresh QR.                                                                                                                                                                        |
| **QR is for a different Agent**          | Log in to the Agent address your GM actually uses, then scan again.                                                                                                                               |
| **Linked, but can't reach Foundry**      | You*are* paired, and stats and rolls will still sync. This only affects loading character images, which needs Foundry to be reachable from your device (same network, or a public HTTPS address). |

---

## Data Pools

I always liked the classifieds vibe of 2077's terminals. The Red is a lot less connected (and rightly so), but there are still local networks called CitiNets thanks to Ziggurat. (Hit me up if I've got the lore wrong.)

To imitate this, I've added a new type of Journal page to Foundry. It gives you a form where you can add PDFs and rich text as the "pages" of a Data Pool, and set an icon for each one. They show up on the Agent looking a lot like the 2077 terminal net page.

The CitiNet icon won't appear in the Agent until you've set up a Data Pool Journal. If it still won't show, give the Agent a refresh.

---

## Troubleshooting

Open the browser console with **F12** and look at the red stuff first.

### The Bridge says "Offline"


| Check                  |                                                                                                                                                                                                                                                   |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Agent URL**          | It must match the Agent's address exactly, scheme and port included.                                                                                                                                                                              |
| **Is it running?**     | Self-hosting? The background processes need to be up:`ddev agent-serve` on DDEV, `php artisan agent:serve` on Herd. No Reverb, no connection.                                                                                                     |
| **API Key**            | Generating a new key kills the old one. Paste the current key into Foundry. A**401** in the Foundry console means this.                                                                                                                           |
| **Leftover settings**  | If you set this up the old way, you might still have a port like`8080` or `8081` in **WebSocket Port**, or a host in **WebSocket Host**. Set the port back to `0` and clear the host. Pasting a Setup Code resets the port, but **not** the host. |
| **WebSocket security** | Should be on**Auto**.                                                                                                                                                                                                                             |

### Rolls or stats aren't updating

- **Channel ID.** If the Bridge says Online but nothing arrives, the Bridge and the Agent are probably on different channels. Check the Channel ID matches in both. Turning on **Debug Mode** in the Bridge gets you a lot more to go on in the Foundry console.
- **Character assignment.** Make sure the GM has assigned you a character in **Manage Characters**.
- **The sheet loads but never updates.** The WebSocket never connected. Look for `[Agent] WebSocket subscription established.` in the browser console. A **404** on `/app/...` means the WebSocket isn't getting through to Reverb.
- **Changes vanish after a while.** The queue worker isn't running. Start `ddev agent-serve` (DDEV) or `php artisan agent:serve` (Herd).

### Login bounces straight back to the login page

Self-hosting? `APP_URL` doesn't match the address you're actually using. Fix it in `.env`, then clear the config cache: `ddev artisan config:clear` on DDEV, `php artisan config:clear` on Herd.

### Images aren't showing up

- **Foundry URL.** Make sure it does **not** end in `/game`.
- **Mixed schemes.** If Foundry is on `http` and the Agent is on `https`, most browsers will block the images. Put both on `https`.

If you're super stuck, come and say hi on the CPRed Discord. I'm in the Content Share section.
