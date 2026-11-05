# LUMIFLOWON: Beta-first release workflow

1. Develop on a release branch. Never put Production credentials, database snapshots or financial records in Git.
2. Run the repository suite only against the guarded disposable PostgreSQL database `127.0.0.1:5433/leobot_financial_disposable`. Production, Beta and Demo are never test databases. Run Pint, the frontend build and dependency audits.
3. Push the branch. The release-branch GitHub workflow must pass; keep Render auto-deploy disabled.
4. Deploy the reviewed commit to `lumiflowon-beta` first. Its database resource is `dpg-dbfg8n7avr4c73e23vog`, distinct from Production. Its Docker command is `bash /var/www/html/scripts/beta-entrypoint.sh`. The command refuses the wrong Render service, the wrong database resource, live financial gates or a real-email mailer before additive migrations. Never upgrade the free service or copy Production secrets as a workaround for unavailable one-off jobs.
5. Beta must use `LUMIFLOWON_RELEASE_STAGE=beta`, `MAIL_MAILER=log`, and false Production approval, staking, profit, deposits and withdrawal gates. It displays a clear no-real-funds banner. Verify login, admin/member roles, desktop/mobile pages and protected operations. Preserve existing Beta records; reconcile legacy principal before any incompatible migration.
6. Promote the identical tested commit to `lumiflowon-live`. Production uses the normal Docker command: no automatic migration, reset, seeding or financial activation on startup. Temporarily keep affected write gates closed while applying a reviewed additive migration with verified Production identity. Check `/up`, deployment SHA, migrations, TLS and the read-only accounting report before reopening the affected control.
7. Preserve the administrator's withdrawal fee and other business settings. A verified profit statement requires an independent checker: a different real administrator, or an existing member with a separately audited review-only permission. A submitter or beneficiary cannot approve their own statement. Member reviewers gain no administrator privileges or staking eligibility. Their approval is immutable and bound to the displayed evidence. The admin may subsequently reserve an independently approved statement using the separate reservation action; the self-approval endpoint always rejects the submitter. New statements reserve 67% Self / 30% Generation / 3% Magic; reserves are not member payouts. Existing payout gates, eligibility, funding and independent source evidence remain required. Never turn a certification failure into a pass by bypassing a guard.
8. Before advertising a fully verified financial public release, demonstrate a backup restoration into a separate recovery database, independently verify chain reserves and operating custody, and record an authorized real-money end-to-end receipt/payout with reconciliation. Successful automated tests are not evidence of treasury solvency or email Inbox delivery.

Demo service and all Demo metadata/databases are excluded from this workflow.


## Limited independent reviewer workflow

- In Admin → Trading-Profit Statements, grant/revoke review-only permission for an exact existing Production username. No account is created, no role is promoted, and no financial activation status changes.
- The reviewer signs in to their own account, completes the ordinary Security PIN access step if required, and opens Member Dashboard → Independent Profit Review (`/reviewer/trading-profit`). The PIN setup is not proof of transaction authorization.
- The reviewer independently checks the actual external realized-profit statement and received funds, then approves the exact displayed snapshot. A statement that benefits that reviewer is excluded. No wallet/ledger payout occurs at this step.
- The owner/admin uses **Reserve previously approved profit** only after recorded independent approval and all existing funding gates permit it. The snapshot, current reviewer permission, account state and 67/30/3 allocation must still match. Separate settlement controls remain required.
- Revocation blocks new reservations, while earlier financial approvals and audit history remain intact. Repeated approval/reservation requests are idempotent; revised or stale evidence fails closed.
- An unavailable/missing Production reviewer, inaccessible backup restoration, unfunded treasury or absent real transaction evidence must remain an explicit certification blocker. Never invent a person, source profit, on-chain transfer, backup recovery success or a full release certificate.


## Public host isolation

The public apex `lumiflowon.com` is still bound to Beta at the infrastructure layer. Beta now redirects GET/HEAD requests on that exact host to the Production www homepage/login, without copying Beta referral IDs, reset tokens or query strings. Stale form submissions on that host return409 before session or financial processing, rather than replaying a POST into another environment. The dedicated `https://lumiflowon-beta.onrender.com` remains the sandbox URL. No database or domain binding is moved by this middleware.
