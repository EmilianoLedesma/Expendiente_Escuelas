# Informe — Refactor de la Constancia de Seguridad Estructural y de Ocupación (Paso 2.2)

Fecha: 2026-10-20. Rama: `worktree-constancia-seguridad` (sin commit, sin push, sin merge). Especificación y plan locales (gitignored): `docs/superpowers/specs/2026-10-20-constancia-seguridad-design.md` y `docs/superpowers/plans/2026-10-20-constancia-seguridad.md`. Pendientes jurídicos: `docs/decisions/PENDIENTE-constancia-seguridad-estructural.md`. **No se tocó `COMPENDIO_MAESTRO`** (lo actualiza otro agente) ni `docs/progress.md`.

## 1. Diagnóstico del formulario anterior

Campos: fecha de emisión, nombre del perito, cédula (opcional), número de registro "DRO" (obligatorio), autoridad (opcional), vigencia del registro (opcional, DATE), vigencia de la constancia (**obligatoria**).

- No identificaba la calidad en que firma el profesional; la etiqueta suponía DRO.
- Obligaba a inventar una fecha de vencimiento cuando el documento no la trae.
- `ValidarVigenciaDocumentos` bloqueaba si el **año** de `perito_registro_vigencia` ≠ año de emisión:
  - falso positivo: registro vigente hasta 2027-03-31, constancia de 2026-05-10 → rechazada;
  - falso negativo: registro vencido el 2026-02-28, constancia de 2026-09-01 → aceptada.
- El caso de uso no validaba nada de la constancia (solo el formulario).

## 2. Hallazgos normativos verificados

Texto leído del Reglamento de Construcción para el Municipio de Querétaro (PDF del registro municipal, reformas hasta Gaceta Municipal No. 14 del 29-mar-2022). Detalle y matriz de trazabilidad en la especificación (N1–N10) y el PENDIENTE.

- Art. 120: las escuelas son Grupo A. Art. 274 a): requieren Visto Bueno de Seguridad y Operación. Art. 277: Grupo A registra una Constancia de Seguridad Estructural, renovada cada cinco años, en la que un **Corresponsable en Seguridad Estructural** hace constar la seguridad. Art. 279: cambio de uso, VoBo en 90 días con la CSE "en su caso". Art. 289 fr. III: el **DRO** da su responsiva al suscribir el VoBo. Arts. 291, 293–295, 297: registro, refrendo y padrón anual de DRO y Corresponsables.
- DRO y Corresponsable **no son equivalentes**; la carta responsiva pertenece al VoBo, no a la CSE.
- **Ningún artículo iguala el año del registro con el año de emisión.** La regla proviene de requisitos de SEDEQ de Básica citados por el Compendio; no se pudieron leer los PDF de SEDEQ (WAF/500). Certeza: Documentado, no contrastado.
- El Reglamento no nombra una "Constancia de Seguridad Estructural y de Ocupación": el nombre es de SEDEQ.
- Todo lo anterior es del Municipio de Querétaro; no se aplica a otros municipios. Reformas posteriores a 2022: no encontradas (no concluyente). Una consulta a la presentación municipal de lineamientos de DRO no fue legible (PDF comprimido).

## 3–4. Especificación y plan

Ver los dos archivos locales. Decisiones: retirar la regla año=año; no reinterpretar `perito_registro_vigencia`; columnas explícitas nuevas; `perito_registro_dro` conserva el nombre y guarda el número de registro de cualquier calidad; vigencia declarada con `tipo_vigencia` (mismo modelo del dictamen); vencimiento del registro anterior a la emisión **avisa sin bloquear**.

## 5. Cambios efectuados

- `DatosDocumento`: `calidadProfesional`, `calidadProfesionalOtra`, `folioConstancia`, `peritoRegistroFecha`, `peritoRegistroVencimiento` (reutiliza `tipoVigencia` y `vigenciaConstancia`).
- `RegistrarDocumento`: `validarConstancia` (invariantes sin Livewire), `columnasConstancia` (reemplazo sobrescribe toda la extensión; descripción de calidad y vencimiento solo con la opción que los justifica), `fecha_vigencia` solo de una fecha expresa. Un DTO anterior sin `tipoVigencia` sigue funcionando (infiere el tipo; no exige calidad).
- `ValidarVigenciaDocumentos`: se elimina la regla por año; nuevo `avisos()` no bloqueante.
- `ConstanciaSeguridadForm`, `Paso2Documentos::guardarConstancia` y el bloque Blade (secciones "Datos de la constancia" y "Datos del profesional que firma", campos condicionales, aviso visible).
- Modelo `ConstanciaSeguridadEstructural`: fillable y docblock.

