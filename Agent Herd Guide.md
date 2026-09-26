> [!WARNING]
> **Superseded.** This guide describes the old setup, which shipped a bundled
> `.env` with WebSocket settings you had to edit by hand. That file
> (`.env.herd`) has been removed: the Agent now serves the WebSocket on its own
> address, so there is nothing to point at a second port.
>
> Follow **[`wiki/setup-herd.md`](wiki/setup-herd.md)** instead. It is shorter,
> and it is the one that matches the current code.

# Setting Up the Cyberpunk RED Agent App with Laravel Herd

A guide to running Travisse's Cyberpunk RED Foundry app using **Laravel Herd** instead of Docker/DDEV.

By the end of this, you'll have a working web app that's accessible by both the host PC and other devices on your home network, phones, tablets, laptops, etc. Getting it to talk to the bridge module in Foundry is already pretty well documented, so I'm not covering that here, but if need be I'll gladly update this guide to include those steps too.

> [!IMPORTANT]
> **There is one important note for setting up the bridge connection.** Even though we redirect incoming traffic to your host PC's local IP address, you will still need to use Herd's default URL in the Foundry Bridge module settings. For the app URL setting you will be using the `http://cyberpunk-red-agent.test` address. The rest of the bridge setup information should be fine.

This all probably looks like a lot, and it kinda is, but once it's set up correctly it's just a few clicks and keystrokes to boot up Herd, fire up Reverb and Foundry, then it's go time! A simple Windows CLI batch script can do all that in one click too. I even ended up making a QR code for my players to scan and quickly get to the app when they arrive.

---

## Before You Start

