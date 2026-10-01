# Correcciones de revisión — PR #2 (Motor de Capacidad Instalada + sub-pasos 4–6 del Paso 3)

**Fecha:** 2026-10-01
**Rama:** `feat/motor-capacidad-instalada` (worktree `.claude/worktrees/pr2-fixes`)
**Estado:** sin commit; cambios en el árbol de trabajo para revisión del controlador.

## 0. Preparación

- Se trajo `master` a la rama (`git merge master`): sin conflictos, commit de merge automático `28da228`. Entraron, entre otras cosas, la corrección de PR #1 por la que `ValidacionFinal::mount()` solo valida si no hay una evaluación guardada.
- El worktree no tenía `public/build` (ignorado por git); la primera corrida de la suite dio 71 fallos por `ViteManifestNotFoundException`. Se copió `public/build` del checkout principal (solo lectura del origen; archivo ignorado, no entra en ningún commit).
- **Línea base** (tras el merge, sin los cambios de esta sesión, con `git stash`): `php -d memory_limit=1G vendor/bin/phpunit` → **882 pruebas, 882 aprobadas, 2377 aserciones**.
- En la primera corrida (con el manifiesto faltante y otra carga en paralelo) falló además una vez `MisTramitesTest::test_ordena_del_mas_reciente_al_mas_antiguo` ("55163 is less than 51688"); no se repitió en la corrida limpia ni en la final. Ese archivo no lo toca este PR; se deja anotado como posible prueba inestable.

## 1. `#[Locked]` en las tres páginas nuevas — protección explícita (el ataque ya no funcionaba)

**Hallazgo:** `Paso3/{PlanEstudios,PlantillaDocente,Matricula}` declaraban `public EscuelaNivel $escuelaNivel;` sin `#[Locked]` (WS-5b sí lo puso en `Paso24DocumentosNivel`).

**Evidencia, obtenida con una prueba de sondeo temporal (borrada después):**
- `->set('escuelaNivel', <id ajeno>)`: Livewire **no lanza excepción**, pero el modelo no cambia (después del `set`, `escuelaNivel` seguía siendo el propio, id 1, no el ajeno, id 2). Al guardar, la escritura cayó en el nivel propio (`propio.modalidad = "escolarizada"`, `ajeno.modalidad = null`).
- `->set('escuelaNivel.id', …)` y `->set('escuelaNivel.escuela_id', …)`: Livewire lanza una `Exception` genérica "Can't set model properties directly".

Conclusión: **hoy no se puede alterar el nivel desde el cliente**. Pero el rechazo depende de cómo Livewire maneja los modelos internamente (y en la raíz es silencioso, no un error). Se añadió `#[Locked]` a las tres páginas, igual que en WS-5b, para que el rechazo sea explícito y no dependa de esos detalles.

**Prueba:** `tests/Feature/Livewire/Tramite/Paso3NivelBloqueadoTest.php::test_el_nivel_de_las_paginas_nuevas_del_paso3_esta_bloqueado`. Recorre las tres páginas y las rutas `escuelaNivel`, `escuelaNivel.id` y `escuelaNivel.escuela_id`, y exige `CannotUpdateLockedPropertyException`. Antes del cambio falló con "App\Livewire\Tramite\Paso3\PlanEstudios aceptó reescribir escuelaNivel."; después pasa (9 aserciones).

Los componentes anteriores no se revisaron uno por uno: ese barrido sigue pendiente por separado.

## 2. Lock del nivel en los guardados que borran y vuelven a crear filas — corregido

**Hallazgo:** `RegistrarMatricula` y `RegistrarPlantillaDocente` borran y vuelven a crear las filas del nivel sin serializar los guardados concurrentes.

**Cambio:** al inicio de la transacción, igual que `RegistrarDatosNivel` y la rama por nivel de `RegistrarDocumento` (WS-5b), se toma `EscuelaNivel::lockForUpdate()->findOrFail(...)` y la compuerta (`CompuertaSubPaso3::verificar`) se vuelve a ejecutar sobre la fila bloqueada. La verificación previa a la validación se mantiene: el orden de los errores (primero la compuerta, luego `DatosInvalidos`) no cambia.

**Pruebas:** `RegistrarMatriculaTest::test_reemplaza_la_matricula_bajo_el_lock_del_nivel` y `RegistrarPlantillaDocenteTest::test_reemplaza_la_plantilla_bajo_el_lock_del_nivel`. Con el registro de consultas, comprueban que se emite un `select … from "escuela_niveles" … for update`. Antes del cambio fallaban ("Failed asserting that an array is not empty."); ahora pasan. **Límite:** PHPUnit no puede reproducir una carrera real entre dos conexiones. Estas pruebas solo demuestran que se toma el lock, no que se evita la carrera. No se escribió una prueba de carrera simulada. Las pruebas de comportamiento del caso de uso que ya existían siguen pasando (7/7 y 9/9).

## 3. Evaluaciones guardadas antes de la capacidad — corregido

**Hallazgo:** una evaluación guardada antes del motor de capacidad (sin la clave `capacidad` en `resultados`, o con el formato plano más antiguo) se mostraba sin la sección de capacidad. Como `mount()` solo validaba si no existía ninguna evaluación, el solicitante no veía esa sección hasta pulsar "Validar de nuevo".

