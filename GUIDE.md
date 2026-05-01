# CPR Agent Guide

This guide provides detailed instructions on how to set up, self-host, and connect the CPR Agent with Foundry VTT.

## Table of Contents
1. [Getting Started](#getting-started)
2. [Self-Hosting Guide](#self-hosting-guide)
3. [Connecting Foundry VTT](#connecting-foundry-vtt)
    - [Local Instance](#local-instance)
    - [Shared via NGROK](#shared-via-ngrok)
    - [Shared via Cloudflare Tunnel](#shared-via-cloudflare-tunnel)
4. [Settings Reference](#settings-reference)
5. [Technical Deep Dive: The Bridge](#technical-deep-dive-the-bridge)

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
4. Players are now synced! Stats and rolls will flow in real-time. Once synced, only the GMs instance of Foundry is required to be open for this to work.

### 5. Extras
* Save the page to your Homescreen if using a mobile or tablet. It will remove the address bar and it will have more of an 'app' feel.
* On Safari, Hit the 3 dots in the bottom right -> Share -> Scroll Down -> Add to Home Screen.
* From Google: "
  To the right of the address bar, tap More More and then Add to home screen and then Create shortcut."

---

## Self-Hosting Guide

If you prefer to host your own instance of the CPR Agent, follow these steps.

### Prerequisites
- [Docker](https://www.docker.com/)
- [DDEV](https://ddev.readthedocs.io/) (Recommended for easy setup)

### Installation Steps
1. **Clone the repository**:
   ```bash
   git clone https://gitlab.com/surrealtech/cyberpunk-red-agent
   cd cyberpunk
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
4. **Setup Database & Assets**:
   ```bash
   ddev artisan migrate:fresh
   ddev npm run build
   ```
5. **Environment Configuration**:
   Copy `.env.example` to `.env` and configure your settings. Key variables include:
   - `APP_URL`: Your local or public URL.
   - `REVERB_HOST`: The hostname for the websocket server.
   - `REVERB_PORT`: Typically 8080 for local development.

**Note** The DDEV setup I have created uses Redis as the caching system instead of the database. This makes it incredibly
responsive. During DDEV startup, check 'ddev describe' to list the containers, and check that redis exists. 
If it doesnt: 
```bash
ddev add-on get ddev/ddev-redis
ddev restart 
```
---

## Connecting Foundry VTT

The "Bridge" module in Foundry needs to know how to talk to your Agent instance.

### Local Instance
If both Foundry and the Agent are running on the same machine:
- **Controller URL**: `http://localhost` (or your DDEV URL).
- **Reverb Port**: `8080` (default for self-hosting).
- **Reverb Use SSL**: Off.

### Shared via NGROK
To share your local Foundry and Agent with players over the internet:
1. **Expose the Web Port**: Use `ngrok http 30000` for Foundry and another tunnel for the Agent app.
2. **Expose Reverb**: Reverb (the websocket server) runs on port `8080`. You **must** expose this port for the Bridge to work.
3. **NGROK Configuration**: You can use a configuration file (`ngrok.yml`) to open multiple tunnels at once.
   ```yaml
   tunnels:
     foundry:
       proto: http
       addr: 30000
     agent:
       proto: http
       addr: 80
     reverb:
       proto: http
       addr: 8080
   ```
4. **Settings**:
   - In the Agent app, set the **Foundry URL** to your NGROK URL (e.g., `https://random-id.ngrok-free.app`).
   - In Foundry Bridge settings, set the **Host Override** to your NGROK URL for the Reverb tunnel.

### Shared via Cloudflare Tunnel
Cloudflare Tunnels are a more stable alternative to NGROK.
1. Install `cloudflared`.
2. Create a tunnel and route traffic for your Agent app and Reverb port.
3. Ensure the Reverb port (8080) is accessible via its own hostname or a subpath.

---

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
It can be found at this (repository)[] or on the Foundry package manager.

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
