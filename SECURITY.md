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

## How input is handled

For anyone auditing the plugin, this is the approach it takes. It is not a claim that the code is
flawless: it is what to check, and where a mistake would most likely be.

- **Every game action is a POST** carrying a WordPress nonce, checked with `wp_verify_nonce()`
  before anything happens, and refused outright unless the visitor is signed in and has a pilot.
- **Posted fields are read through one helper** that discards anything which is not a scalar, then
  casts to the type wanted: `(int)` for quantities and ids, `sanitize_key()` for fixed choices such
  as a commodity or action, `sanitize_text_field()` for names and `sanitize_textarea_field()` for
  message bodies. Passwords are passed to WordPress's own hashing untouched.
- **Every SQL statement with a variable in it uses `$wpdb->prepare()`** with placeholders. Values
  are never concatenated into SQL.
- **Where a column name is chosen at runtime** (moving cargo, trading a commodity), the name is
  matched against a fixed whitelist in the code first, so a request cannot introduce one.
- **Output is escaped at the point of printing** with `esc_html()`, `esc_attr()` or `esc_url()`,
  and message text is escaped before `nl2br()`. Nothing from the database is echoed raw.
- **No file paths come from user input.** The only dynamic `include` statements use keys from a
  fixed list of pages, so directory traversal has nothing to act on.
- **No shell, `eval()`, `unserialize()` or dynamic code execution** anywhere in the plugin.
- **Ownership and capability are checked on the action, not the page.** Landing, cargo transfers,
  recalling fighters and reading mail all verify that the pilot owns the thing; admin functions
  require `manage_options` plus their own nonce. Hiding a link is never treated as a control.
- **Redirects are validated** with `wp_validate_redirect()`, so a crafted form cannot bounce a
  player off-site.
- **Turn, credit and stock changes are atomic** single UPDATE statements with the guard in the
  WHERE clause, so a double-submitted form cannot spend the same turn or credits twice.

## If you deploy from a git clone

The plugin is installed as a folder of files, so whatever is in that folder sits under your web
root. A clone of the repository carries a `.git` directory holding the whole project history; the
released zip does not. The plugin ships an `.htaccess` that refuses `.git`, `*.sql`, `*.md`, logs
and editor leftovers, and an `index.php` in every directory so nothing can be listed, but
`.htaccess` is Apache-only and nginx needs the rules in the README. The safe course is not to put
`.git` on the server at all: install the released zip, or build one with `git archive`, as
[Building a release zip](README.md#building-a-release-zip) describes.

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
