# Reporte: Dictamen de Uso de Suelo (Paso 2.2) — vigencia declarada por el documento

**Fecha:** 2026-10-19 · **Rama:** `worktree-dictamen-uso-suelo` · **Especificación y plan:** `docs/superpowers/specs/2026-10-19-dictamen-uso-suelo-design.md` y `docs/superpowers/plans/2026-10-19-dictamen-uso-suelo.md` (locales, ignorados por git).

## 1. Diagnóstico del código previo

- El formulario capturaba autoridad, folio, fecha de emisión y giro (todos obligatorios), sin fecha de vencimiento ni condicionantes.
- `RegistrarDocumento` calculaba `documentos_plantel.fecha_vigencia = fecha_emision + 30 días` (`tipos_documentos.vigencia_max_dias = 30`) y `ValidarVigenciaDocumentos` bloqueaba el avance si esa fecha calculada ya había pasado, con el mensaje "ha superado su vigencia máxima".
- Resultado: un dictamen válido emitido hace más de 30 días se rechazaba aunque el documento no tuviera fecha de vencimiento o la tuviera en el futuro.

## 2. Resolución sobre `vigencia_max_dias = 30`

| Pregunta | Respuesta |
|---|---|
| ¿De dónde salió? | Decisión de diseño de `2026-09-10-paso2-documentos-design.md` §5 y PRD:117-119. COMPENDIO_MAESTRO fila 4: "regla operativa confirmada por el usuario, **no aparece en los documentos normativos fuente**". |
| ¿Qué es? | Una **antigüedad administrativa máxima de presentación**, sin cita normativa verificada, tratada como si fuera la **vigencia jurídica** del dictamen. |
| ¿Qué dice la fuente oficial? | Municipio de Querétaro TR-170100-017: vigencia = "la que indique el documento". TR-170100-044: solo la factibilidad de giro tiene vigencia de 1 año calendario; el dictamen no indica plazo. |
| Decisión (dueño, 2026-10-19) | Retirarla como regla **bloqueante**; anular por migración reversible las fechas calculadas ya guardadas. |
| Impacto | Un dictamen sin fecha expresa, o con fecha expresa futura, ya no se rechaza por antigüedad. Solo bloquea una fecha de vencimiento **declarada** y ya pasada. Protección Civil y Seguridad Estructural no cambian. |
| Reversión | `down()` de `2026_10_19_000300` restaura `vigencia_max_dias = 30` y recalcula `fecha_vigencia = fecha_emision + 30 días`. |
| Pendiente | Si SEDEQ confirma una antigüedad máxima, se implementa como regla **separada**, sin reutilizar la vigencia declarada. |

## 3. Cambios

| Tarea | Commit | Resultado |
|---|---|---|
| 1 | `6287630` | Migración `2026_10_19_000200`: `tipo_vigencia` (`fecha_expresa` / `sin_fecha_expresa` / `no_capturada`, por omisión `no_capturada`), `fecha_vencimiento_documento`, `condicionantes`, CHECK de coherencia. Migración de datos `2026_10_19_000300`. Seeder `vigencia_max_dias => null`. |
| 2 | `11796d7` | `RegistrarDocumento::validarDictamen()` (largos, solo espacios, fechas reales, vencimiento ≥ emisión, requeridos en captura nueva); no calcula vigencia; helpers de validación compartidos con la acreditación. |
| 3 | `ded36ad` | `ValidarVigenciaDocumentos` juzga el dictamen por la fecha declarada; mensaje con la fecha. Fixtures de "dictamen vencido" migrados. |
| 4 | `e3b0f69` | `DictamenUsoSueloForm`: `tipoVigencia`, `fechaVencimientoDocumento`, `condicionantes`, `datosParaDocumento()`. |
| 5 | `e7dcbf2` | Vista: etiqueta y ayuda de uso/giro, pregunta de fecha de vencimiento, fecha condicional, condicionantes. |
| 6 | `7aef56c` | Pruebas de regresión. |
| 7 | (este) | Compendio (filas 4 y 195), PRD, PENDIENTE, este informe. |

## 4. Verificación (ejecutada)

- Corrida completa en la rama: **1576 de 1576 pruebas pasaron** (5620 aserciones); Pint limpio; PHPStan 0 errores.
- Migraciones reversibles comprobadas en la BD de **pruebas** (7 → 4 → 7 columnas).
- La corrida completa necesita `php -d memory_limit=1G`; una corrida anterior fue detenida por falta de memoria del sistema y se repitió.

