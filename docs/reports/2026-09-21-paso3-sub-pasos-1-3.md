# Sesión 2026-09-21 — Paso 3 sub-pasos 1-3 (Inmueble, Infraestructura, Mobiliario)

Rama: `worktree-paso3-sub-pasos-1-3` (worktree en `sedeq-worktrees/paso3-sub-pasos-1-3`). Plan de 11 tareas, ejecutado secuencialmente implementador → revisor. Esta es la Tarea 11: verificación final y reporte, sin código nuevo.

## Alcance entregado

Cuatro componentes Livewire bajo `app/Livewire/Tramite/Paso3/`, cada uno con su ruta:

| Componente | Ruta | Nombre de ruta |
| --- | --- | --- |
| `DatosInmueble` | `GET /tramite/paso3/{escuelaNivel}` | `tramite.paso3-inmueble` |
| `InfraestructuraNivel` | `GET /tramite/paso3/{escuelaNivel}/infraestructura` | `tramite.paso3-infraestructura` |
| `MobiliarioNivel` | `GET /tramite/paso3/{escuelaNivel}/mobiliario` | `tramite.paso3-mobiliario` |
| `Paso3ProximosPasos` | `GET /tramite/paso3/{escuelaNivel}/proximos-pasos` | `tramite.paso3-proximos-pasos` |

Cada uno protegido con `can:view,escuelaNivel` reutilizando `EscuelaNivelPolicy` existente (sin política nueva), con prueba de 403 propia.

Casos de uso en `app/Application/`: `RegistrarDatosInmueble`, `RegistrarInfraestructuraNivel`, `RegistrarMobiliarioNivel`, `MarcarPasoCompletado`, más los guards de idempotencia `DatosInmuebleYaCapturados` e `InfraestructuraYaCapturada`.

## Evidencia — suite automatizada

Ejecutado desde `app-laravel/` en estado limpio (`php artisan config:clear` primero):

```
php artisan test       → 298 tests, 298 passed, 662 assertions
vendor/bin/pint --test → passed (sin archivos con estilo incorrecto)
vendor/bin/phpstan analyse --memory-limit=512M → 0 errores (nivel 5 + regla PHPat de ADR-001)
```

Los tres coinciden exactamente con lo esperado en el plan (298/298, Pint limpio, PHPStan 0). No hubo que corregir nada para llegar a este estado.

**Paso 2 del brief (prueba manual contra PostgreSQL de desarrollo + navegador) NO se ejecutó.** Requiere permiso explícito del dueño del proyecto para escribir en la base de datos viva (regla dura de CLAUDE.md), permiso que se está solicitando por separado y que este agente no tenía al momento de correr la verificación. No se corrió `php artisan db:seed`, `php artisan migrate` ni se abrió navegador. La evidencia primaria de esta sesión es la suite automatizada (sqlite en memoria vía `phpunit.xml`), que no depende de PostgreSQL.

## Defecto vivo cerrado: widget de progreso

Ningún componente Livewire existente le pasaba `$escuelaNivelId` a `layouts/tramite.blade.php`, así que el widget de progreso mostraba los seis sub-pasos como "pendiente" incondicionalmente, sin importar el estado real en `escuela_nivel_pasos`. Esto llevaba en producción desde antes de esta sesión.

El spec de diseño proponía resolverlo con una propiedad pública `public ?int $escuelaNivelId` en cada componente. **No funciona en Livewire 3.8**: el layout no se renderiza leyendo propiedades públicas del componente montado, sino a partir de `PageComponentConfig->params`, que Livewire construye antes de que el layout tenga acceso al árbol de propiedades del componente. La corrección real es la vista-macro `layoutData(['escuelaNivelId' => $this->escuelaNivel->id])`, que sí inyecta el dato en el contexto que el layout consume. Se aplicó en los cuatro componentes nuevos (`DatosInmueble`, `InfraestructuraNivel`, `MobiliarioNivel`, `Paso3ProximosPasos`). Esto es una corrección al mecanismo del spec, no al diseño: el resultado visible (widget verde en los tres primeros sub-pasos una vez completados) es el que el spec pedía.

## Catálogos sembrados

**`tipos_espacios`**: 26 filas (COMPENDIO, desglose de espacios capturables líneas 129-134 + comparación Inicial-vs-genérico líneas 208-216).

**`niveles_tipos_espacios`**: 88 filas en total.
- 36 filas explícitas: 13 tipos de espacio cuya aplicabilidad por nivel COMPENDIO afirma directamente (`filtro_recepcion` → solo Inicial; `biblioteca` → Primaria y Secundaria; los 11 restantes → Preescolar/Primaria/Secundaria).
- 52 filas inferidas: los 13 tipos de espacio restantes, mapeados a los 4 niveles de Básica (Inicial, Preescolar, Primaria, Secundaria) como no obligatorios, porque COMPENDIO no dice explícitamente a qué niveles aplican. Cada una de estas 52 filas está listada en `docs/decisions/PENDIENTE-matriz-espacios-por-nivel.md`.
- `obligatorio = false` en las 88 filas sin excepción: COMPENDIO enumera los espacios capturables pero nunca marca ninguno como obligatorio; la columna existe en el DDL a la espera de que SEDEQ confirme esa matriz.

**`mobiliario_conceptos`**: 41 filas, todas de Educación Inicial (COMPENDIO §Motor de Validación, ratios por sala, verificado contra req_inicial.docx líneas 133-190). Inicial es el único nivel con catálogo confirmado; el resto de niveles queda deliberadamente fuera de este MVP.

## Decisiones e inferencias registradas

