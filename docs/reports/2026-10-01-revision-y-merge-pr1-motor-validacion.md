# Revisión, correcciones y merge del PR #1 (motor de validación documental, ADR-007)

**Fecha:** 2026-10-01
**PR:** #1 `spike/validation-engine-eval` → `master` (merge `b684e12`, commit de correcciones `15c73d6`)
**Relacionado:** `docs/decisions/ADR-007-motor-validacion-hechos.md`, `docs/reports/2026-09-30-evaluacion-motor-validacion.md`, `docs/reports/2026-09-30-motor-validacion-integracion.md`

## 1. Qué se pidió

Revisar el PR #1, corregir los hallazgos, actualizar el DDL con lo que el PR cambia y mergearlo.

## 2. Revisión

El PR traía 5 373 líneas nuevas (motor de reglas en `app/Domain/Validaciones/Documental/`, casos de uso en `app/Application/Validaciones/`, página `ValidacionFinal`, 4 migraciones, reporte en PDF). CI estaba en verde antes de la revisión (Pint, PHPStan + PHPat, pruebas).

La rama local `spike/validation-engine-eval` estaba desactualizada (en `cd91ffe`, el merge-base). El contenido real del PR está en `origin/spike/validation-engine-eval` (`40848c4`). La revisión se hizo sobre la rama remota. Una primera pasada automática revisó por error el diff local (una línea de `CLAUDE.md`) y no encontró nada; se repitió contra el PR.

Se leyeron los archivos centrales (`EjecutarValidacionFinal`, `ConstruirContextoValidacion`, `RegistrarDocumento`, `ReciboNoReutilizado`, `IdentificadorCoincide`, `ValidacionFinal`, el controlador del PDF, rutas y la primera migración). No se leyeron las 5 373 líneas completas.

## 3. Hallazgos y resolución

| # | Hallazgo | Resolución |
|---|----------|------------|
| 1 | `ValidacionFinal::mount()` ejecutaba la validación completa en cada GET: cada recarga guardaba un PDF y una fila en `evaluaciones_validacion`. | **Corregido.** `mount()` valida solo si no existe una evaluación guardada; las siguientes ejecuciones pasan por `validarDeNuevo`. |
| 2 | Se creyó que un documento presente sin datos tipados dejaba de bloquear el envío (`no_evaluable` no bloquea). | **Retirado, era un error de la revisión.** Todas las reglas (`ReglaDeCoincidencia`, `DomicilioCoincide`, `ReciboNoReutilizado`) ya devuelven `no_cumple` cuando el documento está presente y faltan sus datos. No hubo cambio. |
| 3 | `foliosAjenos` cargaba en memoria todos los folios de recibo de todos los trámites por cada nivel. | **Corregido.** `paraNivel` trae solo los folios de otros niveles iguales al propio (`UPPER(TRIM(folio))`, misma normalización que la regla). |
| 4 | El PR agrega 6 tablas y la columna `gestores.curp`, pero el DDL no se actualizó. | **Corregido.** Ver sección 5. |

Límite de diseño, sin cambio y probablemente intencional: el motor compara lo que el solicitante captura sobre cada documento contra lo que declaró en otros pasos; no lee el PDF. Demuestra consistencia interna, no autenticidad.

## 4. Correcciones (TDD)

Pruebas escritas primero y vistas fallar por la razón correcta:

- `ValidacionFinalTest::test_abrir_la_pagina_de_nuevo_no_repite_la_validacion_si_ya_hay_una` (falló con 2 filas en lugar de 1).
- `ValidacionPorNivelTest::test_el_contexto_trae_solo_los_folios_ajenos_que_coinciden_ignorando_mayusculas_y_espacios` (falló: traía también un folio no relacionado).
- `ValidacionPorNivelTest::test_el_contexto_del_nivel_lee_el_recibo_el_acervo_y_los_folios_de_otros_niveles`: la aserción de `foliosAjenos` pasó de «contiene el folio ajeno» a «vacío cuando no coincide».

Archivos: `ConstruirContextoValidacion.php`, `ContextoValidacion.php` (solo docblock), `Livewire/Tramite/ValidacionFinal.php` y los dos archivos de prueba.

**Evidencia:** suite completa 806/806 (2 170 aserciones; el PR traía 804), Pint limpio, PHPStan nivel 5 + PHPat 0 errores. CI en verde sobre `15c73d6` (las tres verificaciones, dos ejecuciones). Merge con `gh pr merge 1 --merge` → `b684e12`.

**Notas de entorno:** en este equipo `php artisan test` completo se cae por límite de memoria de 128 M y PHPStan también; se corrió con `php -d memory_limit=1G vendor/bin/phpunit` y `--memory-limit=1G`. Un worktree nuevo necesita su propio `composer install` (no se puede enlazar `vendor/` de otro checkout con una junction: el autoload resolvería a las rutas del checkout original).

## 5. DDL

`docs/ddl_sistema_incorporacion_v3.sql` está ignorado por git (`*.sql`, decisión D7 de la auditoría), así que el cambio no viaja en el PR y vive solo en el checkout principal. Se agregaron, copiados de las migraciones:

- `gestores.curp VARCHAR(18)` (migración `2026_09_30_000001`).
- `credenciales_ine`, `constancias_curp`, `constancias_situacion_fiscal`, `certificados_numero_oficial` (migración `2026_09_30_000000`).
- `relaciones_acervo_bibliografico` (migración `2026_09_30_000003`).
- `evaluaciones_validacion` con su índice (migración `2026_09_30_000002`).

## 6. Decisiones y lo que se dejó sin hacer

- **Al abrir la página no se revalida si ya hay una evaluación.** Costo: tras corregir un documento, el solicitante debe pulsar «Validar de nuevo» para ver el resultado actualizado; mientras tanto ve el resultado anterior. Alternativa descartada por complejidad: revalidar si hay datos más recientes que la última evaluación.
- **Sin índice único de folios.** La unicidad del folio sigue comprobándose solo al validar, no en base de datos. Tampoco se cambió el mensaje de «ya está registrado en otro nivel o trámite»: avisar de un folio repetido revela que existe en otro lugar, y eso es inherente a la regla.
- **Pendiente para WS-7:** el envío debe volver a ejecutar la validación, no confiar en una fila anterior (ya anotado en `PENDIENTE-edicion-hasta-envio.md`).
- **Pendiente del dev:** correr `php artisan migrate` (4 migraciones nuevas) y `php artisan db:seed --class=TiposDocumentosSeeder` (agrega `constancia_curp` y `constancia_situacion_fiscal`; ahora 15 filas), con confirmación del owner por la regla de seguridad de datos.
