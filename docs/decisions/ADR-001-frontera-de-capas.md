# ADR-001: Frontera de capas — Application layer en proceso, no HTTP interno

**Fecha:** 2026-09-07
**Estado:** Aceptado

## Contexto

El diseño original del barrier API (`docs/superpowers/specs/2026-09-04-api-layer-design.md`,
decisión D2) exigía que el wizard Livewire llamara a su propia API vía un
round-trip HTTP real (`Http::` desde una clase wrapper), para garantizar que
la API fuera el único punto de entrada al Domain.

Una revisión de arquitectura (Opus, 2026-09-04, ver `docs/progress.md`
Session Log) encontró que ese diseño no ejecuta en absoluto:

- La clase wrapper es PHP del lado servidor emitiendo `Http::`. Sanctum en
  modo cookie/SPA depende de que el navegador tenga el cookie jar de la
  sesión — una petición servidor-a-servidor no lo tiene.
- `php artisan serve` es de un solo proceso: una llamada de la app a sí
  misma por HTTP se bloquea (deadlock).
- Más allá de que no funciona: rompe el límite transaccional de pasos que
  escriben varias tablas a la vez (Paso 3.1 escribe cuatro tablas — no
  puede envolverse en una sola transacción si el paso intermedio es una
  petición HTTP separada), duplica la E/S de subida de archivos (una vez al
  API, otra al storage real), y rompe los stack traces en la frontera HTTP.

## Decisión

1. **Se rechaza el diseño D2 de round-trip HTTP real.** Ninguna llamada
   interna del wizard a sí mismo pasa por HTTP.

2. **La frontera de capas es una capa `app/Application/` en proceso.** Tanto
   los componentes Livewire como los controladores de la API llaman a los
   mismos casos de uso ahí definidos. Livewire nunca toca Eloquent ni
   `app/Domain` directamente — la garantía de D2 (nadie puede colarse
   importando un servicio de Domain desde un componente) se mantiene, solo
   que la enforcement es una capa de código, no una llamada de red.

3. **La API HTTP se sigue construyendo** — `/api/v1` versionada, OpenAPI
   generado con Scramble, sus propios contract tests — como un adaptador
   delgado sobre `Application/`, pensado para consumidores externos de la
   Etapa 3. Es real e independientemente probable; simplemente no está en
   el camino del wizard.

4. **La enforcement de la frontera se difiere hasta después de la
   presentación del MVP**, vía Deptrac en CI, no vía indirección en tiempo
   de ejecución. Razón: un análisis estático sobre stubs vacíos no prueba
   nada — la regla vale la pena una vez que exista lógica real que pueda
   violarla.

5. **El sistema es server-rendered, no un SPA.** Una ruta Laravel por paso
   del wizard, `redirect()` entre pasos, reactividad Livewire solo dentro de
   un paso, Alpine para interacciones puramente visuales. El estado del
   wizard vive en Postgres (ver `escuela_nivel_pasos`,
   `docs/reports/2026-09-07-wizard-progreso.md`) porque no hay un store del
   lado del cliente.

## Consecuencias

- El diseño D2 y la mitad "async job" de D3 en
  `docs/superpowers/specs/2026-09-04-api-layer-design.md` quedan marcados
  como superados en ese mismo archivo, sin borrar el razonamiento original
  (queda como registro de por qué se intentó y por qué no funcionó).
- Ningún código de `app/Application/` existe todavía — esta ADR fija la
  regla, no la implementa. Queda fuera de alcance de la tarea que originó
  esta ADR (`docs/reports/2026-09-07-wizard-progreso.md`).
- Deptrac no está configurado todavía; se añade cuando haya lógica real en
  `app/Domain`, `app/Application` y `app/Http/Livewire` que pueda violar la
  frontera.
- El Motor de Validación síncrono (no job en cola) es la dirección actual;
  el diseño de origen de magnitud y composición de reglas sigue abierto en
  `docs/decisions/PENDIENTE-origen-de-magnitud.md`, sin relación con esta
  ADR salvo que ambos alimentan la misma capa `Application/` futura.
