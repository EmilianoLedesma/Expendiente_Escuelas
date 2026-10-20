# Informe — Refactor del Visto Bueno / Dictamen de Protección Civil (Paso 2.2)

Fecha: 2026-10-20. Rama: `worktree-proteccion-civil-vb`. Referencia normativa: `COMPENDIO_MAESTRO` §4 («Fundamento normativo del Visto Bueno de Protección Civil») y §9 («Segunda pasada sobre Protección Civil»), commit `8c5d405`. El Compendio **no se modificó**. Especificación y plan locales (gitignored): `docs/superpowers/specs/2026-10-20-proteccion-civil-design.md` y `docs/superpowers/plans/2026-10-20-proteccion-civil.md`.

## 1. Comparación antes / después

| | Antes | Después |
|---|---|---|
| Captura | Solo `fechaEmision` y el PDF | Tipo de documento (Visto Bueno / Dictamen / Otro con descripción), folio, fecha de emisión, quién expide (Coordinación Municipal de PC de Querétaro u otra autoridad), autoridad emisora, año del Visto Bueno (solo Querétaro), riesgo declarado, vencimiento declarado, condicionantes |
| Vigencia | `vigencia_max_dias = 30`: `fecha_vigencia = emisión + 30 días`, bloqueaba al vencer | Sin cálculo a 30 días. Vence por la fecha que declara el documento o, solo para el Visto Bueno de Querétaro con año capturado, el 31-dic de ese año (art. 46) |
| Origen de una fecha | No se guardaba | `fecha_vencimiento_documento` (declarada) separada de `fecha_vencimiento_normativo` con su `regimen_normativo` |
| Riesgo | No existía | Se captura tal como lo dice el documento; para documentos de Querétaro, medio o bajo genera un aviso con el art. 48, fr. I, inciso m) |
| Persistencia | `documentos_plantel` | Más la tabla `vistos_buenos_proteccion_civil` (1:1, columnas tipadas y CHECK) |
| Históricos | Fecha calculada tratada como vencimiento | Fila `no_capturada`; la fecha calculada se conserva marcada como no verificada |

## 2. Reglas jurídicas implementadas (y su alcance)

- **Vencimiento normativo** — Reglamento de Protección Civil del Municipio de Querétaro (2017), art. 46, y cédulas TR-140600-007/-008/-009 (año calendario, hasta el 31-dic). Solo cuando el emisor es el Municipio de Querétaro, el instrumento es un Visto Bueno y se capturó el año de trámite. **No** se deriva el año de la fecha de emisión. Un documento vencido bloquea el avance; no se asume prórroga en enero–mayo.
- **Vencimiento declarado** — la fecha que indica el propio documento (patrón del dictamen), para cualquier emisor.
- **Aviso por riesgo** — art. 48, fr. I, inciso m): los centros educativos son de alto riesgo. Solo documentos del Municipio de Querétaro; solo aviso; `no_indicado` y `alto` no avisan.
- **Sin vencimiento determinable** (otra autoridad sin fecha expresa, Querétaro sin año, fila histórica): el documento se conserva, no bloquea y se muestra «SEDEQ lo revisará». No se inventa fecha.

Todo lo anterior es consistencia técnica o regla municipal confirmada; **ningún documento queda marcado como aceptado por SEDEQ**.

## 3. Reglas que permanecen pendientes (no se implementan como bloqueo)

Por plantel o por nivel; denominación aceptada por SEDEQ; documento en renovación de enero a mayo; antigüedad máxima de presentación; documentos de otros municipios; alcance de «centros educativos»; Programa Interno, plan de evacuación y simulacros como documentos independientes. Ver `PENDIENTE-dictamenes-por-nivel.md` (actualizado, sin renombrar) y el Compendio §9.