**Cambio:** nuevo `UltimaValidacionFinal::faltaParaEscuela(int $escuelaId): bool`. Devuelve verdadero si no hay evaluación o si la última no tiene la clave `capacidad`. `ValidacionFinal::mount()` lo usa en lugar de `paraEscuela(...) === null`. Una evaluación actual y completa sigue sin provocar escritura en el GET: `test_abrir_la_pagina_de_nuevo_no_repite_la_validacion_si_ya_hay_una` sigue pasando.

**Prueba:** `ValidacionFinalTest::test_una_evaluacion_anterior_a_la_capacidad_se_vuelve_a_validar_al_abrir`. Quita la clave `capacidad` de la evaluación guardada, vuelve a abrir la página y espera ver "Capacidad instalada · Primaria" y 2 evaluaciones. Antes del cambio falló porque el HTML no contenía ese texto; ahora pasa.

## 4. P2: la matrícula del plantel incluye la de otros dueños — documentado, sin cambiar el comportamiento

Comprobado en el código: `ConstruirDatosCapacidad::matriculaPlantel()` suma `matricula_grados` y `matricula_salas` de todos los `escuela_niveles` de todas las escuelas del plantel, sin filtrar por solicitante. Esa suma es la magnitud de `primaria.superficie.predio_total` y `secundaria.superficie.predio_total` (`ratio_por_alumno`, 2.50 m²/alumno en `ReglasValidacionSeeder`). El requerido se muestra como "Requerido: X m²" en la página y en el PDF. Con dos dueños en un mismo plantel, cada uno podría deducir la matrícula del otro.

Se añadió una sección a `docs/decisions/PENDIENTE-motor-capacidad-provisionales.md` que explica este efecto. Hoy solo afecta a planteles compartidos heredados: desde el 2026-09-23, `IniciarTramiteNuevo` impide adjuntar una escuela a un plantel ajeno, pero la base de desarrollo tiene uno. También se registró que depende de `PENDIENTE-plantel-solicitante-cardinalidad.md`.

## 5. Educación Física en Secundaria (P10) — documentado

`ConstruirDatosCapacidad` cuenta a los docentes de Educación Física comparando el **nombre** de la asignatura (`'Educación Física'`). Se añadió al mismo documento PENDIENTE una nota sobre esta fragilidad: si cambia la redacción en el catálogo, el conteo baja a 0 sin dar error.

## 6. Revisión del resto del trabajo nuevo

Se leyeron `RegistrarPlanEstudios`, `PlantillaCapturada`, `MatriculaCapturada`, las tres vistas Blade, los cambios en `ResumenTramite`, `PresentadorValidacion::filasCapacidad`, `ReporteValidacionPdf` y su vista PDF, `EvaluadorMobiliario`, `GradosSeeder` y `EjecutarValidacionCapacidad`. **No se encontró ningún otro defecto confirmado.** Observaciones menores que no se corrigieron (no son errores demostrables):

- `PlantillaCapturada::personas()` (líneas 18–19) lee **todas** las filas de `personal_salas` y `personal_asignaturas` de la base, sin limitarse al nivel. No expone datos, porque solo se consultan los ids del propio nivel, pero el costo crece con el total del sistema. Bastaría con `whereIn('personal_id', …)`.
- `Matricula::guardar()` y `PlantillaDocente::guardar()` tipan los valores del formulario como `string` en los closures (`fn (string $alumnos)`, `fn (string $valor)`). Un cliente manipulado que envíe un número JSON en lugar de una cadena provocaría un `TypeError` (500) en vez de un error de campo. Desde el navegador, Livewire envía cadenas, así que no se reproduce con uso normal.
- `EjecutarValidacionFinal` consulta dos veces los `escuela_niveles` de la escuela (en el bucle de niveles y en `capacidad()`). Es redundante, no incorrecto.
- `GradosSeeder`, `EvaluadorMobiliario` y los enlaces "Revisar la captura" (`RUTAS_PASO3[pasoCorreccion]`, todos con el parámetro `escuelaNivel`) son coherentes con el DDL y las rutas.

## 7. Verificación final

- Archivos afectados (por separado): `Paso3NivelBloqueadoTest` 1/1, `RegistrarMatriculaTest` 7/7, `RegistrarPlantillaDocenteTest` 9/9, `ValidacionFinalTest` 12/12, `Paso3MatriculaTest` 5/5, `Paso3PlantillaDocenteTest` 5/5, `Paso3PlanEstudiosTest` 5/5, `EjecutarValidacionFinalTest` 10/10.
- Suite completa: **886 pruebas, 886 aprobadas, 2390 aserciones** (línea base 882; +4 pruebas nuevas).
- `vendor/bin/pint --test`: aprobado. `vendor/bin/phpstan analyse`: 0 errores (incluye la regla PHPat de ADR-001).

## 8. Lo que se dejó deliberadamente

- El barrido de `#[Locked]` en los componentes Livewire anteriores (pendiente aparte).
- Cambiar P2 o P10: siguen siendo elecciones provisionales a la espera de SEDEQ o del dueño.
- Las observaciones menores de §6.
- `docs/progress.md` no se tocó (solo lo escribe el controlador).
