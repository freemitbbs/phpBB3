# themitbbs real IP logging and progressive bans

The existing aggregate limit remains 5 requests/second, burst 5, and 32
concurrent filtered homepage requests. This guard adds visitor IP bans at
Cloudflare. It uses the host's existing Fail2ban; the SSH jail policy is unchanged.

## Activation

The staged production bundle is `/home/hyang/themitbbs-ip-guard`.

1. In Cloudflare, create a custom API token with **Zone / Firewall Services /
   Edit** and **Zone / Zone / Read**. Set Zone Resources to **Include / Specific
   zone / themitbbs.com**. Keep the terminal activation command below ready,
   then copy the token from its creation screen straight into the hidden prompt.
   No Zone ID lookup is needed.
2. On production run:

   ```sh
   sudo python3 /home/hyang/themitbbs-ip-guard/script/install-themitbbs-ip-guard --configure
   ```

   The installer prompts for the hidden token and optional admin IPs or CIDRs
   to exempt. It finds the active themitbbs.com zone using the token's existing
   Zone Read permission. Credentials are verified against the zone name and saved
   as root-owned mode 600 at `/etc/fail2ban/themitbbs-cloudflare.json`. Credentials
   never appear in command arguments or the repository. The preflight checks
   zone and rule read access; write authorization is checked by Cloudflare when
   a real ban is first applied.

   The one-time token is immediately saved in a root-only pending file before
   lookup or validation. If setup fails, rerun the command and press Enter at
   the token prompt to reuse it, or paste a replacement. The pending file is
   removed after verified credentials have been saved.

To enable trusted IP logging before obtaining a token:

```sh
sudo python3 /home/hyang/themitbbs-ip-guard/script/install-themitbbs-ip-guard --realip-only
```

## Behavior

- Only Cloudflare's published IPv4/IPv6 ranges may supply `CF-Connecting-IP`.
  The installer downloads and validates these lists each time it is run. Rerun
  it when Cloudflare changes its ranges; no arbitrary forwarded header is trusted.
- Ordinary access logs and PHP receive the normalized visitor address. Changing
  the address used by phpBB can require existing users to log in again when
  session IP validation is enabled.
- `/var/log/nginx/themitbbs.abuse.log` contains only 429 responses to the existing
  expensive filtered-homepage request class, received through a Cloudflare peer.
  Its fields contain normalized addresses and fixed text, with no supplied URL,
  user agent, or header text that could inject a ban. Existing nginx log rotation
  covers this file and creates logs readable by `adm`.
- A first ban requires 30 such rejections within 10 minutes. Rejections alone
  are a heuristic: a visitor repeatedly refreshing while the aggregate budget
  is full can reach the threshold. Fail2ban's native repeat-offender logic also
  lowers the number of retries required on subsequent offenses.
- Durations: **10 minutes, 1 hour, 6 hours, 1 day, 7 days, 30 days**, capped at
  30 days. Ban history is retained for 60 days in Fail2ban's existing SQLite
  database. This setting increases history retention for all jails without
  changing their thresholds or actions.
- Ban actions use only the configured zone endpoint. A banned IP is blocked
  across this domain, not only on the expensive homepage. Both IPv4 and IPv6
  are supported; Cloudflare proxy ranges, the origin, non-public addresses,
  and admin exemptions cannot be banned by this action.
- Actions check HTTP and API success, and remove only rules bearing this
  guard's exact ownership prefix and expiry timestamp. Independent manual
  Cloudflare rules are preserved. Ban/prolong operations store the Fail2ban
  expiry in Cloudflare rule notes, including restored bans' original start time.
- Fail2ban normally unbans at expiry. A timer also removes expired owned rules
  every five minutes, even if Fail2ban misses the unban. API/network errors
  leave rules for a subsequent expiry retry. Token expiry or revocation prevents
  both bans and unbans until credentials are repaired.
- Distributed sources below the IP threshold can still pass the IP rule; the
  aggregate capacity guard continues to protect PHP and MariaDB.

## Operations

```sh
sudo fail2ban-client status themitbbs-expensive-home
sudo fail2ban-client set themitbbs-expensive-home unbanip VISITOR_IP
sudo /usr/local/sbin/themitbbs-cloudflare-ban check
systemctl status themitbbs-cloudflare-expiry.timer
```

Review `/var/log/fail2ban.log` for ban actions and API errors. Check
`journalctl -u themitbbs-cloudflare-expiry.service` for expiry maintenance.
The installer backs up files under `/var/backups/themitbbs-ip-guard.*`, checks
nginx and Fail2ban syntax, reloads services, and requires HTTP 200 from both
`/index.php` and `/app.php/blog`. It restores nginx first if activation fails.
Use the exact rollback command printed by the installer to undo a deployment.
Credentials remain available for cleanup and are not removed by rollback.

