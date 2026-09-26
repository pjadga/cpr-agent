---
title: Overview
slug: home
order: 1
---

# CPR Agent Wiki

This is where everything past "what is this thing?" lives. Find the row that
matches what you're trying to do and start there.

## I want to use the Agent

| | |
|---|---|
| **Just play** (no hosting at all) | Use my [hosted Agent](https://agent.surrealdisco.co.uk). All you need to do is share your Foundry instance. Start with **[Getting Started](/wiki/getting-started)**. |
| **Host it myself** | **[Herd Setup Guide](/wiki/setup-herd)**. This is the one I'd point you at on Windows and macOS. One address, no Docker. |
| **Host it with DDEV** | [Dev Env Setup: DDEV](/wiki/guide). For Linux, or if you already live in DDEV. |
| **Host it with no dependencies at all** | [Standalone / FrankenPHP](/wiki/standalone). A single binary, no PHP or Node install. Still rough around the edges. |

## Reference

| | |
|---|---|
| [Getting Started](/wiki/getting-started) | Accounts, QR pairing, linking your players, Data Pools and troubleshooting. |
| [Dev Env Setup: DDEV](/wiki/guide) | The DDEV install, connecting Foundry, sharing, the settings reference, and a look under the hood of the Bridge and the delta system. |
| [Architecture](/wiki/architecture) | How the Agent, Foundry and the Bridge talk to each other: request patterns, event types, caching and the callback contract. |
| [Internal API: Agent](/wiki/internal-api-agent) | What the Agent and the Bridge actually send each other: every route, event type and reply. |
| [External API: Future](/wiki/external-api-future) | A scratchpad of ideas for a deeper integration with the CPR module. None of it exists yet. |
| [Testing](/wiki/testing) | The test suite and how to run it. |

## Which setup doc do I actually want?

There are three, and yes, they overlap a bit:

- **[Herd Setup Guide](/wiki/setup-herd)** is the one to follow. It's current, it
  matches how the hosted version is built, and you end up with a single address
  and no WebSocket port to fiddle with.
- **[Standalone](/wiki/standalone)** is for running the Agent as one
  self-contained binary. No PHP, Node, Docker or database to install.
- **[Dev Env Setup: DDEV](/wiki/guide)** is the DDEV route. It's on one
  address now too, just like Herd, but it needs Docker (plus WSL2 on Windows).
  Great on Linux, or if you already use DDEV.

Whichever one you pick, **[Getting Started](/wiki/getting-started)** is next.
Pairing, Data Pools and troubleshooting all live there.

## Working on the app itself

The DDEV environment in this repo is set up to mirror a Herd install, so what
you test is what a self-hoster gets. The
[contributor appendix](/wiki/setup-herd#appendix-testing-both-topologies-contributors)
covers the topology toggle and the checks worth running.
