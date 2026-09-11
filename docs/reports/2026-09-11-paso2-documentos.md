# Session Report: Paso 2.2 (Documentos + Formato de Solicitud) — full implementation

**Date:** 2026-09-11
**Branch:** `worktree-paso2-documentos`
**Plan:** `docs/superpowers/plans/2026-09-10-paso2-documentos.md` (15 tasks, written 2026-09-10)
**Spec:** `docs/superpowers/specs/2026-09-10-paso2-documentos-design.md`
**Method:** `superpowers:subagent-driven-development` — 15 tasks, each implementer→reviewer, one final whole-branch review + one fix wave.
**Final commit:** `6fed64b`
**Full suite:** 229/229 passing, Pint clean, PHPStan level 5 clean (0 errors, `--memory-limit=512M`).

---

## What was built

Paso 2.2 — capture of the 6 checklist documents (INE; acta de nacimiento / escritura-poder de facultades, mutually exclusive by tipo_persona; escritura del inmueble; Dictamen de Uso de Suelo; Constancia de Seguridad Estructural+DRO; Formato de Solicitud) — inserted between Paso 2.1 (responsable legal) and Paso 2.3 (niveles selección), correcting a gating gap where the wizard previously skipped straight from responsable to niveles (COMPENDIO confirms niveles selection is "la última acción del Paso 2, después de capturar los documentos").

Key pieces (task-by-task, commits in order): `TiposDocumentosSeeder` (7 rows, 6 documents) → `TipoDocumento`/`DocumentoPlantel`/`DocumentoEscuela` models → `ConstanciaSeguridadEstructural`/`AcreditacionOcupacionLegal` extension models → `documentos` filesystem disk + `AlmacenDocumentos` interface/local impl → `DatosDocumento` DTO → `RegistrarDocumento` generic use case (dispatches by clave, writes base + extension tables in one transaction) → `DocumentosCompletos` (single shared completeness query, called by both `Paso2Responsable::mount()` and `Paso2Documentos::mount()`, never duplicated) → `ValidarVigenciaDocumentos` (submission-time-only vigencia check: Dictamen 30-day window, perito-registro-year match) → the documentos gate in `Paso2Responsable::mount()` → `Paso2Documentos` Livewire component (file-only documents, then structured-field documents, then Formato de Solicitud PDF generation via dompdf + reupload, then vigencia enforcement on final submit) → policy-gated document download route → a real-HTTP round-trip regression test per ADR-003's precedent.

No new migrations — all 6 relevant tables (`tipos_documentos`, `documentos_plantel`, `documentos_escuela`, `constancias_seguridad_estructural`, `acreditaciones_ocupacion_legal`) already existed from the original 44-table DDL port, only models/Application-layer code were missing.

---

## Real bugs found and fixed during implementation (not part of the original plan text)

- **Task 7**: `RegistrarDocumento` (Task 6's code) unconditionally wrote to `acreditaciones_ocupacion_legal` on the `escritura_inmueble` clave — violated the DDL's `tipo NOT NULL` constraint whenever `tipoAcreditacion` was null. Fixed with a guard. DDL-confirmed, correctly fixed, re-verified consistent through every later task.
- **Task 9's carried defect, closed by Task 10**: Task 9's placeholder route (`fn () => abort(501)`) took zero parameters, so implicit route-model-binding never resolved `{escuela}`, meaning `can:view,escuela` middleware couldn't authorize correctly (a raw string reached the policy check instead of an `Escuela` model). Task 10 replaced the closure with the real `Paso2Documentos::class` component (typed `mount(Escuela $escuela, ...)`), which Livewire's own implicit-binding path resolves correctly. Independently re-verified twice (Task 10's own reviewer traced it through Livewire's vendor source; the final whole-branch reviewer re-confirmed).
- **Final whole-branch review found a coupled Critical pair the per-task reviews couldn't see**: (C1) `Paso2Documentos::mount()` crashed with a `TypeError` if a solicitante re-entered the documentos screen after all 6 documents were already registered (undefined array-key `$pendientes[0]` assigned to a typed `string $fase`) — reachable via browser back-navigation or by re-mounting after a vigencia block. (C2) `ValidarVigenciaDocumentos` only ran inside `Paso2Documentos::avanzar()`, never in `Paso2Responsable::mount()`, so a solicitante blocked by an expired Dictamen could navigate directly to `/tramite/paso2/{escuela}` and bypass the vigencia rule entirely (`DocumentosCompletos::paraEscuela()` alone is true the instant all 6 rows exist, regardless of date validity). Fixed together in one commit (`6fed64b`): `Paso2Responsable::mount()` now also runs the vigencia check and redirects to documentos on violation; `Paso2Documentos::mount()` falls back to the offending clave (via `array_key_first()` on `ValidarVigenciaDocumentos::ejecutar()`'s now-associative return) instead of indexing blind.
- **Same fix wave, Important**: `Paso2Documentos::guardarDocumentoSimple()` used the client-writable Livewire `$fase` property directly as the document clave — a crafted request could set `fase` to a structured-field clave and submit through the file-only path, silently skipping structured capture (no acreditación row, no `fecha_vigencia` on the Dictamen). Fixed with a `CLAVES_SOLO_ARCHIVO` whitelist guard.

