# Disposable Email Blocker

Blocks phpBB registration and account email changes when the email domain appears in `data/domains.txt`.

The bundled blocklist was downloaded from:

`https://raw.githubusercontent.com/doodad-labs/disposable-email-domains/main/data/domains.txt`

If a domain is incorrectly blocked, add it to `data/allowlist.txt` and purge the phpBB cache.

The extension also registers a phpBB cron task, `cron.task.freemitbbs.disposableemailblocker.refresh_domains`, which refreshes the upstream list once a week. Runtime refreshes are written to `store/freemitbbs/disposableemailblocker/domains.txt`; if that file is missing, phpBB falls back to the bundled `data/domains.txt`.

Refresh the upstream list with:

```sh
ext/freemitbbs/disposableemailblocker/script/update-domains.sh
```
