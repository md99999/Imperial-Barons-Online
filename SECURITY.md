# Security Policy

## Reporting a vulnerability

Please report security problems privately, by email, rather than opening a public issue:

**sysop@maddogproductions.online**

Please do not post the details publicly until a fix is available, so that sites running the game
are not exposed in the meantime.

### What to include

The more of this you can give, the faster it can be fixed:

- what the problem is, and what an attacker could do with it
- the steps to reproduce it, ideally with the exact request, URL or form involved
- the plugin version (shown on the Plugins screen and in the game's footer bar)
- WordPress and PHP versions, and anything unusual about the site
- whether the attacker needs to be signed in, and with what role
- any proof-of-concept code, patch or suggested fix you have

### What to expect

This is a hobby project maintained by one person, so please be patient. The aim is to acknowledge
a report within a week, agree what the problem is and how serious it is, fix it and release a new
version, and credit you in the release notes if you would like that. No bounty is offered.

## In scope

Anything in this plugin's own code, for example:

- a player action that works without being signed in, or without the right pilot, team or ownership
- one player affecting another's ship, planets, credits or messages when the rules should not allow it
- SQL injection, cross-site scripting, cross-site request forgery, or a missing capability check
- an administrator-only function reachable by someone who is not an administrator
- a way to gain turns, credits or cargo that the game's rules do not permit

## Out of scope

- vulnerabilities in WordPress core, other plugins, themes, PHP, MySQL or the web server: report
  those to the projects concerned
- anything that requires an administrator account to exploit, since administrators can already
  edit plugin code and run arbitrary SQL
- missing hardening on the site around the plugin, such as weak passwords, no HTTPS, open
  registration without an anti-bot check, or an out-of-date WordPress install
- denial of service through sheer volume of requests, which is a hosting concern

## Supported versions

Only the latest release is supported. Fixes are made on the current version rather than backported,
so please update before reporting a problem, and keep sites up to date.

## No warranty

As set out in the [README](README.md) and the [GNU General Public License v2](LICENSE), this
software is provided **as is, without warranty of any kind**. You install and run it at your own
risk, and the author accepts no responsibility or liability for any loss, damage or compromise
arising from its use. Every effort is made to write it safely, but new vulnerabilities are
discovered in software of every kind every day and no website can be guaranteed secure.
