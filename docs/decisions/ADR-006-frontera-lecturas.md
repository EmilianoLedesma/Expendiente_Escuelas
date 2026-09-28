# ADR-006: Frontera de lecturas — Application concentra flujo/permiso/completitud, presentación puede leer para mostrar

**Fecha:** 2026-09-28
**Estado:** Aceptado

## Contexto

ADR-001 dice "Livewire nunca toca Eloquent ni app/Domain directamente", pero
la auditoría del 2026-09-23 (`docs/superpowers/auditoria/AGENT_BRIEF_remediacion-auditoria-2026-09-23.md`,
decisión D5) encontró que el código nunca cumplió esa frase al pie de la
letra: componentes y rutas leen Eloquent/DB directamente para mostrar datos,
y —el problema real— algunas de esas lecturas directas eran decisiones de
flujo/permiso/completitud, no solo de presentación. La compuerta de Paso 2
que se podía saltar (WS-1.2) es la prueba: la decisión de "¿ya se puede
avanzar?" vivía en un método de un componente Livewire, no en Application.

## Decisión

1. **Toda decisión de flujo, permiso, propiedad o completitud vive en
   `app/Application`**, como caso de uso o consulta (query class), nunca en
   un componente Livewire, una ruta, o un View Component. Esto ya se aplicó
   en WS-1/WS-2/WS-2.4 (`EstadoPaso2`, `EstadoPaso3`, `CompuertaPaso3`,
   `PrecondicionIncumplida`) y en el rediseño de UI del 2026-09-25
   (`ResumenTramite`, que reemplazó a `Progreso` y `Paso3ProximosPasos`).
   Esta ADR formaliza la regla que esos cambios ya seguían, no la inventa.

2. **Una lectura puramente de presentación puede quedarse en el componente**
   — mostrar "esto ya lo capturaste" (`InfraestructuraNivel::espaciosCapturados()`,
   vía el modelo Eloquent `InstalacionEspacio`) no es una decisión de
   negocio, es pintar lo que Application ya decidió que es válido mostrar.
   La línea: si la lectura decide qué puede hacer el usuario a continuación,
   va en Application; si solo decide qué se ve en pantalla, puede quedarse.

3. **Ninguna lectura de presentación usa el facade `DB` crudo.** Una tabla
   sin modelo Eloquent (`sanitarios`, `tipos_material_biblioteca`) no tiene
   dónde colgar una regla de propiedad o aplicabilidad si algún día la
   necesita — por eso esas lecturas se mueven a `app/Application` aunque
   sean de presentación pura, no porque violen el punto 1, sino porque el
   facade `DB` en un componente es la señal más barata de detectar
   mecánicamente de que "alguien está a punto de escribir una regla de
   negocio directo en la vista". (El ejemplo de duplicación consolidado en
   la Tarea 3 —`Paso2Documentos`/`ObtenerDocumentoCapturado`— fue una
   duplicación de Eloquent contra Eloquent, no un caso que esta regla del
   facade `DB` hubiera detectado; se corrigió por revisión manual, no
   mecánicamente. Ver `docs/reports/2026-09-28-ws4-frontera-lecturas.md`
   para el historial completo.)

4. **Ninguna lógica de persistencia vive en un closure de `routes/web.php`.**
   Ya aplicado en WS-1.5 (`DescargarDocumentoController`,
   `FormatoSolicitudPdfController`) — sin cambios aquí, solo se deja
   registrado como parte de la misma regla.

5. **Enforcement mecánico vía PHPat** (`tests/Architecture/PresentationBoundaryTest.php`,
   corrido por PHPStan, no PHPUnit — mismo mecanismo que `DomainBoundaryTest.php`
   para ADR-001): `app/Application` no puede depender de `app/Livewire`,
   `app/Http` ni `app/View`; `app/Livewire` y `app/View` no pueden depender
   de `Illuminate\Support\Facades\DB`. Deptrac sigue sin ser necesario — la
   misma razón que ADR-001 §4 ya dio (PHPat cubre esta frontera concreta sin
   añadir una segunda herramienta). **Nota de implementación (2026-09-28):**
   el enforcement de ADR-001 vía `DomainBoundaryTest` nunca ejecutó realmente
   — PHPat descubre reglas solo vía clases registradas como servicio con tag
   `phpat.test` en `phpstan.neon`, y ese registro nunca existió en este
   proyecto hasta esta tarea, que lo agregó para ambas clases de reglas
   (`DomainBoundaryTest` y `PresentationBoundaryTest`) en el mismo cambio.

## Consecuencias

- `InfraestructuraNivel`'s dos lecturas con `DB::table()` se movieron a
  `App\Application\Infraestructura\CatalogosInfraestructura`.
- `Paso2Documentos::documentosCapturados()` y `ObtenerDocumentoCapturado`
  —dos implementaciones independientes de "¿qué documentos ya capturó esta
  escuela para el checklist/descarga de Paso 2.2?"— se consolidaron en
  `App\Application\Documentos\DocumentosCapturados`. Esto no reemplaza
  `DocumentosCompletos::clavesPendientes()`, que decide si Paso 2 está
  completo (`avanzar()`) con una lógica distinta (ambito, fila de catálogo
  faltante) — ver `docs/reports/2026-09-28-ws4-frontera-lecturas.md`.
  El lookup de `tipo_persona`, duplicado en cinco sitios, se consolidó en
  `App\Application\ResponsableLegal\TipoPersonaDeEscuela`.
- La regla NO prohíbe leer modelos Eloquent desde un componente Livewire
  para mostrar datos — solo prohíbe que esa lectura decida flujo/permiso, y
  prohíbe el facade `DB` crudo específicamente. Un futuro `InstalacionEspacio::where(...)`
  puramente de presentación sigue siendo válido sin pasar por esta ADR.
- La ADR-001 original queda con una nota de estado fechada apuntando aquí,
  sin reescribir su texto original.
