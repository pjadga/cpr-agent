---
title: 'Dev Env Setup: DDEV'
order: 4
---

# Dev Env Setup: DDEV

This covers getting a local DDEV environment set up, hosting the Agent yourself, and getting it talking to Foundry VTT.

> **Just want to play?** Use the [hosted Agent](https://agent.surrealdisco.co.uk). It's free, and the only thing you need to share is your Foundry. Head to [Getting Started](/wiki/getting-started).
>
> **Self-hosting on Windows or macOS?** Head to the [Herd guide](/wiki/setup-herd) instead. Huge thanks to **capt_garris** from the CPR Discord for writing it. It takes SO much of the faff out of self-hosting, and no Docker or WSL2 required.
>
> **On Linux, or already a DDEV person?** Stick around.

Once the Agent's up and running, [Getting Started](/wiki/getting-started) covers accounts, QR pairing and linking your players.

## Contents

1. [Self-Hosting with DDEV](#self-hosting-with-ddev)
2. [Connecting Foundry VTT](#connecting-foundry-vtt)
3. [Sharing is Caring](#sharing-is-caring)
4. [Settings Reference](#settings-reference)
5. [Technical Deep Dive: The Bridge](#technical-deep-dive-the-bridge)
6. [The Delta System](#the-delta-system)

---

## Self-Hosting with DDEV

If you'd rather run your own Agent, this is how I do it on Linux. On Windows or macOS, the [Herd guide](/wiki/setup-herd) is a much smoother ride.

**The good news:** it used to be a right faff, with Reverb (the WebSocket server) sat on its own port and a pile of Bridge settings to match. That's gone. Reverb now rides the Agent's own address, so there's **one URL, one port**, and nothing Reverb-related to fill in anywhere.

### What you need

- [Docker](https://www.docker.com/)
- [DDEV](https://ddev.readthedocs.io/)

### Installation

**1. Grab the code**

```bash
git clone https://gitlab.com/surrealtech/cyberpunk-red-agent
cd cyberpunk-red-agent
```

> **On Windows with WSL?** Unzipping into WSL can leave `Zone.Identifier` files everywhere, which cause all sorts of errors. Clear them out with:
>
> ```bash
> find . -name "*:Zone.Identifier" -type f -delete
> ```

**2. Set up your environment file**

```bash
cp .env.ddev .env
```

The template is ready to go as it is. The Agent lives at `https://cyberpunk.ddev.site`, and you don't need to touch anything Reverb-related.

**3. Start DDEV and install the dependencies**

```bash
ddev start
ddev composer install
ddev npm install
```

**4. Run the installer**

```bash
ddev artisan agent:install
```

This generates your app key and any missing secrets, sets up storage and runs the migrations. It only fills in what's missing, so it's safe to run again.

**5. Build the front end**

```bash
ddev npm run build
```

**6. Start the background processes**

```bash
ddev agent-serve
```

This runs Reverb and the queue worker together, and restarts them if they fall over. **Leave it running.** Without it the Agent will look fine, but nothing will update live and character changes won't get saved.

Open [https://cyberpunk.ddev.site](https://cyberpunk.ddev.site) and you should be looking at the login screen. Fingers crossed.

### A note on Redis

The DDEV setup uses Redis for caching instead of the database, which makes it incredibly responsive. Run `ddev describe` and check there's a `redis` container in the list. If there isn't:

```bash
ddev add-on get ddev/ddev-redis
ddev restart
```

### Updating

```bash
git pull
ddev composer install
ddev npm install
ddev artisan migrate
ddev npm run build
```

Then restart `ddev agent-serve`.

> **SUPER IMPORTANT:** when updating, use `migrate`, **never** `migrate:fresh`. Fresh wipes the whole database, logins and all.

---

## Connecting Foundry VTT

The Bridge module needs to know where your Agent is. That's genuinely it now.

**The quick way:** use the [Foundry Setup Code](/wiki/getting-started#quick-connect-qr-pairing). It fills everything in for you.

**By hand,** in **Configure Settings → Agent Bridge**:

| Setting | What goes in it |
|---|---|
| **Agent URL** | Your Agent's address, exactly. `https://cyberpunk.ddev.site` for a local DDEV install, your `APP_URL` for anything else, or `https://agent.surrealdisco.co.uk` for the hosted one. |
| **API Key** | Generated on the Agent's Settings page. |
| **Channel ID** | From the Agent's Settings page. |
| **Reverb App Key** | `REVERB_APP_KEY` from your `.env` (`my-app-key` unless you changed it). On the hosted Agent, leave the default. |
| **WebSocket Port (override)** | Leave at `0`. |
| **WebSocket Host (override)** | Leave blank. |
| **WebSocket security** | Leave on **Auto**. |

Those last three stay on their defaults because the Bridge works everything out from the Agent URL. An `https://` Agent URL means a secure WebSocket, an `http://` one means a plain one, and both go to the same address.

Foundry usually wants a refresh after saving. You should see **"Cyberpunk Bridge: Online"**.

> **Heads up:** generating a new API key kills the old one. If you regenerate it in the Agent, paste the new one into Foundry too, or the Bridge will throw 401s and show as offline.

---

## Sharing is Caring

Getting your players in from outside your house. There are a few ways to do it.

The easiest by miles is the [hosted Agent](https://agent.surrealdisco.co.uk). It's free, and you only need to share your Foundry.

Me? I run mine on an Unraid server, behind SWAG (or Nginx Proxy Manager), through Cloudflare to a domain I own. It works brilliantly, but it's complex and way out of scope here. If you fancy that rabbit hole, look up IBRACORP on YouTube.

The friendlier option is **Tailscale**. No port forwarding, no firewall fiddling. Follow [this tutorial](https://foundryvtt.wiki/en/setup/hosting/tailscale) to put Foundry on a Tailscale Funnel. You'll end up with a Magic URL like `https://foundry.random-words.ts.net`.

### With the hosted Agent

1. Put your Tailscale Foundry URL (`https://foundry.random-words.ts.net`) in the Agent's **Foundry URL** setting.
2. Set up the Bridge with the [Foundry Setup Code](/wiki/getting-started#quick-connect-qr-pairing).
3. Give your players the Tailscale Foundry URL, and have them make an account on the hosted Agent.
4. Link them with [QR Pairing](/wiki/getting-started#quick-connect-qr-pairing), or do it by hand (API keys, Channel ID and character assignment, as in [Getting Started](/wiki/getting-started#first-time-setup)).

### With your own DDEV Agent

If you're like me and need control over every digital aspect of your life, read on. Because Reverb rides the Agent's own address, this is **one extra Funnel**, not two.

1. Get the Agent running locally first ([Self-Hosting with DDEV](#self-hosting-with-ddev)). Get Foundry on its Funnel too, and check you can open it from the Magic URL.
2. DDEV only answers to hostnames it knows about, so tell it about yours. In `.ddev/config.yaml`:

   ```yaml
   additional_fqdns:
     - foundry.random-words.ts.net
   ```

   Then `ddev restart`.
3. Open the Funnel for the Agent:

   ```bash
   tailscale funnel --bg --https 8443 http://127.0.0.1:80
   ```

4. Point the Agent at its new address. In `.env`:

   ```
   APP_URL="https://foundry.random-words.ts.net:8443"
   ```

   Then `ddev artisan config:clear` and restart `ddev agent-serve`.
5. In Foundry, set the Bridge's **Agent URL** to `https://foundry.random-words.ts.net:8443` (or just paste a fresh Setup Code). Leave the WebSocket settings on their defaults.
6. Save, refresh, and you should get **Cyberpunk Bridge: Online**.
7. Share `https://foundry.random-words.ts.net:8443` with your players. That's their Agent.

> **The one rule:** the address your players type is the address in `APP_URL`, and the same address in the Bridge's Agent URL. If those three don't match, logins bounce and QR codes complain.

**Quick and dirty alternative:** `ddev share --provider=cloudflared` gives you a temporary public URL with no setup. Set `APP_URL` to whatever it hands you (then `ddev artisan config:clear`), and use it as the Agent URL. The URL changes every time, so it's handy for testing, not so much for a weekly game.

### Good luck, choom

Setting this up locally is fairly involved. If you've made it this far and you're sat in front of a shared Foundry and Agent, congrats! Kick back with some skop and kopi kadal.

If not, work through the usual suspects first:

- Refresh, or hard refresh (Ctrl+Shift+R).
- Re-save the character in Settings.
- Clear the site data and cookies for the Agent in your browser.

Still broken? Hit F12 and look for anything red in the console. 500s, 404s, "can't connect" or "lost connection" usually mean the Agent and Foundry aren't talking, and that's nearly always a URL mismatch (or my inability to write good documentation). Then check [Troubleshooting](/wiki/getting-started#troubleshooting).

If you're super stuck, come and say hi on the CPRed Discord. I'm in the Content Share section.

---

## Settings Reference

### Agent settings

| Setting | What it's for |
|---|---|
| **Foundry URL** | The base address of your Foundry. The Agent uses it to pull in images (tokens, items, portraits). Do **not** put `/game` on the end. It should look like `http://localhost:30000` or `https://foundry.example.com`. |
| **API Key** | A token generated by Laravel Sanctum. It's how your Bridge proves who it is to the Agent. Hit **Generate** in Settings to make one. |
| **Channel ID** | The ID for your game session. It puts the GM and players on the same channel. The GM's is created when they save their Foundry connection; players get it from the GM or a QR scan. |

### Foundry Bridge settings

| Setting | What it's for |
|---|---|
| **Quick Setup** | Paste or type a Foundry Setup Code and it fills in everything below. |
| **Manage Characters** | Assign characters to players, and show pairing QRs. |
| **Agent URL** | Where your Agent lives. Must match the address players use. |
| **API Key** | Your own API key from the Agent. Each player enters their own. |
| **Channel ID** | The GM's Channel ID. |
| **Reverb App Key** | Your Agent's `REVERB_APP_KEY`. Leave the default on the hosted Agent. |
| **WebSocket Host (override)** | Leave blank. Only for an Agent that serves its WebSocket from a different hostname. |
| **WebSocket Port (override)** | Leave at `0`. Only for an Agent that publishes Reverb on its own port. |
| **WebSocket security** | Leave on **Auto**, which matches the Agent URL. Only override if you've got a proxy that changes the scheme and live updates fail while everything else works. |
| **Debug Mode** | Chatty logging in the Foundry console (F12). Your first stop when the connection's playing up. |

---

## Technical Deep Dive: The Bridge

The Bridge module is where all the Foundry side of things happens. It sits between Foundry and the Laravel-based Agent and passes everything back and forth. Grab it from the [repository](https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge) or the Foundry package manager.

### How it works

1. **Connection.** It spins up a `Laravel Echo` instance using the Reverb broadcaster. The WebSocket goes to the Agent URL's own host and port, on the `/app/` path.
2. **Authentication.** It sends your **API Key** (a Sanctum token) in the headers to authenticate with the Agent's API.
3. **Subscriptions.** It joins the private channel `cpr-agent.{channelId}` and listens for events from the Agent. Every event is named in capitalised SNAKE_CASE.
4. **Synchronisation.** When something happens in Foundry, the Bridge catches it and sends it to the Agent. When the Agent sends a command (e.g. "Roll Initiative"), the Bridge runs it in Foundry.
5. **Data flow.**
   - **Foundry → Agent:** standard API calls (POST/PATCH) to update character data.
   - **Agent → Foundry:** WebSockets (Reverb) for real-time triggers and updates.

### The `CyberpunkBridge` class

This looks after:

- **Roll interception.** It hooks into Foundry's rolls, grabs the formula, results and modifiers, and sends them to the Agent so players see the "virtual dice".
- **Actor updates.** It watches Foundry actors for changes (HP, armour, Humanity and so on) and pushes the new stats to the Agent.
- **Start-up.** It connects automatically when Foundry starts, once the Agent URL, API key and Channel ID are all set.

### Communication protocol

The Bridge uses a mix of REST (for state changes) and WebSockets (for events).

- **REST:** `POST /api/foundry/callback` and `/api/foundry/update`, for bulk character data.
- **WebSockets:** the `cpr-agent.{channelId}` channel, for instant triggers like "Request Roll" or "Ping Location".

---

## The Delta System

Foundry character sheets are huge, and phones on patchy signal don't love huge. So the Agent has its own "delta" sync for character data.

### How it works

Rather than sending the whole character (which can run to several hundred kilobytes) every time one number changes, it sends a **delta**: just the bit that changed.

- **Dot-notation paths.** Fields are targeted like `system.stats.emp.value`.
- **Items** get their own syntax:
  - `items.ID` updates a specific item.
  - `items.+ID` adds a new item to the sheet.
- **Null values.** Setting a path to `null` deletes that field or item.

### Why bother?

1. **Bandwidth.** A delta is usually less than 1% of the size of a full update, which is why the Agent stays snappy even on a rubbish mobile connection.
2. **Server load.** The backend merges deltas straight into the cached character. No parsing and replacing a giant JSON blob because someone lost 2 HP.
3. **Saved in the background.** Deltas hit a fast cache first, and the queue worker writes the full character to the database behind the scenes. The UI updates near enough instantly, and nothing gets lost. (This is why the queue worker in `agent-serve` matters.)

Stuck? Have a look at [Troubleshooting](/wiki/getting-started#troubleshooting) on the Getting Started page.
