# SalesInbox — inbound email (Zoho gap #11) — scope

**Status:** built. Finishes the Email module's **receive** side (outbound SMTP already worked).
Owner chose **both** transports: a verifiable ingest pipeline **and** an IMAP fetcher on top of it.

## The environment constraint (why it's split this way)
There is **no IMAP anywhere in the dev stack** — no `ext-imap`, and Mailpit (the local mail
container) is **SMTP-only** with no IMAP server to fetch from. So the *transport* (IMAP) cannot be
run for real here; the *ingest/matching/threading* core can, and is verified for real.

## Ingest core (fully verified)
`EmailService::ingestInbound($msg, EmailAccount)` — the one path every received message flows
through, whatever the transport:
- **Idempotent** by `message_id` — a re-fetch returns the existing row, never a duplicate.
- **Sender match** → record: Contact→its Customer, else Customer, else Lead (by email, case-insensitive),
  setting the polymorphic `related`. A converted lead therefore matches as its Customer.
- **Threading:** an inbound with no sender match but an `in_reply_to` inherits the `related` of the
  message it answers (`emails.in_reply_to`, new column).
- Writes an `emails` row (`direction=inbound`, `status=received`) and a **timeline entry** on the
  matched record. Console-safe (keys off `account.company_id`, never `auth()`).

## Transports
- **Inbound ingest endpoint** `POST /emails/inbound` (`feature:email`, `permission:email.send`) —
  provider-agnostic: a mail-provider inbound-parse webhook (Mailgun/SendGrid/Postmark) or a manual
  push sends the parsed shape; the account is resolved by a recipient matching an account address,
  else the default. **This is the verifiable receive path.**
- **IMAP fetcher** — `ImapClient` (webklex/php-imap v6, added cleanly — Carbon-3 compatible, no
  advisory block) + `emails:fetch` command (scheduled `everyTenMinutes`, and a manual
  `POST /email-accounts/{id}/fetch`). Reads each account's IMAP creds from the encrypted
  `email_accounts.config`, pulls messages with UID > `imap_last_uid` (new column), normalises each,
  and calls `ingestInbound`. **Cannot be run against a live server here** — written defensively so
  an unconfigured or unreachable mailbox is a clean, reported no-op, never a fatal. This is the
  "structure ready" transport (cf. M17 BC syncers, chat provider handshake).

## Config
IMAP credentials live in the existing `email_accounts.config` (encrypted). `SmtpMailer` gains a
separate `imapFields()` + `isImapConfigured()` — deliberately **not** folded into the SMTP
`credentialFields()`, so the send-side `isConfigured()` never demands IMAP settings. `imap_password`
is write-only (masked on read).

## Verified
- **curl (ingest core):** inbound from a known customer → matched to that Customer; same
  `message_id` again → same row (dedupe); a reply from an unknown sender with `in_reply_to` →
  inherited the parent's Customer (threading); all appear in the `inbox` folder; timeline written.
- **curl (IMAP transport, structural):** unconfigured account → "IMAP is not configured"; a
  configured-but-invalid host → "IMAP fetch failed: connection failed" (HTTP 200, `ok:false`, **no
  500**), proving the webklex path executes and errors are caught; `imap_password` never returned.
- `emails:fetch` command → clean no-op ("No accounts with IMAP configured").

## Out of scope (MVP)
OAuth (Gmail/Outlook) mailbox connect; attachment download on inbound; auto-reply/rules; sending
from within a thread. The `POST /emails/inbound` shape is provider-agnostic, so a real provider or
IMAP simply feeds the same ingest core.
