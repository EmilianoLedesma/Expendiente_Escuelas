# WS-4 — Frontera de lecturas (2026-09-28)

Rango de commits: `01df2af`..HEAD (tras el fix wave de revisión final).

## 1. Qué se hizo

WS-4 formaliza y hace cumplir mecánicamente ADR-006: las lecturas de
flujo/permiso/completitud viven en `app/Application`, no en presentación
(Livewire/View/rutas), y ninguna lectura de presentación usa el facade `DB`
crudo. Cuatro tareas del plan (`docs/superpowers/plans/2026-09-28-ws4-frontera-lecturas.md`):

- **Tarea 1** — `tests/Architecture/PresentationBoundaryTest.php`: 5 reglas
  PHPat (Application no depende de Livewire/Http/View; Livewire y View no
  usan el facade `DB`). En el proceso se descubrió y corrigió un defecto de
  infraestructura de pruebas mucho más importante — ver sección 2.
- **Tarea 2** — Las dos lecturas `DB::table()` de `InfraestructuraNivel`
  (catálogos de espacios/mobiliario) se movieron a
  `App\Application\Infraestructura\CatalogosInfraestructura`.
- **Tarea 3** — Se consolidaron dos implementaciones independientes de "¿qué
  documentos ya capturó esta escuela?" (`Paso2Documentos::documentosCapturados()`
  y `ObtenerDocumentoCapturado::ejecutar()`) en
  `App\Application\Documentos\DocumentosCapturados`. Se creó
  `App\Application\ResponsableLegal\TipoPersonaDeEscuela` para el lookup de
  `tipo_persona`, usado inicialmente en los dos sitios de esta tarea.
- **Tarea 4** — Se escribió `docs/decisions/ADR-006-frontera-lecturas.md` y
  se corrigió la nota de estado obsoleta de `docs/decisions/ADR-001-frontera-de-capas.md`.

## 2. `DomainBoundaryTest` nunca ejecutó realmente (2026-09-08 → 2026-09-28)

**Hallazgo prominente de esta workstream.** PHPat (0.12.4) descubre las
clases de reglas exclusivamente vía `$container->getServicesByTag('phpat.test')`
(un tag de contenedor Nette DI). Ni el `extension.neon` propio del paquete
PHPat, ni el `phpstan.neon` de este proyecto (verificado completo, 12 líneas,
sin bloque `services:`), registraron nunca `DomainBoundaryTest` — ni ninguna
otra clase — bajo ese tag.

Consecuencia: **el job `static-analysis` de CI reportó "0 errores" en falso
desde que `DomainBoundaryTest` se agregó (2026-09-08) hasta este branch
(2026-09-28)**. No porque ADR-001 (Domain no depende de `Illuminate\*`)
nunca se violara, sino porque el chequeo en sí nunca corrió — PHPat no tenía
ninguna regla registrada para ejecutar.

Verificado con una violación de prueba deliberada (agregada y quitada
después, no forma parte del diff final): una clase escaneada
`App\Domain\ScratchViolation` con `use Illuminate\Support\Str;` y una
llamada a `Str::random()` — violación inequívoca de la regla existente.
Antes del fix: exit code 0 (`0 errores`, falso negativo confirmado, no solo
en el resumen JSON sino en el código de salida real del proceso). Después
del fix (agregando el bloque `services:` a `phpstan.neon` que registra
`DomainBoundaryTest` y `PresentationBoundaryTest` bajo `phpat.test`): exit
code 1, con el detalle completo del error.

Corregido en la Tarea 1 (commit `57fe0e5`, `phpstan.neon`, bloque
`services:`). Confirmado tras el fix: 0 errores reales con las 6 reglas
activas (la pre-existente de Domain + las 5 nuevas de presentación),
incluyendo una re-verificación independiente por el revisor de cada tarea y
por el control final.

Nadie lo detectó antes porque nunca se escribió un control positivo
(deliberadamente en rojo) para la propia regla — la misma disciplina de
TDD que este proyecto exige para el código de negocio, aplicada a las
herramientas de verificación, es exactamente lo que expuso este plan al
pedir un rojo inicial en la Tarea 1.

## 3. Follow-up: `DocumentosCapturados` vs. `DocumentosCompletos::clavesPendientes()`

