---
title: Overview
slug: home
order: 1
---

# CPR Agent Wiki

Everything beyond "what is this" lives here. Start with the row that matches
what you're trying to do.

## I want to use the Agent

| | |
|---|---|
| **Just play** ,  no hosting at all | Use the [hosted Agent](https://agent.surrealdisco.co.uk). You only need to share your Foundry instance. Setup is in the [Guide](/wiki/guide#connecting-foundry-vtt). |
| **Host it myself** | **[Herd Setup Guide](/wiki/setup-herd)** ,  the current recommended route on Windows and macOS. One address, no Docker. |
| **Host it with no dependencies at all** | [Standalone / FrankenPHP](/wiki/standalone) ,  a single binary, no PHP or Node install. |

## Reference

| | |
|---|---|
| [Guide](/wiki/guide) | The original in-depth manual: QR pairing, data pools, the delta system, settings reference, the Bridge deep dive. Still the best explanation of *how the pieces work*. |
| [Architecture](/wiki/architecture) | Component and communication overview ,  request patterns, event types, caching and the callback contract. |
| [API Endpoints](/wiki/api-endpoints) | The endpoint surface between the Agent and Foundry. |
| [Testing](/wiki/testing) | The test suite and how to run it. |

## Which setup document do I actually want?

There are three, and they overlap:

- **[Herd Setup Guide](/wiki/setup-herd)** is the one to follow. It is current, it
  matches how the hosted service is built, and it produces a setup with a single
  address and no WebSocket port to configure.
- **[Standalone](/wiki/standalone)** is for running the Agent as a self-contained
  binary with no PHP, Node, Docker or database install.
- **[Guide](/wiki/guide)** contains the older DDEV instructions, which put the
  WebSocket server on a second port. That approach still works but is the source
  of most reported setup problems, and it needs Docker (plus WSL2 on Windows).
  Prefer the Herd guide for new installs; read this one for everything *after*
  setup ,  pairing, data pools, deltas and troubleshooting are all here.

## Working on the app itself

The DDEV environment in this repo is configured to mirror a Herd install, so
what you test is what a self-hoster gets. See the
[contributor appendix](/wiki/setup-herd#appendix-testing-both-topologies-contributors)
for the topology toggle and the checks worth running.
