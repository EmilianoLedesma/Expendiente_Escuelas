# PENDIENTE — Namespace de componentes Livewire (`App\Http\Livewire` vs. `App\Livewire`)

**Estado: abierto.** No adjudicado por el agente. Requiere decisión del arquitecto.

## Por qué existe este documento

`docs/reports/2026-09-07-fix-sesion-419.md` diagnosticó la causa raíz del 419
"Page expired" que bloquea toda interacción Livewire de Paso 1, pero ese
reporte fue explícitamente diagnóstico — el propio task lo dejó fuera de
alcance tocar código de Livewire, Application o Domain. Este documento eleva
esa causa a decisión arquitectónica formal, con su propio archivo, en línea
con las otras dos decisiones pendientes de este directorio.

## El síntoma

419 "Page expired" en cada interacción Livewire de Paso 1 — idle, edición de
un campo (`wire:model.blur`), selección de un plantel existente. Ocurre en
todo entorno, sin relación con sesión, cookie o token CSRF.

## Causa real

Los componentes Livewire de este proyecto viven bajo `App\Http\Livewire\...`
(convención deliberada, documentada en la sección de arquitectura de
CLAUDE.md), pero Livewire 3 autodescubre componentes bajo `App\Livewire\...`.
El snapshot que el navegador reenvía en cada petición `/livewire/update` lleva
`memo.name` derivado automáticamente del namespace completo del componente
(`app.http.livewire.tramite.paso1-preregistro`, con el segmento literal
`http` incluido). `Livewire\Features\SupportReleaseTokens\ReleaseToken::verify()`
busca ese nombre vía `ComponentRegistry::getClass()` antes de comparar
tokens de release; la búsqueda falla con `ComponentNotFoundException`, y
Livewire relanza eso como `LivewireReleaseTokenMismatchException` — el mismo
diálogo "This page has expired" que produce un 419 real de CSRF, aunque la
causa no tenga nada que ver con sesión ni CSRF. Nada en el proyecto llama a
`Livewire::component(...)` para registrar el alias manualmente. Resultado:
toda petición AJAX de Livewire para un componente bajo esta convención falla
incondicionalmente, en cualquier entorno, independientemente del estado de
sesión.

## Las dos opciones

1. **Registrar explícitamente cada componente** con
   `Livewire::component('tramite.paso1-preregistro', Paso1Preregistro::class)`
   (u equivalente) en un service provider — conserva la convención
   `App\Http\Livewire\` ya documentada en CLAUDE.md, pero exige registrar cada
   componente nuevo a mano y es fácil de olvidar en un paso futuro.
2. **Migrar el directorio a `app/Livewire/`** para alinear con la
   autodetección nativa de Livewire 3 — elimina el registro manual, pero
   contradice la convención actual documentada en CLAUDE.md (que también
   tendría que actualizarse) y requiere mover cada componente ya escrito.

## Consecuencia de no resolverlo

Bloquea toda prueba de navegador de Paso 1 ahora mismo, y se repetirá
exactamente igual en cada uno de los pasos restantes del wizard (Paso 2,
Paso 3.1–3.6) mientras se construyan bajo la misma convención de directorio
— no es un problema aislado a Paso 1, es estructural.

## Estado de este documento

Sin fix aplicado. No se ha tocado código de Livewire, Application ni Domain
como parte de este documento. Se marca como pendiente de decisión del
arquitecto entre las dos opciones anteriores — este documento no adjudica
cuál tomar.
