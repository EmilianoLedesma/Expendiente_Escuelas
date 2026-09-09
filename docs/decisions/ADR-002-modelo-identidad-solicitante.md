# ADR-002: Modelo de identidad y propiedad del solicitante

**Fecha:** 2026-09-09
**Estado:** Aceptado

## Contexto

La auditoría de arquitectura inicial encontró un hueco real: ni `planteles`
ni `escuelas` tenían columna de dueño, `/tramite/preregistro` no tenía
middleware `auth`, y `ListarPlantelesDisponibles` devolvía la dirección
completa de todos los planteles a cualquier visitante anónimo.

El PRD (§3) define "Solicitante" como "representante legal (persona
física/moral) de la escuela", y Paso 2 se titula "Responsable legal,
documentos y selección de niveles" — textualmente coincide con
`responsables_legales` (ya migrada, **UNIQUE por `escuela_id`**: un
registro por escuela, capturado como parte del papeleo de cada trámite, no
una identidad reutilizable). Modelar "solicitante" (quien inicia sesión y
es dueño de los registros a través del tiempo) y "responsable legal" (los
datos legales de esta escuela en este trámite) como la misma cosa rompía en
dos frentes: un gestor que administra trámites para varias escuelas no
podría tener una sola cuenta, y datos legítimamente cambiantes de trámite a
trámite (domicilio, RFC vigente) terminarían leyéndose como "es una persona
distinta" a efectos de quién puede ver sus propios expedientes.

Diseño completo, con el razonamiento íntegro: `docs/superpowers/specs/2026-09-08-modelo-identidad-solicitante-design.md`.
Plan de implementación (8 tareas, TDD): `docs/superpowers/plans/2026-09-08-modelo-identidad-solicitante.md`.
Reporte de sesión con el detalle de ejecución, hallazgos y decisiones tomadas durante la implementación: `docs/reports/2026-09-08-modelo-identidad-solicitante.md`.

## Decisión

1. **Nueva entidad `solicitantes`**, separada tanto de `users`
   (autenticación, Breeze, sin cambios) como de `responsables_legales`
   (papeleo legal por escuela/trámite, sin relación de esquema con
   `solicitantes`). `user_id` es `UNIQUE` — 1:1 para el MVP.

2. **Creación automática**: un listener en
   `Illuminate\Auth\Events\Registered` crea el `Solicitante`
   correspondiente en cada registro nuevo. Todo código downstream puede
   asumir `auth()->user()->solicitante` sin verificación de null.

3. **Backfill embebido en la migración**, no un comando Artisan aparte —
   corre automáticamente en `up()` cada vez que corren las migraciones
   (dev, CI, y el futuro despliegue), cubriendo cualquier `users` previo al
   listener. Ningún paso manual que alguien deba recordar ejecutar por
   separado.

4. **Propiedad vive en `escuelas`, no en `planteles`**:
   `escuelas.solicitante_id BIGINT NOT NULL REFERENCES solicitantes(id) ON
   DELETE RESTRICT`. `planteles` sigue siendo el registro compartido y
   neutral de un sitio físico — soporta el caso de un campus compartido por
   escuelas de solicitantes distintos, y coincide con el marco que el
   COMPENDIO usa para la Etapa 3 (reutilización centrada en el plantel, no
   en el historial del usuario). `ON DELETE RESTRICT` explícito: una
   escuela es un expediente regulatorio real, nunca debe borrarse en
   cascada por accidente al borrar un solicitante.

5. **`/tramite/*` requiere autenticación**; `ListarPlantelesDisponibles` no
   cambia de forma — la fuga real era la exposición a visitantes anónimos,
   no la visibilidad del dato de plantel entre solicitantes legítimos (semi-
   público, no secreto). El arreglo correcto es el middleware, no redacción
   ni un segundo estado "reclamado/no reclamado".

6. **Personal SEDEQ vs. solicitantes: misma tabla `users`, separados por
   rol** (`spatie/laravel-permission`, ya instalado). Sin guard de
   autenticación separado. `historial_estados_expediente.usuario_sedeq`
   (antes `VARCHAR(150)` libre) se convierte en FK real a `users(id)`.

7. **Etapa 3 — precarga sin acoplar esquemas.** Sin FK entre `solicitantes`
   y `responsables_legales`. La precarga de datos legales al iniciar un
   nuevo trámite es una lectura de la capa `Application/` (buscar la
   `escuela` más reciente del solicitante autenticado → su
   `responsables_legales`), no una relación de datos — cada trámite sigue
   capturando y validando su propio registro legal vigente, coincidiendo
   con el requisito del COMPENDIO de que los documentos de cada trámite
   coincidan con los datos de identificación de ese trámite, no un puntero
   compartido a datos que pudieron cambiar.

## Consecuencias

- `IniciarTramiteNuevo::ejecutar()` (`app/Application/Preregistro/`) ahora
  requiere el `solicitante_id` que actúa como segundo parámetro —
  resuelto por el llamador desde la sesión autenticada, nunca desde
  `DatosPreregistro` ni desde input del cliente.
- `Paso1Preregistro::guardar()` dereferencia
  `auth()->user()->solicitante->getKey()` sin verificación de null — seguro
  específicamente porque el middleware `auth` (decisión 5) garantiza que
  ninguna petición no autenticada llega a `guardar()`, y las decisiones 2+3
  garantizan que todo usuario autenticado tiene un `Solicitante`. El orden
  de aterrizaje de las tareas de implementación se invirtió explícitamente
  (middleware antes que el cambio del componente) para que esta garantía
  ya fuera cierta en cada commit individual, no una promesa sobre un commit
  futuro.
- Tres capas independientes de enforcement de propiedad, cada una con su
  propia prueba: middleware de ruta, resolución de dueño desde sesión (el
  DTO deliberadamente excluye `solicitante_id`, no puede spoofearse desde
  el cliente), y la restricción `NOT NULL` + FK en base de datos.
- `/tramite/paso2/{escuela}` está protegido por `auth` pero **no** todavía
  por dueño — cualquier usuario autenticado puede pasar cualquier `escuela`
  id. Inofensivo hoy porque Paso 2 es un placeholder que no carga ningún
  registro; en cuanto Paso 2 cargue la escuela real esto se vuelve un IDOR.
  La columna `solicitante_id` que esta ADR introduce es precisamente lo que
  hace posible la corrección futura (policy, scope en route-model-binding,
  o una lectura de `Application/`) — queda como trabajo pendiente, no
  resuelto por esta ADR.
- **Diferido deliberadamente, no en el alcance de esta ADR:** un pivote
  `solicitante_users` para multi-usuario por solicitante (una persona moral
  con varios miembros de staff con login propio) — el `user_id UNIQUE` lo
  deja fuera del MVP a propósito, confirmado como aditivo de agregar
  después sin tocar `escuelas` ni ningún FK existente hacia
  `solicitante_id`; un guard de autenticación separado para personal SEDEQ,
  considerado innecesario para el alcance actual (panel único); redacción o
  un estado "reclamado/no reclamado" para el listado de planteles,
  descartado — la autenticación sola cierra la fuga reportada.
- `docs/ddl_sistema_incorporacion_v3.sql` no estaba bajo control de
  versiones antes de que esta implementación aterrizara — su primer commit
  rastreado agrupa ediciones on-disk de tareas anteriores en un solo diff,
  un problema de atribución de `git blame` en un archivo de solo
  documentación, sin impacto funcional. No corregido (implicaría cirugía de
  historial de git sobre una rama ya fusionada); queda como nota para quien
  decida si vale la pena.
