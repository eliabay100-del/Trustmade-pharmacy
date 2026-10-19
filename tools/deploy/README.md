# Deployment tooling

Scripts for getting a change from this repository onto the live site at
`https://trustmed.pharmacy.et`.

Plain-language instructions for the pharmacy are in
[`docs/UPDATES.md`](../../docs/UPDATES.md). This file is for whoever is
driving the scripts.

These scripts are **not** part of the application. They never go onto the
server, and `build-update.ps1` skips `tools/` for exactly that reason.

---

## The constraints these exist for

The live host is a Plesk subscription on Ethio Telecom's shared platform. It
has, by observation:

- **no SSH** and no terminal in the panel;
- **no cron / scheduled tasks** on this subscription;
- `exec()` usable but with no honest guarantee about which binaries exist;
- PHP limits locked by the host: 128 MB memory, 30 s `max_execution_time`,
  2 MB upload, `open_basedir` pinned to the vhost;
- FTP and Amazon S3 as the only remote-storage options for its own backups,
  and scheduled remote backups are a paid add-on.

So: updates are a ZIP uploaded through the File Manager, and anything that
needs a command run is done by a token-guarded PHP page that deletes itself.

The one thing that makes the manual upload survivable is that the application
is deliberately left **uncached** in production — `optimize` is never run, only
`optimize:clear`. Compiled Blade views recompile on their own when a template
is newer, so a code or template change takes effect the moment the file lands.

---

## `build-update.ps1`

Builds the ZIP that gets uploaded.

```powershell
# Everything that has changed since the "deployed" tag (the normal case)
.\build-update.ps1

# Since a specific commit
.\build-update.ps1 -Since 5ecb02c

# An explicit list, when git cannot know
.\build-update.ps1 -Files routes/web.php,app/Models/User.php

# Add a file the rules would otherwise leave behind - a newly optimised
# branding logo, for instance, which lives under storage/
.\build-update.ps1 -Include storage/app/public/branding/trustmed-logo.png

# Force a maintenance page in even if nothing appears to need one
.\build-update.ps1 -Maintenance
```

What it does, in order:

1. **Works out what changed.** Default comparison is the working tree against
   the `deployed` tag — which means uncommitted work is included, because
   that is usually what is being deployed. New untracked files are picked up
   too. `-Since` or `-Files` override it.
2. **Refuses to upload things that must not be on a web server.** Tests,
   docs, `tools/`, dotfiles, `.md` files, `storage/`, `node_modules/`,
   `public/build/`, and the JS build config all stay behind. It prints what it
   skipped so nothing disappears quietly.
3. **Warns about the files that need more than a copy.** `composer.json`,
   `config/`, and seeders get called out. Migrations are called out *and* get
   the maintenance page added, because a file copy alone cannot run them.
4. **Reports files that no longer exist.** A deleted file stays on the server
   until somebody removes it, which is exactly the sort of thing that leaves
   two versions of the truth. It lists them for manual deletion.
5. **Syntax-checks every PHP file** with `php -l` before it goes near the ZIP.
6. **Zips with forward-slash entry names.** This is not a detail.
   `Compress-Archive` and .NET's `CreateFromDirectory` write
   `app\Services\X.php`; Linux unzips that as a single file whose name
   contains a backslash, and the application silently keeps running the old
   code. The script builds the archive entry by entry so the separator is
   always `/`.
7. **Prints the manifest and the next steps**, including the maintenance URL
   when there is one.

The maintenance token is generated fresh per build (64 random hex characters)
and is never written into the repository.

### The `deployed` tag

After an upload has been verified on the live site, record what is running:

```powershell
git tag -f deployed
git push --force --tags
```

Then the next `build-update.ps1` starts from the right place. Without it, the
script falls back to comparing against `HEAD`, which finds only uncommitted
work — fine for a quick single-change update, wrong for anything else.

---

## The maintenance page

`templates/maintenance.php` is the template. The build script bakes in a token
and adds it to `public/_maintenance.php` **only when the update contains a
migration**, or when `-Maintenance` is passed.

Opened once with the right token, it:

1. runs `migrate --force`;
2. runs `optimize:clear`;
3. runs `pmas:preflight` (the application's own health check);
4. makes a real web request to `/up` to prove the site boots for a visitor,
   not just from a console;
5. deletes itself — unless a step failed, in which case it stays put and says
   so, so the command can be retried without another upload.

Every guard is a fixed command; there is no parameter that can be talked into
running something else.

---

## `check-live.ps1`

Requests the public pages anonymously and reports what a stranger sees.

```powershell
.\check-live.ps1
.\check-live.ps1 -Site https://staging.trustmed.pharmacy.et
```

It checks `/`, `/login`, `/up`, the built stylesheet, the favicon — and that
`/_setup.php`, `/_maintenance.php` and `/_cache.php` all return **404**, which
is how we know the one-shot pages really did delete themselves.

It also reads the sign-in page and fails if it contains a Laravel error
page, or if it is missing the pharmacy's name.

Most of the application sits behind the login, so it cannot check the
signed-in screens. Open those in a browser: the dashboard, Start a sale, the
stock list, and anything the change touched.

**Run it after every upload, and again a few minutes later.** A PHP syntax
error in a rarely-visited file will not show up on the pages you happen to
click.

---

## Restoring a backup into a scratch database

Worth doing once, and again any time the dumper changes. It is the only way to
know a backup is a backup rather than a file that merely has the right name.

1. Create an empty database, e.g. `pmas_restore_check`.
2. Import the `.sql` from the Backups screen.
3. Compare the table checksums with the live database:

```sql
CHECKSUM TABLE drugs, products, purchase_batches, issued_items, users;
```

They should match exactly. Then drop the scratch database.

This was last done when the dumper was written: all 37 tables matched
byte for byte.
