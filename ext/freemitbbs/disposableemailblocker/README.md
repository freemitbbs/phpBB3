# Disposable Email Blocker

Blocks phpBB registration and account email changes when the email domain appears in `data/domains.txt`.

The bundled blocklist was downloaded from:

`https://raw.githubusercontent.com/doodad-labs/disposable-email-domains/main/data/domains.txt`

If a domain is incorrectly blocked, add it to `data/allowlist.txt` and purge the phpBB cache.

Refresh the upstream list with:

```sh
ext/freemitbbs/disposableemailblocker/script/update-domains.sh
```
