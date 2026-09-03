# Documents library (Zoho gap #9) — scope

**Status:** scoping, decisions settled. Normal module build. Module name `documents`.

**Decisions locked (product owner, 2026-08-31):**
- **Replace, no version history** — one file per document; re-upload overwrites. A
  `document_versions` table is additive later without reworking this.
- **Flat folders** — single-level. A `parent_id` for nesting is additive later.
- **Staff only** — internal library (sales collateral, contracts, templates). No customer-portal
  exposure. Portal sharing is additive later (would need a visibility/share model + portal path).
- **Professional+** plan tier (`feature:documents`).

## Deliberately NOT the `attachments` pattern (this is the security point)
Per-record `attachments` store on the **public** disk and expose a URL (`Attachment::getUrlAttribute`)
— the same world-readable-file class the 2026-08-31 audit flagged for report CSVs. Documents
**must not** repeat that:
- Files go on the **private `local` disk** (`storage/app/private`), never `public`.
- The `Document` model has **no `url` accessor** — a private path has no meaningful public URL, and
  adding one invites a leak. The **only** access path is an authenticated streaming endpoint.

## Path & download security
- Store with a **generated** name (`$file->store(...)` hashes it) — never `storeAs` with the
  client's filename (path-traversal / overwrite footgun). The original name lives only in the
  `name` column.
- Storage path includes `company_id`: `documents/{company_id}/{Y/m}/<hash>.<ext>` — so a
  filesystem-level slip is still tenant-separable. **But the path is never the authorization gate;
  the DB row's `company_id` is.**
- `GET /documents/{id}/download` resolves the row through the **company-scoped model first**, then
  streams by its stored `path`. It never accepts a path/filename from the request.
- IDOR test (as a **non-platform** user, since `admin@krama.local` bypasses the scope): fetching
  another tenant's document id → **404**, not a file.

## Delete & replace semantics (stated, per the non-transactional-Storage gotcha)
- **No `SoftDeletes`** on `Document`. Delete = remove the DB row (committed) **then** best-effort
  `Storage::delete` the file. Order matters: row first, so a failed file delete leaves a harmless
  orphan, never a row pointing at a missing file.
- **Replace:** store the new file → update the row (path/mime/size) in a transaction → **after
  commit**, best-effort delete the old file. The new file is safely referenced before the old is
  removed.
- **Folder delete:** documents' `folder_id` is set null (FK `nullOnDelete`) — they become
  "unfiled", never orphaned.
- `docker compose exec` runs as root — the first upload **through the app** is the real test that
  PHP-FPM (www-data) can create `storage/app/private/documents/...`; don't pre-create it from a shell.

## Data model (next migration after 000035)
- **document_folders** — company_id, name, created_by, timestamps.
- **documents** — company_id, folder_id (nullable, nullOnDelete), name, description (nullable),
  disk (default `local`), path, mime, size, uploaded_by (nullOnDelete), timestamps. **No soft
  deletes.** index (company_id, folder_id).

## Endpoints (`feature:documents`)
- `GET /documents` (filters `q`, `folder_id`), `GET /documents/meta` (folders) — `documents.view`
- `POST /documents` (multipart: `file` + `name?` + `folder_id?` + `description?`) — `documents.create`
- `GET /documents/{id}` (metadata) — `documents.view`
- `GET /documents/{id}/download` (stream from private disk) — `documents.view`
- `PUT /documents/{id}` (name/folder/description) — `documents.update`
- `POST /documents/{id}/file` (replace file) — `documents.update`
- `DELETE /documents/{id}` — `documents.delete`
- `POST /documents/folders`, `PUT/DELETE /documents/folders/{id}` — create `documents.create`,
  rename `documents.update`, delete `documents.delete`

## RBAC (three TenantProvisioner places)
- `MODULES`: `documents => [view, create, update, delete]`.
- `roleDefinitions`: Sales Manager + HR full; Sales Staff view/create/update; Accountant,
  Purchase Manager, Customer Service view. Owner/CEO full via existing rules.
- `planDefinitions`: add `documents` to `professionalModules`.

## Upload limits
15 MB ceiling (matches leads/deals/chat: `MAX_UPLOAD_KB = 15360`); validated `mimetypes`
whitelist (office docs, pdf, images, text/csv) — the value stored in `mime` is the server-guessed
`getMimeType()`, never the spoofable client type.
