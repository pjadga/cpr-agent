# CPR Agent Guide

This guide provides detailed instructions on how to set up, self-host, and connect the CPR Agent with Foundry VTT.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Self-Hosting Guide](#self-hosting-guide)
3. [Connecting Foundry VTT](#connecting-foundry-vtt)
4. [Data Pools](#data-pools)
5. [Sharing is Caring](#sharing-is-caring)
   - [Sharing Via Tailscale](#sharing-via-tailscale)
6. [Settings Reference](#settings-reference)
7. [Technical Deep Dive: The Bridge](#technical-deep-dive-the-bridge)
8. [The Delta System](#the-delta-system)
9. [Troubleshooting](#troubleshooting)

---

## Getting Started

The CPR Agent is a companion app designed to bridge the gap between players and a Foundry VTT session. It allows players to view stats, manage gear, and perform rolls directly from their mobile devices or tablets.

### 1. Preparation & Installation

- **Foundry VTT Module**: You must have the [Cyberpunk Red Core module](https://foundryvtt.com/packages/cyberpunk-red-core) installed in Foundry VTT.
- **Agent Bridge Module**: Install the **Cyberpunk Red Agent Bridge** module in Foundry VTT.
  - Manifest URL: `https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json`
- **Agent Account**: Sign up and log in to the Agent app (either the [official hosted version](https://agent.surrealdisco.co.uk/) or your own self-hosted instance).

### 2. GM Setup

1. In the Agent app, go to the **Settings** page.
2. Generate an **API Key** and copy it.
3. Copy your **Channel ID**.
4. In Foundry VTT, go to `Configure Settings` -> `Agent Bridge`.
5. Paste the **Channel ID** and **API Key**.
6. Share the **Channel ID** with your players.

### 3. Player Setup

1. Log in to the Agent app and go to **Settings**.
2. Generate your own **API Key** and copy it.
3. Paste the **Channel ID** provided by your GM and click **Commit Sync**.
4. In Foundry VTT, go to `Configure Settings` -> `Agent Bridge` and enter your individual **API Key**.

### 4. Character Assignment

1. As the GM, open the **Agent Character Assignment** menu in Foundry (found in the Agent Bridge module).
2. Assign a playable character to each player who has linked their API key.
3. Once assigned, the player's **Agent Link** will appear.
   - *Note*: Players must be online in Foundry for the link to appear. If it doesn't show up immediately, ask the player to refresh.
4. Players are now synced! Stats and rolls will flow in real-time. Once synced, only the GM's instance of Foundry is required to be open for this to work.

### 5. Extras

* Save the page to your Homescreen if using a mobile or tablet. It will remove the address bar and it will have more of an 'app' feel.
* On Safari, Hit the 3 dots in the bottom right -> Share -> Scroll Down -> Add to Home Screen.
* From Google: "
  To the right of the address bar, tap More More and then Add to home screen and then Create shortcut."

---


## Data Pools

I always liked the 'classifieds' vibe of 2077's terminals, and whilst the Red is fairly unconnected (and rightfully so), there is still local area networks called CitiNets thankls to Ziggurat (hmu if I've misunderstood the lore :D ).

To imitate this, I've leveraged Foundry's Journal system and added a new type of Journal page. This creates a dynamic row form that gives you freedom to upload PDFs and RichText as 'pages' of a Data Pool. You can set the Icons for each 'link', and these will be displayed in a similar fashion to the 2077 terminal 'net' page on the agent.

The CitiNet icon won't appear in the Agent until you've setup a Data Pool Journal. If it refuses to appear, give the Agent a refresh. 

## Self-Hosting Guide

If you prefer to host your own instance of the CPR Agent, follow these steps.

### Prerequisites

- [Docker](https://www.docker.com/)
- [DDEV](https://ddev.readthedocs.io/) (Recommended for easy setup)

### Installation Steps

1. **Clone the repository**:
   ```bash
   git clone https://gitlab.com/surrealtech/cyberpunk-red-agent
   cd cyberpunk-red-agent
   ```
2. **Start DDEV**:
   ```bash
   ddev start
   ```
3. **Install Dependencies**:
   ```bash
   ddev composer install
   ddev npm install
   ```
4. **Environment Configuration**:
   Copy `.env.template` to `.env` and configure your settings. If .env does not exist, please create the file and copy the env.template. Key variables include:

- `APP_URL`: Your local or public URL.
- `REVERB_HOST`: The hostname for the websocket server.
- `REVERB_PORT`: Typically 8080 for local development.

5. **Setup Database & Assets**:
   ```bash
   ddev artisan migrate:fresh
   ddev npm run build
   ```
6. **Turn on Reverb**
   This is a requisite for running the app. Reverb works through port 8080 for http (ws) or 8081 (wss) for https,
   so when creating sharing tunnels later this will need to be taken into account.
   ```bash
   ddev artisan reverb:start
   ```

At this point, the Agent should be locally available and run without any severe Laravel/PHP errors (fingers crossed).
For sharing locally, please see the Sharing is Caring section below.

**Note** The DDEV setup I have created uses Redis as the caching system instead of the database. This makes it incredibly
responsive. During DDEV startup, check 'ddev describe' to list the containers, and check that redis exists.
If it doesn't:

```bash
ddev add-on get ddev/ddev-redis
ddev restart 
```

---

## Connecting Foundry VTT

### Foundry Agent Bridge setup

The "Bridge" module in Foundry needs to know how to talk to your Agent.

If both Foundry and the Agent are running on the same machine then the Bridge (Foundry module) settings:

- **Agent URL**: `https://cyberpunk.ddev.site` (if you've not changed the .env file - find it at APP_URL in the .env).
  If you run ```bash ddev describe``` in the Agent directory, you can see the forwarding DDEV is doing in the back. Useful for troubleshooting ports.
  For the local (not shared) instance, it will be the APP_URL in the .env file.
  For the shared instance (your players either locally or online) it will be the NGROK or CF tunnel url.
- **Reverb Port**: `8080` (default for self-hosting, however if using Tailscale - 8081).
- **Reverb Use SSL**: Off, unless using Tailscale.
- **Reverb App Key**: `my-app-key` in the .env file, or whatever you changed it to. If using the Live agent it should be ```itdsiqtx8cpskowawl1p```
- **API Key**: Your API key, generated within your Agent.
- **Channel ID**: Your Channel ID, also found within your Agent.

If you are using my [hosted Agent service](https://agent.surrealdisco.co.uk) and you are sharing your Foundry instance online, then the defaults in the CPR Agent Bridge module should work (aside from the API Key and Channel ID). If you hit trouble, check the Debug mode in the module settings and check the Dev console for some hints. Otherwise come and let me know on the Discord :)

**NOTE: I've found that when I've entered these, Foundry usually needs a refresh OR a restart.**

## Sharing is Caring

Originally I was using (and suggesting) NGROK to share Foundry and connect with local Agent installations. However, after the technical choice to use Reverb and rely on other ports, this has become a REALLY unreliable and painful way of sharing (for free). Using **Cloudflared Tunnels** is a much better alternative to sharing. The simplest way to use Agent is via my hosted version, it's free, and only relies on you sharing your local Foundry instance.

There's a few decent ways - I have my instance on an Unraid server, tunneled out behind SWAG (or an Nginx Proxy Manager), through Cloudflare to a domain I own. It works amazing, but it's complex and way out of scope for this. If you want to learn more, search for IBRACORP on youtube.

Another more friendly way is Tailscale. I find this to be easier to use instead of messing around with Port Forwarding and Firewalls. Check [this](https://foundryvtt.wiki/en/setup/hosting/tailscale) tutorial out. By the end you should have an idea on how to share your Foundry instance over a Tailscale Funnel.

Whilst writing this part, I had been looking for 'easier' ways to suggest sharing, and came across a [DDEV add-on](https://ddev.com/blog/tailscale-router-ddev-addon/) for Tailscale. I have since updated the DDEV install to use said add-on, and whilst useful for a quick share, I found it cumbersome to allow for specific ports. So, for this section, I am going to assume Tailscale is installed on the operating system you are hosting Foundry on.

### Sharing Via Tailscale

#### And using the live Agent (agent.surrealdisco.co.uk)

Lets say you've followed the Foundry tutorial linked above to generate a Funnel & a Tailscale Magic Url. It will look something like ```https://foundry.random-words.ts.net```. If you are using the Live Agent, this would be the URL you would enter into the **Foundry Url** config input. Remember to make sure the CPR Agent Module settings reflect that you are now using TLS, so let the module work it out and keep the **Reverb Port**, **Host** and **enable** the **SSL checkbox**.

At this point, providing all the settings are correct (including API Key and Channel ID), the Live Agent & your Tailscale Funnel will be able to talk to each other, you can:

* Share your Foundry TS url with players
* Have them make an account on the Live Agent, get their API keys and input your Channel ID.
* Have them log on to Foundry via the TS Funnel URL and link their accounts within the Bridge module settings.
* You can assign their characters in your Module settings.
* Have them trigger the Sync in the Live Agent in Config, and their character should appear. Have them save it, and you should be good to go.

#### Using a Local Agent

If you're like me and require control over every digital aspect of your life, then read on. We'll be leveraging DDEV's portmapping + Tailscale's very annoying port limit.

1. Follow the Installation Steps above. Once you've got a local instance running on a browser you can access, read on.
2. Follow the tutorial for creating a Tailscale Funnel for your Foundry Instance (above). Once you can access that from the Magic URL, continue.
3. Run `ddev describe`. We need the port numbers that are mapped to `web:80` and `web:8080` (they tend to be above 32000). Remember those.
4. We will now be setting up two more Funnels, one for each port:
   ```bash
   tailscale serve --bg --https 10000 http://127.0.0.1:80 
   tailscale serve --bg --https 8443 http://127.0.0.1:8080
   ```

***(Note: Replace 80 and 8080 with the actual port numbers from `ddev describe`)***

5. This adds ports 10000 and 8443 to your Magic URL for your computer. If you go to ```https://foundry.random-words.ts.net:10000``` you should end up on your agent! Remember to make sure Reverb is still running.
6. In Foundry, in the CPR Bridge settings:
   **Agent URL:** ```https://foundry.random-words.ts.net:10000```
   **Reverb Port:** 8443
7. Press save. A Bridge Online message should pop up, and that is connected!
8. Go to your Agent to test everything out. Sharing the ```https://foundry.random-words.ts.net:10000``` will give your players the Agent app.

## Settings Reference

### Agent App Settings

- **Foundry URL**: The base URL of your Foundry VTT instance.
  - *Why*: This allows the Agent to fetch images (tokens, items) from your Foundry server.
  - *Tip*: Do NOT include `/game` at the end. It should look like `http://localhost:30000` or `https://foundry.example.com`.
- **API Key**: A unique token generated via Laravel Sanctum.
  - *Why*: It authenticates your Agent interface with the backend.
  - *Note*: You must click 'Generate' in the settings to create one.
- **Channel ID**: A unique identifier for your game session.
  - *Why*: This groups the GM and players into the same communication channel.
  - *Usage*: The GM generates it, and players must "Commit Sync" using the same ID.

### Foundry Bridge Settings

- **Controller URL**: The URL where the Agent app is hosted.
- **API Key**: Your personal API key from the Agent app.
- **Channel ID**: The GM's Channel ID.
- **Reverb Port**: The port used by the websocket server.
  - Default for official Agent: `443` (TLS) or `80` (Non-TLS).
  - Default for self-hosting (DDEV): `8081` (HTTPS) or `8080` (HTTP).
- **Reverb Use SSL**: Enable (`wss`) if your Agent is hosted behind HTTPS. Disable (`ws`) for plain HTTP.
- **Host Override**: Explicitly set the hostname for the websocket connection.
  - *Example*: If using NGROK for Reverb, put the NGROK hostname here (e.g., `random.ngrok-free.app`).
- **Debug Mode**: Enables verbose logging in the Foundry console (F12). Use this to troubleshoot connection issues.

---

## Technical Deep Dive: The Bridge

The Bridge module is the heart of the Foundry VTT integration. It acts as a bridge between the Foundry environment and the Laravel-based Agent.
It can be found at this [repository](https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge) or on the Foundry package manager.

### How it Works

1. **Connection**: It initializes a `Laravel Echo` instance using `Pusher` (via the Reverb broadcaster).
2. **Authentication**: It uses the **API Key** (Sanctum Token) in the headers to authenticate with the Agent's API.
3. **Subscriptions**:
   - The Bridge joins a private channel: `cpr-agent.{channelId}`.
   - It listens for events sent by the Agent (all events are labelled in capitalised SNAKE_CASE).
4. **Synchronization**:
   - When a player performs an action in Foundry, the Bridge intercepts it and sends data to the Agent.
   - When the Agent sends a command (e.g., "Roll Initiative"), the Bridge executes it in Foundry.
5. **Data Flow**:
   - **Foundry -> Agent**: Uses standard API calls (POST/PATCH) to update character data.
   - **Agent -> Foundry**: Uses WebSockets (Reverb) for real-time triggers and updates.

### The `CyberpunkBridge` Class

The class manages:

- **Roll Interception**:
  - The Bridge hooks into Foundry's roll mechanism.
  - It captures the roll data (formula, results, modifiers) and sends it to the Agent so players can see the "virtual dice" result.
- **Actor Updates**:
  - It watches for changes in Foundry Actors (HP, Armor, Humanity, etc.).
  - When a change is detected, it pushes the updated stats to the Agent via the API.
- **Initialization**:
  - Automatically attempts to connect on Foundry startup.
  - It checks if `apiUrl`, `apiKey`, and `channelId` are set before trying to establish a WebSocket connection.

### Communication Protocol

The Bridge uses a combination of REST API (for state changes) and WebSockets (for events).

- **REST**: `POST /api/foundry/callback|update` - Used for bulk updates of character data.
- **WebSockets**: `cpr-agent.{channelId}` channel - Used for instantaneous triggers like "Request Roll" or "Ping Location".

---

## The Delta System

To ensure a smooth experience and minimize data usage, the Agent uses a custom "Delta" synchronization system for character data.

### How it Works

Instead of sending the entire character JSON (which can be several hundred kilobytes) every time a value changes, the system sends a **Delta**.

- **Dot-Notation Paths**: The system uses dot-notation to target specific fields (e.g., `system.stats.emp.value`).
- **Item Handling**: It can target individual items within the character's inventory using a special syntax:
  - `data.items.ID`: Updates a specific item's properties.
  - `data.items.+ID`: Appends a new item to the character sheet.
- **Null Values**: Setting a path to `null` deletes that field or item.

### Why it Matters (Space & Speed)

1. **Bandwidth Savings**: Full character sheets in Foundry VTT are massive. Delta updates are typically less than 1% of the size of a full update. This makes the Agent extremely responsive even on slower mobile connections.
2. **Server Performance**: The Laravel backend merges these deltas into the cached state in memory instantly. This avoids the overhead of parsing and replacing massive JSON blobs for minor changes.
3. **Async Persistence**: Deltas are applied to a high-speed cache first, and the full state is synced to the database asynchronously. This ensures that UI updates are near-instantaneous while still maintaining data integrity.

---

## Troubleshooting

### Images not showing up in the Agent

- **Foundry URL**: Ensure the Foundry URL in the Agent settings does NOT end in `/game`.
- **CORS Issues**: If your Foundry is on `http` and the Agent is on `https`, most browsers will block image loading. Ensure both use `https`.
- **NGROK**: If using NGROK, the Agent includes special headers to bypass the NGROK splash page. If images still fail, check the browser console (F12) for CORS errors.

### Connection "Offline" in Foundry

- **API Key**: Ensure you have generated a valid API key in the Agent app and pasted it correctly into Foundry settings.
- **Reverb Port**: If self-hosting, ensure you are using the correct port (typically 8080 or 8081).
- **SSL Mismatch**: If your Agent uses `https`, you MUST enable "Reverb Use SSL" in Foundry.

### Rolls not appearing in the Agent

- **Bridge Subscription**: Check the Foundry console. You should see `[Bridge] Subscription successful`. If not, check your `Channel ID`.
- **Character Assignment**: Ensure the GM has assigned a character to your user in the `Agent Character Assignment` menu.

---