1. **Matriz espacios-por-nivel**: 36 filas explícitas vs. 52 inferidas (detalle arriba). Registrado en `docs/decisions/PENDIENTE-matriz-espacios-por-nivel.md`, aún abierto — pendiente de confirmación normativa de SEDEQ.
2. **`obligatorio = false`** en todas las filas de `niveles_tipos_espacios`, explícitas e inferidas por igual — no es un valor por defecto accidental, es la única postura que COMPENDIO permite hasta que exista una fuente que diga lo contrario.
3. **Los conceptos de Sala de Usos Múltiples llevan `sala_id = NULL`**, siguiendo el significado que el propio DDL documenta para ese NULL ("el concepto no depende de una sala específica"). No se inventó una sexta fila en `salas` — Usos Múltiples no es una sala por grupo de edad como las otras cinco (Lactantes A/B/C, Maternal A/B, etc.).
4. **"Material didáctico adecuado a la edad"** se sembró como `fijo_por_sala` / `valor_ratio = 1` porque la fuente normativa (req_inicial.docx líneas 133-190) solo dice "suficiente para los menores de la sala", sin ratio numérico. La naturaleza no cuantificada queda documentada en el campo `fuente` de cada fila afectada (constante `FUENTE_NO_CUANTIFICADA` en el seeder), para que quede visible en la propia tabla y no solo en el comentario de código.
5. **Las 5 piezas "1 por sala" de las salas de Maternal** (repisa/mueble para material didáctico, material didáctico adecuado a la edad, y las demás listadas en el artículo lumped de COMPENDIO) se sembraron como 5 conceptos contables separados, no como un solo renglón agregado — porque el solicitante los cuenta y reporta por separado en el formulario.
6. **Categorías de sanitarios por nivel**: Inicial usa `alumnado_maternal` y `personal`; los otros tres niveles de Básica usan las cuatro categorías masculino/femenino estándar. Es una inferencia derivada de combinar la lista de cuatro categorías que COMPENDIO da para el formulario genérico de Básica con los dos valores adicionales del enum del DDL, que solo tienen sentido en Inicial (no hay "alumnado maternal" en Primaria).
7. **`RegistrarDatosInmueble::ejecutar(int $plantelId, int $escuelaNivelId, DatosInmueble $datos)`** recibe `escuelaNivelId` además de `plantelId` — el spec §5 omitía este parámetro, pero el caso de uso lo necesita para marcar el sub-paso completado en `escuela_nivel_pasos`, que está anclado a `escuela_nivel_id`, no a `plantel_id`.
8. **El auto-completado por reutilización de plantel aplica solo al sub-paso 1** (Datos del inmueble). El sub-paso 2 (Infraestructura) le sigue pidiendo `aulas_nivel` al segundo nivel educativo aunque comparta plantel con el primero, porque esa cifra es específica del nivel, no del inmueble. Esto es un narrowing deliberado del spec §6, no una omisión.

## Deliberadamente sin hacer

- Paso 3 sub-pasos 4-6 (Plan de estudios, Plantilla docente, Matrícula) — brechas reales de información normativa de SEDEQ, no de tiempo.
- Motor de Validación de Capacidad Instalada — no invocado ni scaffoldeado en esta sesión.
- Tres `PENDIENTE-*.md` siguen abiertos sin resolver: `PENDIENTE-plantel-solicitante-cardinalidad.md`, `PENDIENTE-origen-de-magnitud.md`, `PENDIENTE-umbral-educacion-fisica.md` (más el nuevo `PENDIENTE-matriz-espacios-por-nivel.md` de esta sesión).
- Nada se empujó a `origin`.
- `docs/progress.md` intacto — lo escribe el controlador al hacer merge, a partir de este reporte.

## Hallazgos menores diferidos (no bloquean esta entrega)

- `InfraestructuraYaCapturada` ancla el centinela de "plantel ya capturado" solo en `instalaciones_espacios`; un envío con espacios vacíos pero sanitarios no vacíos dejaría el guard sin marcar.
- `DatosInmueble::acreditacion()` y `::constancia()` reconstruyen cada una la misma subconsulta contra `DocumentoPlantel`.

## Archivos eliminados

- `app/Livewire/Tramite/Paso3Placeholder.php` y su prueba `tests/Feature/Livewire/Tramite/Paso3PlaceholderTest.php` — reemplazado por `DatosInmueble`, no dejado muerto junto al componente real.
- Los 3 stubs vacíos `app/Livewire/Tramite/Paso3/Infraestructura.php`, `Inmueble.php`, `Mobiliario.php` — placeholders de la fase de scaffolding de arquitectura, sustituidos por los componentes reales (`InfraestructuraNivel`, `DatosInmueble`, `MobiliarioNivel`) con nombres que reflejan que operan sobre un `escuela_nivel`, no sobre el plantel genérico.

## Correcciones adicionales al spec, para el registro

- `EscuelaNivel` y `NivelEducativo` recibieron docblocks `@property` (mismo remedio que `Plantel` recibió el 2026-09-08), sin los cuales Larastan no ve sus columnas/relaciones y PHPStan nivel 5 falla en accesos como `$escuelaNivel->nivelEducativo->clave`.
- Los documentos de Paso 2.2 mostrados como contexto de solo lectura en sub-paso 1 son de alcance **plantel**, no escuela: el plan asumía `documento_escuela_id`, pero la FK real es `documento_plantel_id`, así que `acreditacion()`/`constancia()` filtran por `plantel_id` vía `DocumentoPlantel`. Consistente con que sub-paso 1 es plantel-scoped en todo lo demás.

## Estado final

- Suite: 298/298 tests, 662 assertions.
- Pint: limpio.
- PHPStan: 0 errores (nivel 5 + regla PHPat ADR-001).
- Working tree limpio antes de este commit.
- Rama `worktree-paso3-sub-pasos-1-3`, no fusionada, no empujada.
