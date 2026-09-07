# Design foundation + Paso 1 Preregistro — 2026-09-07

Branch: `feat/paso1-preregistro` (off `master`, not merged). First vertical
slice: design tokens, wizard shell, Application-layer pattern, Paso 1 end to
end.

## Part 1 — Design foundation

### Token mapping (DESIGN.md → Tailwind)

All names match DESIGN.md 1:1 — `tailwind.config.js`, `theme.extend`:

| DESIGN.md token | Tailwind key | Utility example |
|---|---|---|
| `{colors.primary}` etc. (26 non-nivel colors) | `colors.<same-name>` | `bg-primary`, `text-ink`, `border-hairline` |
| `{colors.nivel-inicial-esc}` … `{colors.nivel-superior}` (7 of 8) | `colors.nivel-*` | `text-nivel-primaria` |
| `{spacing.xxs}`…`{spacing.section}` | `spacing.<same-name>` | `p-lg`, `gap-sm`, `mb-xxs` |
| `{rounded.xs}`…`{rounded.pill}` | `borderRadius.<same-name>` | `rounded-lg`, `rounded-pill` |
| `{typography.display-xl}`…`{typography.nav-link}` (14 entries) | `fontSize.<same-name>` (`[size, {lineHeight, letterSpacing}]`) | `text-display-lg` |
| Cal Sans / Hanken Grotesk / JetBrains Mono | `fontFamily.display` / `fontFamily.sans` / `fontFamily.mono` | `font-display`, `font-sans`, `font-mono` |

Font weight is applied via Tailwind's own `font-{weight}` utility alongside
the `text-{typography-token}` class (e.g. `class="font-display text-display-lg
font-semibold"`) rather than baked into the fontSize entry — Tailwind's
fontSize tuple doesn't carry weight, and DESIGN.md's weight rule (600 for
every display size) is simple enough that a companion utility is clearer
than a custom plugin. **Decision, not spelled out in DESIGN.md.**

