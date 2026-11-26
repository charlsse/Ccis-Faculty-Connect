# Approved academic roster deployment — November 24, 2026 (Asia/Manila)

The user explicitly approved applying the reviewed migration and creating exactly 40 missing Faculty accounts. Deployment is complete on `ccis_local_20261018_164245` at `127.0.0.1:3307`.

## Final counts

| Item | Verified result |
| --- | ---: |
| Teaching faculty in approved source roster | 41 |
| Existing matching roster account preserved | 1 |
| Newly created Faculty accounts | 40 |
| Roster members represented by user accounts | 41 |
| Pre-existing application accounts preserved | 6 |
| Total application users after import | 46 |
| Active application users / directory entries | 45 |
| Total Faculty-role users, including existing users outside this roster | 43 |
| Additional accounts created by repeating import | 0 |
| External messages sent | 0 |

Michael C. Olivo's account remains user **45** with its original credentials, two-factor settings, role and permissions. All six pre-existing user rows matched their before-deployment fingerprints exactly. The 40 inserted users have IDs **51–90**, the reviewed generated roster login IDs, Faculty role, active directory membership, null email, null email-verification timestamps, mandatory first-login password replacement and uncompleted individual TOTP enrollment. No official faculty IDs or emails were fabricated.

## Migration and integrity

Only `2026_11_24_000000_add_academic_roster_provisioning` was applied, recorded in migration batch **11**. It makes email nullable while retaining uniqueness and adds the reviewed roster-provenance table. The live 24-table schema matched the original reviewed backup before deployment. Reconciliation matched the approved 40 creates and one preserved account.

Recovered MariaDB identity, loopback port, read-write status and forced-recovery zero were verified. All **25** post-migration tables passed health checks. No duplicate faculty login IDs or non-null emails were found. The 40 provenance records reference only newly inserted accounts and the existing authorized Administrator.

Before applying the migration, a fresh protected full backup was restored and tested in a separate schema. Fresh evidence:

`C:\Users\Charles\FacultyConnect-Recovery\recurring-backups\academic-roster-preflight-20261124-135257`

It contains `main-before.sql`, `backup-restoration-verified.json`, `normal-deployment-before.json`, `normal-migration-applied.json` and final `normal-deployment-verified.json`.

The final verification compared all **18** stable pre-existing tables against the fresh baseline. Existing user, audit and migration rows were compared separately from approved new rows. Contributions, memberships, receipts, payments, notifications and other financial/history rows remained unchanged. Existing session/cache/queue tables are volatile and were not used to claim stable-row preservation. No historical obligations were assigned to new users.

APP_KEY and database settings remain unchanged; protected `.env`, database, payment, Xendit, presentation-origin and ngrok configuration file fingerprints matched their pre-deployment values. No tunnels or services were restarted or reconfigured for this deployment.

## Authentication and regression verification

All **40** actual temporary passwords were checked against their stored hashes and exercised through the login controller with process-local, in-memory sessions. Every account redirected to required first-login password replacement. No permanent password was substituted and no TOTP secret, recovery code or verification timestamp was pre-enrolled on these real accounts.

The full regression suite passed **236 tests and 2,839 assertions** after deployment. It covers actual password replacement and TOTP enrollment/challenge using disposable fixtures, standard-account email verification, prohibited public/self-service provisioning, faculty role restrictions, private-payment/notification isolation, report failure rollback, source conflicts and idempotency. Local `/login` returned HTTP **200**. Actual faculty-device enrollment and full authenticated mobile journeys remain for the account holders.

An intermediate repeat-import verification stopped because snapshot tooling requested stringified PDO values and a strict comparison treated integer and string representations of the same user ID as different. A fresh read-only reconciliation confirmed all 41 correct matches. Identifier comparison was corrected without changing any account data; a regression test covers stringified results and restores the test connection's settings. No missing-account insert retry occurred. The final repeat-import transaction created zero accounts and changed neither users nor audit records. Intermediate stop evidence is retained alongside the final successful verification.

## Protected credentials report

Exact path:

`C:\Users\Charles\FacultyConnect-Recovery\recurring-backups\academic-roster-preflight-20261124-134109\new-faculty-credentials.json`

The report contains only the 40 newly created accounts, their generated login identifiers, distinct cryptographically generated temporary passwords, role, status and expiry. No existing user's credentials were read out, reset or exported. No passwords were displayed in chat, console output or this document, and none were emailed.

The parent directory has ACL inheritance disabled. File permissions were checked: only Charles, SYSTEM and local Windows Administrators have Allow rules, with zero unexpected rules. The report is a regular file outside the website and repository. Charles can open it locally in File Explorer under his Windows account, or an authorized Windows administrator can retrieve it. Do not copy it into public storage or a shared link.

Temporary passwords expire **November 26, 2026, between 8:55:46 PM and 8:55:56 PM Asia/Manila**, according to the existing 48-hour policy. Account holders must change their password and enroll their own authenticator before dashboard access. Existing accounts retain their original authentication requirements.