**Decisión que conviene confirmar con el dueño y SEDEQ:** el vencimiento normativo del 31-dic **sí bloquea** (art. 46 está confirmado para Querétaro). Si SEDEQ aceptara el Visto Bueno del año anterior durante la renovación, bastaría cambiar ese bloqueo por aviso en `ValidarVigenciaDocumentos`.

## 4. Archivos

Código: `app/Application/Documentos/{DTO/DatosDocumento,RegistrarDocumento,ValidarVigenciaDocumentos}.php`, `app/Livewire/Forms/ProteccionCivilForm.php`, `app/Livewire/Tramite/Paso2Documentos.php`, `app/Models/{VistoBuenoProteccionCivil (nuevo),DocumentoPlantel}.php`, `resources/views/livewire/tramite/paso2-documentos.blade.php`, `database/seeders/TiposDocumentosSeeder.php`.
Migración nueva: `database/migrations/2026_10_20_000200_create_vistos_buenos_proteccion_civil.php`.
Pruebas nuevas: `VistosBuenosProteccionCivilMigracionTest`, `RegistrarProteccionCivilTest`; ampliadas: `ValidarVigenciaDocumentosTest`, `Paso2DocumentosTest`, `GuardaTramiteEnviadoTest`; actualizadas por el retiro de los 30 días: `TiposDocumentosSeederTest`, `VigenciaProteccionCivilMigracionTest`, `DictamenUsoSueloTest`, `RegistrarDocumentoTest`.

## 5. Migración y compatibilidad con registros históricos

`2026_10_20_000200`: crea la tabla; para cada documento de PC existente inserta una fila `tipo_vigencia = no_capturada` con la fecha calculada anterior en `fecha_calculada_30_dias_historica` (marcada no verificada); pone `documentos_plantel.fecha_vigencia` y `tipos_documentos.vigencia_max_dias` en NULL. Se conservan el PDF, la fecha de emisión y los demás metadatos. `down()` restaura `vigencia_max_dias = 30` y la fecha (la conservada, o emisión + 30 días) y elimina la tabla; los datos capturados con el formulario nuevo se pierden al revertir. El seeder ya no siembra 30 (y una BD ya sembrada se corrige con la migración, porque `insertOrIgnore` no actualiza filas). **No se ejecutó en la BD de desarrollo** (requiere autorización); las pruebas la corren sobre la BD de pruebas.

## 6. Evidencia de pruebas

Línea base antes de modificar: 1652/1652 (toda la suite). Suites relevantes ejecutadas después de los cambios, todas en verde: base de datos y modelos (165), `tests/Feature/Application/Documentos` (199), `Paso2DocumentosTest` (83) y `GuardaTramiteEnviadoTest` (40).

**Corrida completa tras los cambios:** 1728/1728 pruebas (6170 aserciones), `pint --test` limpio (tras aplicar dos correcciones de estilo en archivos de prueba) y PHPStan con 0 errores. Una primera corrida completa fue detenida por el sistema por poca memoria (no por un fallo) y se repitió en primer plano.

**Desviaciones respecto al TDD:** (a) una prueba nueva de T2 tenía un defecto del propio fixture (emisión futura) y se corrigió; (b) cuatro pruebas anteriores que codificaban los 30 días se reescribieron al nuevo comportamiento, sin eliminarlas sin sustituto; (c) el RED de T2 se observó parcialmente por parámetros inexistentes del DTO.

## 7. Decisiones para SEDEQ y riesgos de integración

Preguntas: ver §3 y el Compendio §9 (puntos 1–7). Riesgos: (a) `hud-revision` (`ContenidoGeneralRevision`) debe mostrar las columnas nuevas y no depender de `fecha_vigencia` para este documento; (b) el vencimiento normativo depende de que el solicitante capture el año del Visto Bueno; sin él solo hay aviso; (c) las cédulas municipales son de febrero de 2024 y el art. 49 se reformó en junio de 2025: los requisitos para obtener el Visto Bueno no se contrastaron (no son documentos que pida el sistema).