`sans` (Tailwind's default, used implicitly by any unprefixed text) was
changed from Breeze's stock Figtree to Hanken Grotesk — DESIGN.md's stated
body/UI face for the whole system. This affects the existing Breeze
auth/profile screens too (login, register, profile), which weren't otherwise
touched by this task. **Decision, not spelled out in DESIGN.md** — flagging
in case the design owner wants those screens kept on Figtree until they're
formally restyled.

### Fonts — self-hosted, license outcome

All three fonts vendored from npm packages (fetched via the npm registry,
which is reachable in this sandbox; a real air-gapped RHEL box would need
these files copied in some other way at deploy time — not this task's
concern):

| Font | Source package | License | Vendored to |
|---|---|---|---|
| Cal Sans | `cal-sans@1.0.1` (same package DESIGN.md's CDN URL points at) | OFL ("SEE LICENSE IN OFL.TXT" in `package.json`) | `public/fonts/cal-sans/` |
| Hanken Grotesk | `@fontsource/hanken-grotesk@5.3.0` | OFL-1.1 | `public/fonts/hanken-grotesk/` |
| JetBrains Mono | `@fontsource/jetbrains-mono@5.3.0` | OFL-1.1 | `public/fonts/jetbrains-mono/` |

SIL OFL is the standard license written specifically to permit bundling and
self-hosting (with a reserved-name restriction on renamed derivatives, not
relevant here) — no license blocker. Only Latin + Latin-ext subsets were
vendored (Spanish-only system); only the weights DESIGN.md actually uses
(Hanken Grotesk 400/500/600, JetBrains Mono 400, Cal Sans' single 600).
`resources/css/fonts.css` declares the `@font-face` rules with local
`url('/fonts/...')` paths, imported into `app.css` before the Tailwind
directives — no `cdn.jsdelivr.net` reference anywhere in the app. The npm
font packages themselves were removed after extracting the files (`npm
uninstall`) — they were only needed to obtain static assets, not as a
runtime dependency.

### Nivel-palette gap (not silently resolved)

DESIGN.md's 8-entry `{colors.nivel-*}` palette vs. this project's 7
`niveles_educativos`:

- **`nivel-cam`** — excluded. CAM (Centros de Atención Múltiple) is not one
  of this system's niveles; no CAM rows exist or are planned in
  `niveles_educativos`.
- **`nivel-inicial-esc` / `nivel-inicial-no-esc`** — both kept, unmapped to a
  single `nivel-inicial`. The project's `niveles_educativos` catalog has one
  `inicial` row, but `escuela_niveles.modalidad` (PRD §5 Paso 3.4) already
  distinguishes escolarizada/no escolarizada/mixta downstream — these two
  tokens map cleanly to that finer distinction, not to the nivel catalog
  itself. No code in this task consumes them; they're available for
  whichever future screen renders Inicial data broken out by modalidad.
- **Posgrado — no token, left unmapped, not resolved.** COMPENDIO confirms
  Posgrado as a real nivel (`niveles_educativos` seed row, `orden = 7`), and
  DESIGN.md has no corresponding color. Per the task's explicit instruction,
  **`nivel-cam` was NOT applied as a proxy** — using the special-education
  color for Posgrado would misrepresent it. `tailwind.config.js` has no
  `nivel-posgrado` key at all; any future UI needing to color-code Posgrado
  will fail loudly (undefined Tailwind class) rather than silently rendering
  the wrong color. **SEDEQ's design owner needs to define
  `{colors.nivel-posgrado}`** before any chart/table/chip renders Posgrado
  data by level color.

### Components built (Part 1 scope only)

`resources/views/components/ui/`: `text-input.blade.php` (label, required
marker, focused/error/disabled states, hint/error text),
`button-primary.blade.php`, `button-secondary.blade.php`,
`alert.blade.php` (variant prop: info/success/warning/error). No hero bands,
stat cards, charts, data tables, footer, or nav-pill-group — none of those
apply to a transactional form step.

## Part 2 — Wizard shell layout

`resources/views/layouts/tramite.blade.php` — `<x-tramite.top-nav>` (56px,
`bg-canvas`, hairline bottom border, Cal Sans "SEDEQ" brand, sticky),
`<x-tramite.progreso>` (progress indicator), then `{{ $slot }}` for step
content. Registered in `AppServiceProvider::boot()` as an anonymous
component namespace (`Blade::anonymousComponentPath(resource_path('views/layouts'),
'layouts')`) so the same physical file works both as Livewire's full-page
`#[Layout('layouts.tramite')]` (which needs a plain view path) and as
`<x-layouts::tramite>` for ordinary Blade routes like the Paso 2 placeholder
— **note the double colon**, `Blade::anonymousComponentPath`'s prefix form
uses `x-layouts::tramite`, not dot notation. This was a real bug caught only
by hitting the routes over HTTP (see Verification) — the Livewire component
test suite alone didn't catch it, because `Livewire::test()` resolves layouts
differently than a real full-page HTTP request does.

`<x-tramite.progreso :escuela-nivel-id="...">` queries `pasos_captura`
(ordered by `orden`) and, if an `escuelaNivelId` is given, `escuela_nivel_pasos`
for that expediente's per-step `estado`, rendering a dot + label per step —
no step name is hardcoded. Paso 1 passes no `escuelaNivelId` (there's no
`escuela_nivel` yet), so every step renders as pending — the "no expediente
yet" state falls out of the same code path a real expediente will use later,
not a special case.

## Part 3 — Application-layer pattern (copy this for the next 11 steps)

- **DTO location**: `app/Application/<Feature>/DTO/`. `DatosPreregistro` is
  the input (`bifurcacion`, plus the plantel fields, all `?string`/`?int`,
  no validation inside the DTO itself — it's a plain data carrier).
  `ResultadoPreregistro` is the output. Both `final readonly class`, plain
  constructor-promoted properties, no framework types anywhere in their
  signatures — a future API controller builds the same DTO from JSON body
  fields with zero Livewire dependency.
- **Transaction boundary**: opens inside the use case's public method
  (`IniciarTramiteNuevo::ejecutar()`), wrapping every write in
  `DB::transaction(fn () => ...)`. The use case validates its own DTO first
  (throws `InvalidArgumentException` for a missing/invalid field) — that
  validation happens *before* the transaction opens, so a bad DTO never
  touches the DB at all.
- **What the use case may touch**: Eloquent models directly (`Plantel`,
  `Escuela`) — no repository interfaces, per ADR-001's pragmatic boundary.
  Business rules (nuevo vs. existente branching, required-field validation)
  live here, not in the Livewire component or in the Eloquent models
  themselves.
- **What the Livewire component may touch**: its own public properties
  (`wire:model.blur`-bound), the use case class (type-hinted as a method
  parameter — Livewire resolves it from the container, which is also what
  makes `$this->mock(IniciarTramiteNuevo::class, ...)` work in tests), and
  view data. It builds the DTO, calls `ejecutar()`, and either redirects
  (`$this->redirectRoute(...)`) or surfaces an error
  (`$this->addError(...)`). It never imports `Illuminate\Support\Facades\DB`
  or an Eloquent model.
- **Read-only listings are the one gray area**: the "existing plantel"
  dropdown needed a data source, and the task's rule ("no Eloquent, no DB::
  ... in the component") left no path for it. Added
  `ListarPlantelesDisponibles` — a second, tiny Application-layer class,
  read-only, same location/style as the use case. **Decision, not named in
  the task** — flagging in case a different pattern (e.g. a Blade view
  composer, matching how `<x-tramite.progreso>` queries directly) is
  preferred for read-only listings going forward. The progress-indicator
  Blade component (Part 2) *does* query `DB::table(...)` directly, on the
  reasoning that a non-Livewire, non-business-logic display partial isn't
  covered by the Livewire-component-specific rule — worth the design owner
  confirming this split is the intended one before the next 11 steps copy it.
- The Eloquent model class name didn't match its table by Laravel's default
  pluralization (`Plantel` → `plantels`, not `planteles`) — `Plantel` needed
  an explicit `protected $table = 'planteles';`. Caught by the first test
  run, not a design decision, just a note for whoever adds the next model.
- `IniciarTramiteNuevo` is deliberately not `final` — Mockery can't partial-mock
  a final class, and the task's own Part 4 instruction requires mocking it
  in the Livewire test. `DatosPreregistro`/`ResultadoPreregistro` stay
  `final readonly` since nothing needs to mock a plain data carrier.

## Decisions not covered by DESIGN.md or the PRD

1. **Which plantel fields Paso 1 actually captures.** PRD/COMPENDIO say
   "datos básicos de preregistro" without enumerating fields. Captured:
   calle, número exterior, número interior, colonia, localidad, municipio,
   código postal, teléfono, correo electrónico — the address/contact half of
   `planteles`. Excluded (left for Paso 3 Inmueble): metros totales/construidos,
   the four colindancias, latitud/longitud, área cívica, asta de bandera —
   none of those read as "basic preregistro" and PRD explicitly scopes
   Inmueble detail to Paso 3.1.
2. **Field labels** are plain Spanish ("Calle y número", "Código postal",
   etc.) — no source specifies exact wording.
3. **Required-field marking**: a red `*` after the label text (`text-error`
   token), inline with DESIGN.md's `{colors.error}` semantic but not a
   documented pattern.
4. **Multi-field layout**: a 2-column grid (`sm:grid-cols-2`) for the "nuevo"
   fields, with `calle` spanning both columns since it's the longest/most
   important field. The "existente" branch is a single full-width `<select>`.
5. **Error summary placement**: a single `alert` (variant `error`) above the
   form for a *use-case-level* rejection (an `InvalidArgumentException` from
   `IniciarTramiteNuevo` — should not normally happen since the Livewire
   component's own validation rules mirror the use case's checks, but it's
   the safety net for the "existente" plantelId-doesn't-exist case).
   Per-field errors render inline under each `x-ui.text-input` /
   the `<select>`, not duplicated in the summary.
6. **`wire:model.live` on the bifurcación radios**, not `.blur` — the one
   deliberate exception to the task's `.blur`-everywhere rule. A radio
   toggle that must instantly swap the visible field set (nuevo fields vs.
   existente selector) doesn't have a meaningful blur event to defer to;
   `.blur` would leave the wrong section on screen until an unrelated field
   loses focus. All text/select inputs use `.blur` as instructed.

## Follow-up: closed the Blade-layer leak in the progress widget

`<x-tramite.progreso>` originally ran two `DB::table()` queries inside the
`.blade.php`'s `@php` block — a template fetching its own data, untestable
without a database mount, and the project's only Blade-layer boundary
violation (flagged by the user as Deptrac's future first hit). Converted to
a class-based component via `php artisan make:component Tramite/Progreso`:
`app/View/Components/Tramite/Progreso.php` now does both queries in its
constructor and exposes one `public readonly Collection $pasos` (each item
already carrying its resolved `estado`, no per-item lookup left for the
template). `resources/views/components/tramite/progreso.blade.php` is now
pure markup — a `@foreach` and Blade's `@class` directive for conditional
classes, zero `@php` blocks, zero data access. `<x-tramite.progreso
:escuela-nivel-id="...">` usage in `layouts/tramite.blade.php` is unchanged
— class-based and anonymous components share the same `<x-tramite.progreso>`
tag syntax, so no call site needed editing.

Added `tests/Feature/View/Components/Tramite/ProgresoTest.php` (2 tests, via
Laravel's `$this->blade(...)` test helper): the six catalog steps render in
`orden`, and a step marked `completado` renders with a distinct
`data-estado` attribute and `bg-success` dot vs. the other five `pendiente`
steps. `data-estado="{estado}"` was added to each `<li>` specifically to
make this assertable without parsing Tailwind classes as the source of
truth.

## Verification

```
php artisan test
{"tool":"phpunit","result":"passed","tests":126,"passed":126,"assertions":239,"duration_ms":23030}
```
126 = 116 pre-existing (from the wizard-progreso task) + 5
(`IniciarTramiteNuevoTest`) + 3 (`Paso1PreregistroTest`) + 2 (`ProgresoTest`,
added in the Blade-layer-leak follow-up above). Both HTTP routes re-checked
after the refactor — `GET /tramite/preregistro` and `GET /tramite/paso2/{id}`
both still 200, and Paso 1's progress indicator still renders all six steps
as `pendiente` (verified via `data-estado="pendiente"` count = 6 in the
response body).

Manual HTTP verification (`php artisan serve`, no schema/data changes so no
CLAUDE.md data-safety gate applied): `GET /tramite/preregistro` → 200, form
renders with both bifurcación options and all "nuevo" fields;
`GET /tramite/paso2/{id}` → 200. All three vendored font files confirmed
served locally (200) from `/fonts/...`, and the compiled CSS's `@font-face`
`src` URLs point only at those local paths — no `cdn.jsdelivr.net` reference
anywhere in the built output. **Chrome/chrome-devtools-mcp is not available
in this sandbox** (no Chrome binary found), so this was curl/HTML-source
verification, not a real rendered-pixels check — visual QA (spacing,
Cal Sans actually rendering vs. its Hanken Grotesk fallback, focus rings,
responsive breakpoints) is deferred to manual review in an environment with
a browser.

Two real bugs were caught only by this HTTP check, not by the test suite:
`Plantel`'s missing `$table` (caught immediately, before any HTTP check, by
the first `php artisan test` run) and the layout-resolution mismatch between
Livewire's full-page `#[Layout]` and `<x-layouts.tramite>`'s component
resolution (caught only by hitting both routes directly — the Livewire
component test uses `Livewire::test()`, which doesn't exercise the same
`SupportPageComponents` code path a real routed request does).

## Out of scope, confirmed not touched

Paso 2 itself (only a placeholder route/view), Paso 3, any other wizard
step, Filament resources, the Motor de Validación, the HTTP API layer,
Sanctum, Deptrac, PDF generation, file uploads.
