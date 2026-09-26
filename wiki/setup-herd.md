---
title: "Self-Hosting: Laravel Herd"
order: 3
---
# Self-Hosting the CPR Agent with Laravel Herd

*Written by **capt_garris** from the CPR Discord. Big thanks for your time, testing and helping me out.*

This is the current recommended way to run your own Agent on Windows or macOS.
The other route is [Dev Env Setup: DDEV](/wiki/guide), which needs Docker (and
WSL2 on Windows). Once you're up and running, head to
[Getting Started](/wiki/getting-started) to link your players.

By the end you will have **one address** that serves the Agent to you and your
players, with the WebSocket riding the same address. There is no second port, no
second certificate and no Reverb settings to fill in.

> Running the [hosted Agent](https://agent.surrealdisco.co.uk) instead? You don't
> need any of this. You only need to share your Foundry instance.

---

## Contents

1. [Decide how players will reach you](#1-decide-how-players-will-reach-you)
2. [Install Herd and Node](#2-install-herd-and-node)
3. [Get the code](#3-get-the-code)
4. [Run the installer](#4-run-the-installer)
5. [Set your address](#5-set-your-address)
6. [Build the front end](#6-build-the-front-end)
7. [Put Reverb on the same address](#7-put-reverb-on-the-same-address)
8. [Open the firewall](#8-open-the-firewall)
9. [Start the background processes](#9-start-the-background-processes)
10. [Connect Foundry](#10-connect-foundry)
11. [Add your players](#11-add-your-players)
12. [Updating](#updating)
13. [Troubleshooting](#troubleshooting)

---

## 1. Decide how players will reach you

First step: It decides one setting (`APP_URL`) that everything else is derived
from, and changing your mind later means redoing steps 5 and 7.


|                          | Who can use it                                                  | Your`APP_URL`                              |
| ------------------------ | --------------------------------------------------------------- | ------------------------------------------ |
| **A: Just you**          | You only, on this PC. Good for trying it out.                   | `https://cyberpunk-red-agent.test`         |
| **B: Same Wi-Fi**        | Players' phones on your home network.                           | `http://192.168.1.50` (your PC's IP)       |
| **C: Over the internet** | Anyone, anywhere, via Tailscale.**Recommended for real games.** | `https://your-pc.tailnet-1234.ts.net:8443` |

**HTTPS** Browsers only allow camera access on a
secure (HTTPS) connection, so on option B your **external** players cannot scan the QR pairing
code; they have to copy and paste a link code instead. HTTPS also means character
portraits and token art load properly, and the in-Foundry Agent window works.
Tailscale gives you a certificate for free and takes a lot of the headache out of sharing, but is not required for this to work.

> **Note on the URL:** whatever address *players* type is the address that goes in
> `APP_URL`, and the same address goes in the Foundry Bridge module. A
> `.test` address only exists on your own PC. If you give that to the Bridge
> while players are remote, QR pairing and the in-Foundry Agent window will both
> fail even though everything else looks fine.

---

## 2. Install Herd and Node

- **Laravel Herd**: [https://herd.laravel.com](https://herd.laravel.com). The free version is enough.
  Install any dependencies it asks for.
- **Node.js**: [https://nodejs.org/en/download](https://nodejs.org/en/download).

For option C, also install **Tailscale** ([https://tailscale.com/download](https://tailscale.com/download)) and
enable HTTPS in your tailnet admin console under *DNS → HTTPS Certificates*.

---

## 3. Get the code

```bash
git clone https://gitlab.com/surrealtech/cyberpunk-red-agent
cd cyberpunk-red-agent
composer install
npm install
```

Then open Herd, go to **Sites → Link existing project**, and point it at the
folder you just cloned. Leave the default settings.

> **Windows + WSL:** if you unzipped rather than cloned, strip the alternate data
> streams first or PHP will throw odd errors:
> `find . -name "*:Zone.Identifier" -type f -delete`

---

## 4. Run the installer

```bash
php artisan agent:install
```

This creates your `.env` from the template, generates the application key and the
Reverb secrets, creates the SQLite database file and runs the migrations. It
prints the generated secrets once. You don't need to keep them, but don't share
them.

It is safe to run again later; it won't overwrite an existing `.env` or
regenerate secrets unless you pass `--force`.

> **Updating an existing install?** Use `php artisan migrate`. Never
> `migrate:fresh`, which wipes every account and character.

> **Note on PHP & SQLite** In my testing, becuase I have various PHP versions installed, I hit an issue running php artisan agent:install - it suggested that Herd did not have the SQLite extension available (when it clearly does). This is caused by the terminal using a PHP version other than the one Herd is offering. If you hit this, replace `php` with `herd php` in the commands.

---

## 5. Set your address

Open `.env` and set these three lines to match the option you picked in step 1.
On Windows, use Notepad++ or VS Code, not Notepad.

**Option A, just you:**

```dotenv
APP_URL="https://cyberpunk-red-agent.test"
APP_DOMAIN="cyberpunk-red-agent.test"
SANCTUM_STATEFUL_DOMAINS="${APP_DOMAIN},localhost,127.0.0.1"
```

**Option B, same Wi-Fi** (find your IP with `ipconfig`):

```dotenv
APP_URL="http://192.168.1.50"
APP_DOMAIN="192.168.1.50"
SANCTUM_STATEFUL_DOMAINS="${APP_DOMAIN},localhost,127.0.0.1"
```

**Option C, over the internet** (your Tailscale machine name):

```dotenv
APP_URL="https://your-pc.tailnet-1234.ts.net:8443"
APP_DOMAIN="your-pc.tailnet-1234.ts.net:8443"
SANCTUM_STATEFUL_DOMAINS="${APP_DOMAIN},localhost,127.0.0.1"
```

Then:

```bash
php artisan config:clear
```

### Do not set `VITE_REVERB_*`

There are no `VITE_REVERB_HOST`, `VITE_REVERB_PORT` or `VITE_REVERB_SCHEME` lines
in the template, and you should not add any. The Agent works out where the
WebSocket lives from the address the page was loaded on, which is always correct
once step 7 is done. Older guides told you to set these; that advice is out of
date and will break the connection.

Secure cookies are handled for you too: the app reads the scheme from `APP_URL`.
You no longer need to touch `SESSION_SECURE_COOKIE`.

---

## 6. Build the front end

```bash
npm run build
```

Re-run this after every `git pull`.

---

## 7. Put Reverb on the same address

This is the step that removes all the old port juggling. Reverb listens only on
your own machine, and Herd's web server forwards WebSocket traffic to it.

**First, make Herd create a config file for your site.** Herd only writes one
once a site is isolated or secured:

- **Option A:** `herd secure cyberpunk-red-agent`. This also gives you HTTPS
  locally, which is why option A uses an `https://` address.
- **Options B and C:** `herd isolate 8.4`. Tailscale or plain HTTP handles the
  connection, so you don't want Herd's certificate here.

**Then edit the file.** It's at:

```
Windows:  %USERPROFILE%\.config\herd\config\valet\Nginx\cyberpunk-red-agent.test.conf
macOS:    ~/Library/Application Support/Herd/config/valet/Nginx/cyberpunk-red-agent.test.conf
```

Inside the `server { ... }` block, add:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8085;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600s;
}
```

**For options B and C only,** also make the site answer to the address players
use. Find the existing `listen` and `server_name` lines and change them to:

```nginx
listen 0.0.0.0:80;
server_name cyberpunk-red-agent.test 192.168.1.50 your-pc.tailnet-1234.ts.net;
```

Keep any lines Herd put there for the isolated PHP version or the certificate.
Restart Herd's services from its dashboard when you're done.

> **Keep a copy of this file somewhere outside the project.** Herd can overwrite
> it when it updates.

**Option C, start the tunnels.** Two, not three:

```bash
tailscale funnel --bg --https 443 http://127.0.0.1:30000   # Foundry
tailscale funnel --bg --https 8443 http://127.0.0.1:80     # Agent
```

Reverb rides the Agent's tunnel, so it doesn't need one of its own.

---

## 8. Open the firewall

Options B and C only, in an **elevated** PowerShell:

```powershell
New-NetFirewallRule -DisplayName "CPR Agent" -Direction Inbound -LocalPort 80,443 -Protocol TCP -Action Allow
```

Only 80 and 443. If you followed an older guide and opened 8080, you can remove
that rule, since nothing listens there any more.

---

## 9. Start the background processes (I Swear We Are So Close, Choom)

```bash
php artisan agent:serve
```

Leave this window open while you play. It runs the WebSocket server and the queue
worker, and restarts either if it stops.

**The queue worker is not optional.** It writes character data to the database.
Without it the Agent still looks like it works, but changes live only in the cache
and are lost when it clears. Older guides that only start `reverb:start` are
missing this.

Now open your `APP_URL` in a browser and create an account. You should reach the
dashboard.

---

## 10. Connect Foundry

Install the Bridge module in Foundry. Manifest URL:

```
https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json
```

**The quick way.** In the Agent, go to **Settings**, generate an **API Key**, then
click **Copy Foundry Setup Code**. In Foundry, open **Configure Settings → Agent
Bridge → Quick Setup** and paste it. It fills in the Agent URL, Channel ID, API
key and Reverb key together.

**By hand,** if you'd rather. In **Configure Settings → Agent Bridge**:


| Setting            | Value                                       |
| ------------------ | ------------------------------------------- |
| **Agent URL**      | Your`APP_URL`, exactly                      |
| **API Key**        | From the Agent's Settings page              |
| **Channel ID**     | From the Agent's Settings page              |
| **Reverb App Key** | Your`REVERB_APP_KEY` from `.env`            |
| **Reverb Port**    | Leave at`0`                                 |
| **Reverb Host**    | Leave blank                                 |
| **Reverb Use SSL** | On for options A and C,**off** for option B |

Reverb Port and Host stay empty because Reverb is now on the same address as the
Agent: the Bridge works it out from the Agent URL.

Foundry usually needs a refresh after saving. You should see **"Cyberpunk Bridge:
Online"**.

> ⚠️ **Generating a new API key invalidates the old one.** If you regenerate it in
> the Agent, you must paste the new key into Foundry, or the Bridge will start
> returning 401 errors and show as offline. The Agent warns you about this now.

---

## 11. Add your players

Each player needs their **own** API key. They are not shared.

1. The player opens your Agent address, creates an account, and goes to
   **Settings**.
2. As GM, open **Agent Bridge → Manage Characters** in Foundry and click the **QR
   button** next to their name.
3. The player goes to **Settings → Join a Session → Scan to Link Foundry** and
   scans it. If they have no camera, or you're on option B, a copyable link code
   appears under the scan button, send them that instead.

That's it. Their Channel ID, Foundry URL and character assignment are all set
automatically. The QR expires after five minutes and works once.

> **Testing on a player's behalf?** Use a different browser or a private window.
> The Agent stores your login per browser, so setting up a second account in the
> same window overwrites your own.

Once players are linked, only the **GM's** Foundry needs to stay open.

---

## Updating

```bash
git pull
composer install
npm install
npm run build
php artisan migrate
php artisan config:clear
```

Your `.env` is not tracked, so it survives. So does the Herd site config, which
lives outside the project, but back it up anyway, since Herd may rewrite it on
update.

If you followed an older guide and edited files inside the project, `git pull`
will refuse. `git stash`, pull, then check whether you still need those edits.
With this guide, you almost certainly don't.

---

## Troubleshooting

Open the browser console with **F12** and look at the red errors first.


| What you see                                      | What it means                                                                                                                       |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Login bounces straight back to the login page     | `APP_URL` doesn't match the address you're actually using. Fix it, then `php artisan config:clear`.                                 |
| Bridge says**Offline**                            | Agent URL wrong in the module, or`agent:serve` isn't running. On option B, check *Reverb Use SSL* is **off**.                       |
| **HTTP 401** in the Foundry console               | The API key in Foundry is stale. Copy the current one from the Agent's Settings page.                                               |
| Sheet stuck loading forever                       | The WebSocket never connected. Check the console for`[Agent] WebSocket subscription established.` If it's missing, re-check step 7. |
| Player scan says**"QR is for a different Agent"** | The Bridge's Agent URL doesn't match the address the player has open, usually a`.test` address. See step 5.                         |
| Portraits and token art are blank                 | Your Foundry is on`http` while the Agent is on `https`. Put Foundry behind HTTPS too (option C does this).                          |
| Control tab does nothing                          | Usually after the GM changed scenes. The player should refresh the Agent, or re-save in settings.                                   |
| Changes vanish after a while                      | The queue worker isn't running. See step 9.                                                                                         |
| **404** on `/app/...` in the console              | The`location /app/` block from step 7 isn't in effect. Check you edited the right file and restarted Herd.                          |

Still stuck? Turn on **Debug Mode** in the Bridge module settings for verbose
Foundry logging, and come and say hi on the CPRed Discord in the Content Share
section.

---

## Appendix: testing both topologies (contributors)

*You don't need this to set up your own Agent, so skip it. This is for working on
the app itself.*

The DDEV environment in this repo is configured to match a Herd install: Reverb
binds `127.0.0.1:8085` and is reached through `/app/` on the site's own origin
(`.ddev/nginx/reverb-app.conf`), with no `VITE_REVERB_*` anywhere. Ports 8080 and
8081 are not exposed.

```bash
ddev start
ddev agent-serve          # Reverb + queue worker, same as `php artisan agent:serve`
```

The difference between the topologies in step 1 is the scheme in `APP_URL`, and
that is what `session.secure` is derived from, so most scheme-related bugs only
show up on one of them. Switch between them with:

```bash
ddev agent-topology https   # topology A/C: TLS, Secure cookies
ddev agent-topology http    # topology B:  plain HTTP, no Secure flag
ddev agent-topology         # show the current setting
```

DDEV serves the same site on both ports, so no restart is needed. **Browse the
scheme it tells you to**. Loading `https://` while `APP_URL` says `http://` is
itself the misconfiguration this is meant to reproduce.

Confirm the session cookie follows:

```bash
curl -sI http://cyberpunk.ddev.site/login  | grep -i set-cookie   # no `secure`
curl -skI https://cyberpunk.ddev.site/login | grep -i set-cookie  # `secure`
```

Worth knowing what each topology exercises:


|                             | http                        | https     |
| --------------------------- | --------------------------- | --------- |
| Secure cookie derivation    | not Secure                  | Secure    |
| QR camera scanning          | blocked by the browser      | works     |
| Cross-scheme image proxying | n/a                         | exercised |
| In-Foundry Agent window     | blocked if Foundry is https | works     |

Verify the WebSocket is genuinely same-origin. This should return `101` with
`X-Powered-By: Laravel Reverb`, and is the single best check that the setup in
step 7 is correct:

```bash
curl -skI --http1.1 -H "Connection: Upgrade" -H "Upgrade: websocket" \
     -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
     "https://cyberpunk.ddev.site/app/${REVERB_APP_KEY}?protocol=7&client=js&version=8.4.0"
```

Run the tests with `ddev exec ./vendor/bin/phpunit` and `npx vitest run`. They
need `pdo_sqlite`, which the DDEV web container has and a host PHP often doesn't.