- **This was done on a Windows 11 PC.** I can't help with Mac, but Herd does offer an installer for macOS.
- This guide assumes you **don't have any dependencies installed already** and that you have some familiarity with tinkering around with command line instructions. Even if you don't, I've tried to break it down so it's not too hard to follow as long as you can Google or ask questions when stuck. I'm no pro but I'll help where I can. Honestly, if you can work out how to do any advanced stuff with Foundry, you can probably figure this out too. :D
- These steps get you running for a **locally hosted, in-person session**, where players use a phone, laptop, tablet, or whatever to control their character. I believe you can use the same platform to make your instance accessible from outside your home network, but I can't speak to what those additional steps look like.
- To let other devices connect to your running app, they'll need to connect to your host PC's local IP address directly. I've laid out how to do that later on, but to make your life easier you really want to set up your host machine with a **static IP address**. Google it, there are a hundred guides out there and it's not too complicated. Do this, otherwise you'll potentially be editing your files all the time when your router hands out a new IP.
- This setup **doesn't connect to the greater internet**, so you can't send password reset emails (or at least I don't know how). My players' accounts are just nonsense emails and passwords they can't forget.

> [!NOTE]
> **Terminology:** If I say **Terminal**, I mean either Command Prompt or PowerShell will work. If I call out **PowerShell** specifically, it *has* to be PowerShell.

While I'm no expert on any of this, and there might be an easier way to do it, I've gone through my own instructions twice to make sure they work. It's been a ton of research on how any of this works and then moving it from one platform to another, but writing it all out as I went ended up simplifying the process a lot. This app was just too perfect for my use case to surrender, and I hope this helps you get it online too!

It's been a long and rage-inducing road learning some new skills, but it's worth it!

**With all that out of the way, LET'S DO THIS.**

---

## Step 1, Download and Install Laravel Herd

Herd does basically what Docker does for creating a local live dev environment, except it does so as a native Windows application, no complicated containers or wonky networking issues. Since Travisse's work is built with Laravel, we can spin it up in Herd without too much pain.

The **free version of Herd** will work just fine.

- Download: <https://herd.laravel.com/docs/windows/getting-started/installation>
- Install it along with **any dependencies it asks for**.

---

## Step 2, Clone the Repository

Open a Terminal window and navigate to wherever you want the root folder for the app to live on the host PC. Then run:

```bash
git clone https://gitlab.com/surrealtech/cyberpunk-red-agent
```

This drops the folder `cyberpunk-red-agent` inside whatever directory your Terminal is currently pointing at. Make sure it's in a good permanent home that's easily accessible, or just make a shortcut to it wherever you want.

> [!NOTE]
> The `cyberpunk-red-agent` folder will be referred to simply as the **root folder** from here on out.

- Go inside the root folder you just cloned and locate the file `.env.ddev`. **Delete it.** Replace it with the `.env` file I've included for you.
- Keep this folder handy, we'll be back soon.

---

## Step 3, Set Up Your Site in Herd

- If you didn't already, open Laravel Herd. On first boot it may ask to install a couple of things. Do that. **Skip Pro.**
- From the Herd dashboard, go to **Sites** on the left and create one. Choose **Link existing project** and point it at your root folder.
- Leave everything as-is on the path and name. (I *think* you can name it whatever you want as long as it's reflected in your `.env`, but I haven't tried it myself.) **Leave HTTPS off** as well.
- This step builds some server files we need, but the site won't work just yet. You can minimize Herd for now.

---

## Step 4, Install Dependencies and Generate an App Key

### 4a. Install Node.js

You might already have this if you run a headless Foundry like I do. If not, install it, it's free and facilitates JavaScript and such in the background.

- Download: <https://nodejs.org/en/download>

### 4b. Install Composer Dependencies & Frontend Packages

If you're still in a Terminal from previous steps, close it and open a new one navigated to your root folder. This makes sure it picks up any new system paths from what we've done so far.

Run the following one at a time (they might take a sec):

```bash
composer install
```

```bash
npm install
```

### 4c. Generate an App Key

Still in the same Terminal, run:

```bash
php artisan key:generate
```

This creates the unique key Laravel needs for encryption and automatically inserts it into your `.env` file.

> If that fails, you probably didn't install the dependencies first, or didn't replace the `.env` file correctly back in Step 2.

---

## Step 5, Edit Your `.env` File

I strongly suggest using **Notepad++** for this, for many reasons. It's free and an excellent lightweight tool for editing code blocks cleanly without mangling any lines.

The `.env` I've supplied just needs the local IP address of your host machine. If you don't know how to find it, open a Terminal and run `ipconfig` from anywhere. Look for the line **IPv4 Address**, that's what we're after.

Inside your `.env` file, find the following lines and replace the `X` in the IP address with the correct last digit(s) of your host PC's IP, in **three places**:

| Line | Setting |
| ---- | ------- |
| 6  | `APP_URL=` |
| 27 | `SANCTUM_STATEFUL_DOMAINS=` *(both spots on this line)* |
| 39 | `VITE_REVERB_HOST=` |

Save the file.

---

## Step 6, Set Up the Database

This sets up a simple database for your logins and whatnot. You'll need a **PowerShell** window for this one. Navigate it to your root folder and run these two commands one at a time:

```powershell
New-Item -ItemType File -Path "database\database.sqlite" -Force
```

```powershell
php artisan migrate --seed
```

---

## Step 7, Compile the Front End

Tired of looking at Terminals yet? Too bad, we're getting there though! Run this in your root folder:

```bash
npm run build
```

---

## Step 8, Start the Reverb Server

Copy this next command down somewhere, because you'll need it **every time** you want to spin up the app. It's basically the middle-man between the app's backend and the user interface. It makes everything sync up beautifully and facilitates that "live" environment for your users.

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

> [!WARNING]
> **Leave this window open.** It might look like it's not doing anything, but unless it throws an error, it's quietly doing its job, as long as you don't close this window (or end the process with `Ctrl+C`).

---

## Step 9, Make Sure All This Nonsense Worked Before We Move On

- If you followed these steps exactly, Herd should default the app's URL to **`cyberpunk-red-agent.test`**. Open this in a web browser on the host PC and pray you see that login screen! I bet you will, and if you don't, it's probably a simple fix we can work out.
- To make sure everything is talking to each other, make a throwaway account to log in. You don't even need a real email address, but it does need to be in email format, like `test@test`. If you get a **419**, **500**, or anything else, something got borked in the setup. It could be a large number of things, so carefully retrace your steps and do a little investigating first, then reach out if you need to. Opening your browser's developer tools (usually `F12`) and checking the console for errors is a good place to start.
- If we did it right, you should be looking at the dashboard for the stand-in netrunner character. **Victory!**
- We still need to set up other local devices to connect, but the worst part is over.

---

## Step 10, Set Up LAN Access for Local Devices

Your app is running at `cyberpunk-red-agent.test`, and that's cool, except since that's a BS URL, no device except the host PC knows what it actually is. We've set your host's local IP in the `.env` file, but we need to update Herd about that with a couple of steps.

By default, **Nginx** (Herd's web server) is just looking at your own machine's loopback address. What we're going to do is tell Herd to listen to LAN traffic directed at your host machine's IP and kick that where it needs to go.

**1.** Open a File Explorer window and navigate to:

```text
C:\Users\YOUR_USERNAME\.config\herd\config\nginx\
```

**2.** Open `herd.config` (using Notepad++) and edit the following line near the top:

```nginx
listen 127.0.0.1:80 default_server;
```

Change it to read:

```nginx
listen 0.0.0.0:80 default_server;
```

**3.** A line or two below that, you'll see the line starting with `root` that probably just shows `/` for a value. Replace it with this exactly:

```nginx
root "YOUR_ROOT_FOLDER_DIRECTORY/public";
```

> [!IMPORTANT]
> This must use **forward slashes**. If you copy/paste the directory from File Explorer it'll use backslashes and you'll have problems. Copy/paste is fine, just replace the backslashes (`\`) with forward ones (`/`).

Obviously, replace all the capped text with your root folder's full directory, wherever you stuck it back in Step 2. Save the file.

**4.** Next, using a Terminal inside your root folder, enter the following (replacing the capped text with your IP, the same static one we looked up earlier for your `.env`):

```bash
herd link YOUR_HOST_PC_IP
```

That should direct Nginx, now listening to LAN traffic, to route that traffic to your Agent app.

---

## Step 11, Open the Needed Ports on the Host PC

Run the following in an **elevated PowerShell window (as Admin)** navigated to your root folder. Make sure it's as Admin or it'll throw an error.

```powershell
New-NetFirewallRule -DisplayName "Laravel LAN Access" -Direction Inbound -LocalPort 80,8080 -Protocol TCP -Action Allow
```

---

## Step 12, I Swear We Are So Close, Choom

- On Herd's dashboard, click **Stop all services**, then start them up again.
- Run the following inside a Terminal in your root folder to clear out the cache and apply the changes we've made:

```bash
php artisan optimize:clear
```

- Remember that Reverb process we were supposed to keep open? If it's not still running, fire it up again:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

---

## Moment of Glory

Get your phone out and make sure it's on your Wi-Fi. Click **directly** in your web browser's address bar and type in **exactly** your host IP, nothing before or after. Don't click it from a dropdown (that tends to cause trouble or just try to Google-search it, which obviously isn't going to work).

If everything went smoothly, you should now be looking at the login screen and be able to register an account! If not, it's possible a step was missed, or I might not have accounted for something on your PC that caused the setup to go awry, or maybe my instructions aren't as clear as I thought. Either way, I bet we can figure it out.

---

> [!NOTE]
> **Reminder:** Use the Herd default URL of `http://cyberpunk-red-agent.test` for the Agent URL setting inside the Foundry Bridge module.
