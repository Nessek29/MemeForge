# MemeForge — Meme Generator

## Objective

MemeForge is a small meme generator built with **PHP + SQLite + Bootstrap 5**. It is
the target application for **Lab 6 — Hardening the Application Environment**.

Unlike DevTalk in Labs 4 and 5, MemeForge's *code* already follows secure coding
practices: parameterized queries, output encoding, anti-CSRF tokens, ownership
checks, SSRF-safe URL imports, and validated file uploads. Your job in Lab 6 is to
harden the **environment** the application runs in — the HTTP response headers,
the Content-Security-Policy, the session cookie, and the container it ships in.

## Requirements

- PHP 8.1 or newer with the `pdo_sqlite`, `fileinfo` and `mbstring` extensions
- The `curl` extension (only needed for the *Import from URL* image option)
- [Composer](https://getcomposer.org/)

Check your PHP version and extensions:

```bash
php -v
php -m | grep -i -E 'sqlite|fileinfo|mbstring|curl'
```

> **Windows:** if `fileinfo`, `mbstring` or `curl` is missing, open your `php.ini`
> and remove the `;` in front of `extension=fileinfo`, `extension=mbstring` and
> `extension=curl`.

## Setup

From the `MemeForge` directory:

```bash
# 1. Build and seed the database (also clears uploaded images)
composer reset-db

# 2. Start the development server
composer start
```

Then open <http://localhost:8000> in your browser.

To wipe any changes and start fresh at any time, run `composer reset-db` again.

## Sample accounts

| Username | Password       |
|----------|----------------|
| alice    | `password123`  |
| bob      | `hunter2`      |
| carol    | `letmein2024`  |
| admin    | `Sup3rSecret!` |

You can also register your own account from the **Register** page.

## Features

- Register, log in and log out
- Public gallery of every user's memes, newest first, with search
- Public page for each user listing their memes
- Make a meme from a built-in template, an uploaded image, or an image URL
- Live caption preview while you type
- Only a meme's owner can edit or delete it
- Account settings page to update your email address or delete your account

## Project layout

```
MemeForge/
├── composer.json          # defines `composer start` and `composer reset-db`
├── public/                # web root (point your server here)
│   ├── index.php          # gallery + search
│   ├── meme.php           # view one meme
│   ├── create.php         # make a meme (template / upload / URL)
│   ├── edit.php           # edit captions (owner only)
│   ├── delete.php         # confirm + delete (owner only)
│   ├── user.php           # a user's public meme page
│   ├── profile.php        # account settings: email, delete account
│   ├── login.php / register.php / logout.php
│   ├── assets/            # CSS and meme templates
│   └── uploads/           # stored user images (random file names)
├── src/
│   ├── config.php         # settings and limits
│   ├── db.php             # PDO connection
│   ├── helpers.php        # shared include: session, CSRF, auth, layout
│   └── images.php         # upload validation and SSRF-safe URL import
└── database/              # schema + build/seed script (SQLite file is generated)
```

Every page in `public/` begins by including `src/helpers.php`, which loads the
configuration and database connection and starts the session.

## Troubleshooting

- **`php: command not found`** — install PHP (e.g. `brew install php` on macOS).
- **Database errors on first load** — run `composer reset-db` before `composer start`.
- **Errors are not shown in the browser** — that is intentional. PHP errors are
  written to the terminal running `composer start`.
- **"Importing from a URL needs the PHP curl extension"** — enable `curl` (see above)
  or use a template or file upload instead.
- **Port 8000 in use** — edit the `start` script in `composer.json` to use another port.