## Contract change worth flagging for future readers

`ValidarVigenciaDocumentos::ejecutar()`'s return type changed from `list<string>` (plain violation-message array) to an associative `array<clave,mensaje>` (keyed by the offending document's clave), specifically so `Paso2Documentos::mount()` can derive which clave to fall back to. Verified safe for the one existing consumer (`avanzar()`'s `implode(' ', $violaciones)` — `implode` ignores keys) and Task 8's own tests (updated from positional to key-based assertions, same underlying message checks, no behavior change to the vigencia logic itself).

---

## Deliberately left undone / tracked gaps

- **`escritura_inmueble` (Escritura del inmueble) blade form ships functionally incomplete** — this is the one item the final reviewer explicitly downgraded from "acceptable to park silently" to "must be tracked, not buried in task-review history." The Blade view (`resources/views/livewire/tramite/paso2-documentos.blade.php`) exposes only 2 of `AcreditacionOcupacionForm`'s 13 fields (`numeroEscritura`, `notarioNombre`) and has no selector for the `tipo` field (stuck at its hardcoded default `escritura_publica`). A real solicitante whose property is under `arrendamiento`/`comodato`/`otro` — or who needs to submit notario/RPP/contrato data — cannot do so through this screen as shipped. **Nothing on disk is broken**: the Form class and `RegistrarDocumento`/`DatosDocumento` already accept and persist all 13 fields correctly if a caller supplies them — this is purely a blade-completion gap, traceable straight to the original plan's own Step 3 snippet (not an implementer shortcut at any task). **Needs a follow-up task** before Paso 2.2 is exercised by a real solicitante whose property isn't a straightforward `escritura_publica`.
- Storage architecture beyond MVP (local disk) — explicitly deferred per the spec, not evaluated further.
- PDF integrity hash — explicitly deferred, no consumer exists yet (Etapa 2's review flow).
- `persona_autorizada_recoger` required-ness — still open from an earlier session (2026-09-10), unrelated to this plan, not touched here.
- Minor, accepted as-is by the final review (not tracked as follow-ups, just noted for completeness): file-write-before-DB-transaction in `RegistrarDocumento` (deterministic path means no orphan accumulation); Task 14's download-route closure inlining business logic (correctness verified, a maintainability nit, not copied to any other route yet); `documentos_plantel`/`documentos_escuela` have no UNIQUE constraint backing `updateOrCreate`'s dedup (cosmetic risk only — completeness still computes correctly under a rare concurrent double-submit); `DocumentosCompletos::clavesPendientes()`'s unguarded array-key access assumes the seeder ran (consistent with the rest of the codebase's existing convention).

## Not touched

`app/Domain/`, migrations, DDL, `docs/progress.md` (not written by this session per the project's "one writer" rule — this report is the source the controller writes `progress.md` from at merge time).

---

## Verification

Every task's implementer ran `php artisan test` + `vendor/bin/pint --test` + `vendor/bin/phpstan analyse --memory-limit=512M`, all clean, at every step (188/188 baseline → 229/229 final). Every task got an independent task-scoped review (spec compliance + code quality), zero Critical findings at task level, a small number of Important findings all resolved by ruling or fix. One final whole-branch review (opus) found the two coupled Critical items above; one fix wave closed them plus the Important finding; one scoped re-review confirmed all four addressed with no new breakage.