## Edge challenge for rapidly rotating sources

The IP jail cannot identify an IP making only a few rejections in its ten-minute
window. An additional Cloudflare Managed Challenge can cover every nonempty
`ff` filtered homepage request, before it reaches nginx, PHP, or MariaDB. The
candidate is in `cloudflare-challenge.json`. It covers the two site hostnames,
`/`, `/index.php`, and index PATH_INFO, including all four cases of the `ff`
parameter name. Existing configured admin IP exemptions are respected. Ordinary
visitors to the filtered homepage may see a browser verification step; successful
verification receives Cloudflare's clearance cookie. This is an extra barrier,
and does not guarantee that all automation will fail it.

On production, activate it using the already saved credential file:

```sh
sudo python3 /home/hyang/themitbbs-ip-guard/script/themitbbs-edge-challenge --apply
```

This requires **Zone / Zone WAF / Edit** on the existing token with its zone
resource restricted to themitbbs.com. Edit the token's permissions if needed;
the script reads the existing secret, so no Zone ID or token must be pasted.
Invoking it without `--apply` prints the plan and reads the current ruleset.

The script backs up the current custom ruleset, adds or updates only the rule
with ref `themitbbs_expensive_home_guard_v1`, and places it first to avoid
an earlier Skip rule bypassing it. It checks API confirmation and requires
HTTP 200 from the public unfiltered index and blog. A failed activation attempts
to restore the previous owned rule or disable a newly created rule immediately.
Other rules retain their relative order.

To disable this challenge:

```sh
sudo python3 /home/hyang/themitbbs-ip-guard/script/themitbbs-edge-challenge --disable
```

After activation, compare origin filtered-homepage arrivals and HTTP status
counts. The online guest count is a rolling thirty-minute session count and
can remain elevated until older sessions leave the window. Check Cloudflare's
Security Events for matched challenges and whether visitors complete them.

## Temporary block for the observed incident pattern

If attack clients continue to pass the Cloudflare challenge, a temporary nginx
rule can reject the observed combination before PHP: GET filtered homepage,
`toptopics_home=merged`, `toptopics_view=classic`, no Referer, and either of the
two exact browser strings in `nginx-pattern-http.conf`. It does not depend on
the source IP. All conditions must match. User agents and Referer are supplied
by clients, so an attacker can change them to evade this temporary rule.

Visitors using these exact browsers with bookmarked/shared filtered URLs, or
browsers suppressing Referer, can also be blocked. This is an incident heuristic;
it does not authenticate visitors or prove that every matching request is abuse.
The shared rate limit and Cloudflare challenge continue to protect other requests.

Activate on production:

```sh
sudo python3 /home/hyang/themitbbs-ip-guard/script/install-themitbbs-pattern-block
```

The installer backs up nginx configuration, checks syntax, reloads nginx, and
requires HTTP 200 from the origin index and blog. On failure it restores first.
It prints a rollback command. Matching requests return 403, so they do not count
toward the existing Fail2ban jail's threshold of repeated 429 responses.

Disable after the incident or if legitimate visitors are affected:

```sh
sudo python3 /home/hyang/themitbbs-ip-guard/script/install-themitbbs-pattern-block --disable
```

The updated candidate also rate-limits the later unfiltered-homepage burst:
GET `/` or `/index.php`, no query parameters or Referer, and the exact observed
Mac Firefox 135 browser string. These requests share **1 request/second, burst
3 across all IPs**; excess returns 429. This is an additional server-level
budget alongside the original filtered-homepage limit. Direct visits using
that same browser can be rate-limited while the shared budget is full. Changing
the user agent or request parameters evades this narrow incident heuristic.
Rerun the same installer to activate a staged update; it makes a fresh backup.

## References

- [Cloudflare visitor IP restoration](https://developers.cloudflare.com/support/troubleshooting/restoring-visitor-ips/restoring-original-visitor-ips/)
- [Nginx real IP module](https://nginx.org/en/docs/http/ngx_http_realip_module.html)
- [Cloudflare token permissions](https://developers.cloudflare.com/fundamentals/api/reference/permissions/)
- [Cloudflare IP access rules](https://developers.cloudflare.com/waf/tools/ip-access-rules/)
- [Fail2ban 1.1 jail configuration](https://github.com/fail2ban/fail2ban/blob/1.1.0/config/jail.conf)