## 6. Migración

`2026_10_20_000100_extend_constancias_seguridad_estructural.php`: aditiva y reversible. Columnas `calidad_profesional`, `calidad_profesional_otra`, `folio_constancia`, `perito_registro_fecha`, `perito_registro_vencimiento`, `tipo_vigencia` (NOT NULL, default `no_capturada`) con CHECK; backfill `fecha_expresa` donde `vigencia_constancia` no es NULL (esa columna ya significaba "fecha que declara la constancia"); `COMMENT ON COLUMN` sobre `perito_registro_dro` y `perito_registro_vigencia`. **No se ejecutó en la BD de desarrollo** (requiere autorización); las pruebas la corren sobre la BD de pruebas.

## 7–8. Pruebas y resultados

Nuevas: `ConstanciasSeguridadMigracionTest` (8), `RegistrarConstanciaSeguridadTest` (26 con proveedor de datos), 7 de vigencia/avisos en `ValidarVigenciaDocumentosTest` (en lugar de la de año=año) y 10 de Livewire en `Paso2DocumentosTest` (más 3 existentes actualizadas porque el formulario ahora exige `tipoVigencia` y calidad).

Resultados reales: suite completa **1652/1652** (5900 aserciones); `pint --test` limpio; PHPStan 0 errores. Regresiones cubiertas por la suite completa: completitud de documentos, Protección Civil, Dictamen, acreditación, reemplazo, trámite enviado.

Limitación honesta: la prueba de vigencia (T3) se escribió y la implementación se hizo antes de correr su RED por separado (la herramienta rechazó el comando compuesto de git para restaurar la clase original); las pruebas de falso positivo/negativo sí corresponden al comportamiento retirado, pero no se observó su fallo previo. En T1 y T2 el RED sí se observó.

## 9. Compatibilidad con expedientes existentes

Filas anteriores: `tipo_vigencia` = `fecha_expresa` si tenían `vigencia_constancia`, si no `no_capturada`; calidad y folio NULL; `perito_registro_vigencia` intacto y sin evaluarse. `documentos_plantel.fecha_vigencia` no cambia. Una constancia ya capturada deja de ser rechazada por la regla de año.

## 10. Riesgos y pendientes jurídicos

Ver PENDIENTE (9 preguntas para SEDEQ). Riesgos: (a) `perito_registro_dro` mantiene un nombre engañoso; (b) la rama de revisión HUD (`hud-revision`) puede leer `perito_registro_vigencia` y no conoce los campos nuevos: actualizar `ContenidoGeneralRevision` al integrar; (c) el aviso del vencimiento del registro es una inferencia de los arts. 295 y 297, solo de Querétaro.

## 11. Archivos modificados

`app/Application/Documentos/{DTO/DatosDocumento,RegistrarDocumento,ValidarVigenciaDocumentos}.php`, `app/Livewire/Forms/ConstanciaSeguridadForm.php`, `app/Livewire/Tramite/Paso2Documentos.php`, `app/Models/ConstanciaSeguridadEstructural.php`, `resources/views/livewire/tramite/paso2-documentos.blade.php`, migración nueva, tests (`ConstanciasSeguridadMigracionTest`, `RegistrarConstanciaSeguridadTest`, `ValidarVigenciaDocumentosTest`, `Paso2DocumentosTest`), este informe y el PENDIENTE.

## 12. Decisiones a contrastar con el nuevo Compendio

La regla "año del registro = año de emisión" (filas de la constancia y ejemplo de enero 2011); la unidad documental (un PDF con carta responsiva); calidades aceptadas; si el registro debe estar vigente a la emisión; aplicabilidad fuera del Municipio de Querétaro.