La revisión final de branch completo encontró que la Tarea 3 no consolidó
"la única fuente" de qué documentos están capturados, como afirmaban
originalmente el docblock de `DocumentosCapturados` y ADR-006. Existen hoy
dos consultas independientes que responden preguntas relacionadas pero no
idénticas:

- `DocumentosCapturados::paraEscuela()` — qué documentos ya se subieron,
  para las pantallas de checklist y descarga de Paso 2.2.
- `DocumentosCompletos::clavesPendientes()` — qué documentos faltan, la que
  realmente decide si Paso 2 está completo (usada por `EstadoPaso2::etapaFaltante()`
  y, en última instancia, por `avanzar()`).

Divergen en dos puntos concretos:

1. **Ámbito**: `clavesPendientes()` consulta las tablas de escuela Y de
   plantel sin filtrar por el ámbito real del documento;
   `DocumentosCapturados` elige la tabla correcta según
   `tipos_documentos.ambito`.
2. **Fila de catálogo faltante**: si un `tipos_documentos.clave` no existe
   en el catálogo (seeder no corrido), `clavesPendientes()` lanza una
   excepción con una pista para correr el seeder; `DocumentosCapturados` la
   omite en silencio.

No es un bug activo hoy — los datos que escribe `RegistrarDocumento`
siempre siguen el ámbito correcto, así que ambas consultas coinciden en la
práctica. Pero es exactamente la clase de deriva que WS-4 existe para
eliminar: dos fuentes que hoy responden lo mismo pero pueden divergir sin
que nada lo note.

**Decisión de este fix wave**: no fusionar las dos consultas aquí — es un
cambio que toca la lógica real de gating (`avanzar()`), necesita su propia
tarea test-first, y es desproporcionado para un fix de exactitud de
documentación. En su lugar se corrigió el docblock de `DocumentosCapturados`
y la sección "Consecuencias" de ADR-006 para dejar de afirmar "única
fuente".

**Recomendación para una tarea futura**: derivar `clavesPendientes()` de
`DocumentosCapturados()` (o viceversa) para que exista una sola consulta de
base, en vez de dos independientes que hoy coinciden por casualidad de los
datos.

## 4. Evidencia — tests y PHPStan por tarea

| Tarea | Commits | Tests | PHPStan |
|---|---|---|---|
| Tarea 1 | `01df2af`..`57fe0e5` | (cubierto por Tarea 2/3) | 0 errores reales, 6 reglas activas (2 esperadas en rojo antes del fix de Tarea 2, confirmadas) |
| Tarea 2 | `57fe0e5`..`d7118a3` | `--filter=InfraestructuraNivel` → 36/36, 90 assertions | 0 errores |
| Tarea 3 | `d7118a3`..`26ce05c` | Suite completa → 534/534, 1429 assertions | 0 errores |
| Tarea 4 | `26ce05c`..`6889514` | (solo documentación, sin cambios de código) | — |
| Revisión final + fix wave | `6889514`..HEAD | Suite completa → 534/534, 1429 assertions (idéntico a Tarea 3 — confirma que el rewiring de `TipoPersonaDeEscuela` no cambió comportamiento) | 0 errores, `pint --test` → 0 violaciones |

Fuente: `.superpowers/sdd/2026-09-28-ws4-frontera-lecturas/progress.md`.

## 5. Qué quedó deliberadamente fuera

- La migración completa de `clavesPendientes()`/`DocumentosCapturados` a una
  sola consulta base (sección 3) — próxima tarea, test-first.
- Consolidar más lecturas display-only en `app/Application` — ADR-006 §2 las
  permite explícitamente quedarse en presentación; no hay obligación de
  moverlas.
- Cualquier cambio a Filament o a las rutas de la API externa (`/api/v1`) —
  no tocadas por este workstream.
- El aumento de conteo de queries de `ObtenerDocumentoCapturado` (~8 vs.
  2-3 antes) — tradeoff aceptado de la consolidación de la Tarea 3, sin
  cambio de código.
- La inconsistencia de estilo inyección-por-constructor vs. `app()` en
  `Paso2Documentos` — patrón pre-existente del codebase, no introducido por
  este branch.