## 5. Desviaciones del proceso

- El worktree se creó desde `origin/master` (sin la fusión de la acreditación); se avanzó con `git merge --ff-only master` antes de empezar. La línea base de 1493 pruebas no corresponde a `master`; la referencia válida es la corrida final (1576).
- Las pruebas del formulario (`ValidacionFormulariosTramiteTest`) se escribieron en el mismo paso que la implementación, así que no se vieron fallar antes; las de persistencia sí se vieron fallar primero.
- El mensaje de bloqueo del dictamen cambió ("la fecha de vencimiento indicada en el documento (dd/mm/aaaa) ya pasó…"); las pruebas que dependían del texto anterior se actualizaron.
- Un test de fallo transaccional (`test_fallo_dentro_de_la_transaccion_*`) usaba un giro de 201 caracteres para forzar un error de BD; ahora la validación lo detecta antes, así que se cambió el disparador a la constancia estructural.

## 6. Tabla de decisiones pendientes

| Decisión pendiente | Estado previo | Impacto de la implementación | Estado final | Evidencia |
|---|---|---|---|---|
| Antigüedad máxima de 30 días del dictamen | Bloqueante, sin fundamento normativo verificado | Deja de bloquear; la fecha calculada se anula (reversible) | **Técnicamente resuelta** (se retira el bloqueo); la antigüedad que SEDEQ pueda exigir queda **normativamente sin resolver** | `ValidarVigenciaDocumentosTest::test_acepta_dictamen_emitido_hace_mucho_con_vencimiento_declarado_futuro`, `::test_acepta_dictamen_sin_fecha_expresa_aunque_se_emitio_hace_mucho`; `DictamenUsoSueloTest::test_data_migration_up_nulls_calculated_dates_and_down_restores_them`; `TiposDocumentosSeederTest::test_la_vigencia_de_proteccion_civil_se_conserva_y_la_del_dictamen_es_la_declarada` |
| Vigencia jurídica del dictamen | No se capturaba | Se captura fecha expresa o `sin_fecha_expresa`; nunca se inventa una fecha | **Técnicamente resuelta** | `RegistrarDocumentoTest::test_dictamen_persiste_la_fecha_de_vencimiento_declarada_y_las_condicionantes`, `::test_dictamen_sin_fecha_expresa_no_inventa_ni_guarda_fecha`, `::test_dictamen_no_calcula_una_fecha_de_vigencia_del_documento` |
| Aceptación de un dictamen sin fecha expresa | No existía el concepto | Se acepta estructuralmente; la suficiencia la revisa SEDEQ | **Normativamente sin resolver** | PENDIENTE-dictamenes-por-nivel, pregunta 2 |
| Dictámenes ya capturados | Con fecha calculada | Estado explícito `no_capturada`: ni vigentes ni vencidos, no bloquean | **Técnicamente resuelta** | `DictamenUsoSueloTest::test_legacy_row_defaults_to_no_capturada`; `ValidarVigenciaDocumentosTest::test_un_dictamen_anterior_sin_vigencia_capturada_no_se_marca_ni_vigente_ni_vencido` |
| Condicionantes urbanísticas | No se capturaban | Campo opcional (≤1000, normalizado) | **Técnicamente resuelta**; su cumplimiento queda a revisión humana | `RegistrarDocumentoTest::test_dictamen_persiste_la_fecha_de_vencimiento_declarada_y_las_condicionantes`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_dictamen_condicionantes_son_opcionales_y_se_normalizan` |
| Factibilidad de giro (TR-170100-044) | No considerada | Fuera de alcance; documentada la diferencia de vigencias | **Normativamente sin resolver** (¿la exige SEDEQ?) | PENDIENTE, pregunta 3 |
| Dictámenes de otros municipios; requisitos por nivel | Sin documentar | Autoridad en campo libre; sin supuesto de la Dirección de Desarrollo Urbano de la capital | **Normativamente sin resolver**; PDF de requisitos de SEDEQ no legibles | PENDIENTE, preguntas 4 y 5 |
| Dictámenes por nivel / número oficial | Abierta | Ninguno | Sin cambio | `PENDIENTE-dictamenes-por-nivel.md` |

## 7. Matriz normativa

| Regla | Tipo | Fuente | Campo / validación | Prueba |
|---|---|---|---|---|
| Vigencia = la que indique el documento | **Legal confirmado** (Municipio de Querétaro) | TR-170100-017 | `tipo_vigencia`, `fecha_vencimiento_documento` | `RegistrarDocumentoTest::test_dictamen_persiste_la_fecha_de_vencimiento_declarada_y_las_condicionantes` |
| El dictamen contiene giros y condicionantes | **Legal confirmado** | TR-170100-017 (art. 261 Reglamento) | `giro_autorizado`, `condicionantes` | `Paso2DocumentosTest::test_dictamen_con_fecha_expresa_guarda_vencimiento_y_condicionantes_sin_fecha_calculada` |
| Autoridad emisora (Dirección de Desarrollo Urbano) | **Legal confirmado solo para la capital** | TR-170100-017 | campo libre `autoridad_expide` | `RegistrarDocumentoTest::test_dictamen_rechaza_textos_de_solo_espacios_y_campos_obligatorios_vacios` |
| Factibilidad de giro vigente 1 año | **Legal confirmado**, otro documento | TR-170100-044 | no se captura | (documentado) |
| Un vencimiento declarado y pasado bloquea | Mejora técnica | Consecuencia de la vigencia declarada | `ValidarVigenciaDocumentos` | `ValidarVigenciaDocumentosTest::test_detecta_dictamen_con_fecha_de_vencimiento_declarada_ya_pasada`, `Paso2DocumentosTest::test_bloquea_el_avance_final_si_dictamen_esta_vencido` |
| Vencimiento ≥ emisión; emisión no futura | Mejora técnica | Integridad | form y caso de uso | `RegistrarDocumentoTest::test_dictamen_rechaza_un_vencimiento_anterior_a_la_emision`, `::test_dictamen_rechaza_emision_futura_largos_excedidos_y_tipo_desconocido`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_dictamen_rechaza_un_vencimiento_anterior_a_la_emision` |
| Textos de solo espacios rechazados | Mejora técnica | Instrucción del dueño | `validarTextoCampo` | `RegistrarDocumentoTest::test_dictamen_rechaza_textos_de_solo_espacios_y_campos_obligatorios_vacios`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_dictamen_rechaza_textos_de_solo_espacios` |
| Integridad al reemplazar | Mejora técnica | — | validación antes de escribir | `RegistrarDocumentoTest::test_un_dictamen_invalido_al_reemplazar_conserva_fila_y_archivo_previos`, `::test_dictamen_reemplazado_cambia_la_declaracion_y_limpia_la_fecha` |
| Sin efectos sobre otros documentos | Mejora técnica | — | — | `RegistrarDocumentoTest::test_guardar_un_dictamen_no_modifica_otros_documentos_del_plantel` |
| Antigüedad máxima de 30 días | **Administrativo no confirmado** (decisión operativa del dueño) | COMPENDIO fila 4 | se retira el bloqueo | ver tabla 6 |
| Compatibilidad del uso/giro con la actividad educativa; correspondencia dictamen–domicilio; autenticidad | **Hipótesis pendiente** | — | no se valida por palabras ni por folio; domicilio ya está en `planteles` | (revisión humana o motor futuro) |

## 8. Resumen de compatibilidad

- Esquema: columnas aditivas nullable (o con valor por omisión `no_capturada`); los dictámenes existentes conservan autoridad, folio y giro.
- Datos: se anulan la fecha calculada de los dictámenes (`documentos_plantel.fecha_vigencia`) y `vigencia_max_dias` del dictamen; reversible.
- Código: `RegistrarDocumento` conserva su firma; un DTO sin `tipoVigencia` (llamadores antiguos) guarda `no_capturada`. El resto de los documentos no cambia.
- Interfaz: el bloque del dictamen es el único que cambia (más campos y etiquetas).

## 9. Pendientes y verificaciones manuales

- Preguntas para SEDEQ: ver `PENDIENTE-dictamenes-por-nivel.md` (actualización 2026-10-19). La carpeta "Servicios" de la página de trámites de SEDEQ lista PDF de requisitos (Básica, Media Superior, Superior 2024) que no se pudieron leer.
- Recorrido en navegador (teclado, móvil, mensajes por campo, previsualizar y reemplazar el PDF) con servidor propio del worktree y BD propia.
- Una fila anterior en `no_capturada` no muestra aviso en pantalla (la fila no tiene un espacio de aviso propio); se podría agregar si se quiere pedir la recaptura.
