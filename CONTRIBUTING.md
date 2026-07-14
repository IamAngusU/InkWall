# Contributing to InkWall

Thanks for helping improve InkWall. Contributions should preserve the project's
paper-like interface, privacy boundary, and explicit moderation model.

## Before opening an issue

- Search existing issues first.
- Do not include private notes, access tokens, raw IP addresses, or production
  database contents.
- For security-sensitive findings, do not open a public issue. Contact the
  maintainer through the link on [angusu.de](https://angusu.de/).

## Local setup

InkWall requires PHP 8.1 or newer with PDO SQLite.

```bash
git clone https://github.com/IamAngusU/InkWall.git
cd InkWall
cp .env.example .env
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080` and use test content only. Runtime data belongs in
`data/` and must not be committed.

## Pull requests

Keep each pull request focused and explain:

- the user-visible change;
- why the change is needed;
- privacy or moderation implications;
- how the change was verified.

Before submitting, lint every changed PHP file and test the affected flow in a
desktop and narrow mobile viewport.

```bash
find public integrations tools -name '*.php' -print0 | xargs -0 -n1 php -l
```

Changes to the public surface should remain usable without JavaScript-only
navigation and should preserve light and dark SVG output.
