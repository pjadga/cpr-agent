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
| **Just play** (no hosting at all) | Use my [hosted Agent](https://agent.surrealdisco.co.uk). All you need to do is share your Foundry instance. Setup is in the [Guide](/wiki/guide#connecting-foundry-vtt). |
| **Host it myself** | **[Herd Setup Guide](/wiki/setup-herd)**. This is the one I'd point you at on Windows and macOS. One address, no Docker. |
| **Host it with no dependencies at all** | [Standalone / FrankenPHP](/wiki/standalone). A single binary, no PHP or Node install. Still rough around the edges. |

## Reference

| | |
|---|---|
| [Guide](/wiki/guide) | The original manual: QR pairing, Data Pools, the delta system, settings, and a look under the hood of the Bridge. Still the best place to learn *how the pieces fit together*. |
| [Architecture](/wiki/architecture) | How the Agent, Foundry and the Bridge talk to each other: request patterns, event types, caching and the callback contract. |
| [API Endpoints](/wiki/api-endpoints) | My working list of the endpoints between the Agent and Foundry. |
| [Testing](/wiki/testing) | The test suite and how to run it. |

## Which setup doc do I actually want?

There are three, and yes, they overlap a bit:

- **[Herd Setup Guide](/wiki/setup-herd)** is the one to follow. It's current, it
  matches how the hosted version is built, and you end up with a single address
  and no WebSocket port to fiddle with.
- **[Standalone](/wiki/standalone)** is for running the Agent as one
  self-contained binary. No PHP, Node, Docker or database to install.
- **[Guide](/wiki/guide)** has the older DDEV instructions, which put the
  WebSocket server on a second port. That still works, but it's behind most of
  the setup problems people have hit, and it needs Docker (plus WSL2 on
  Windows). Use the Herd guide for new installs, then come back to the Guide
  for everything *after* setup. Pairing, Data Pools, deltas and troubleshooting
  all live there.

## Working on the app itself

The DDEV environment in this repo is set up to mirror a Herd install, so what
you test is what a self-hoster gets. The
[contributor appendix](/wiki/setup-herd#appendix-testing-both-topologies-contributors)
covers the topology toggle and the checks worth running.
