# Reporte: acreditación de ocupación legal del inmueble (Paso 2.2)

**Fecha:** 2026-10-19 · **Rama:** `worktree-acreditacion-ocupacion` (worktree `.claude/worktrees/acreditacion-ocupacion`) · **Estado:** implementado y probado en la rama; **sin push, sin merge, migración sin aplicar a la BD de desarrollo**.
**Especificación:** `docs/superpowers/specs/2026-10-19-acreditacion-ocupacion-design.md` · **Plan:** `docs/superpowers/plans/2026-10-19-acreditacion-ocupacion.md` (ambos locales, ignorados por git).

## 1. Qué cambió

| Tarea | Commit | Resultado |
|---|---|---|
| 1 | `a57101b` | Migración aditiva `2026_10_19_000100`: 13 columnas nullable en `acreditaciones_ocupacion_legal`; modelo con `$fillable` y casts |
| 2 | `6f008e2` | `DatosDocumento` (13 parámetros); `RegistrarDocumento::validarAcreditacion()` y `columnasAcreditacion()`: valida por variante y **escribe NULL en toda columna ajena a la variante**, aunque el llamador evite Livewire |
| 3 | `ccbf7f6` | `AcreditacionOcupacionForm`: reglas condicionales por variante y `datosParaTipo()` |
| 4 | `cbdce05` | `guardarAcreditacion()` usa `datosParaTipo()` (se eliminan 15 líneas de coerción) |
| 5 | `89a78e7` | Vista: campos, etiquetas y ayudas por variante |
| 6 | `344f9ca`, `4eef244` | Pruebas de regresión; corrección de un fixture del test de auditoría |
| 7 | (este) | Revisión de decisiones pendientes, PENDIENTE, informe, Compendio |

Se mantuvo: `ambito = plantel`, clave primaria `documento_plantel_id`, PDF obligatorio de 10 MB, almacenamiento privado, `TramiteEditable`, autorización por ruta (`can:update`), `DocumentosCompletos` y `ValidarVigenciaDocumentos` sin cambios.

## 2. Verificación (ejecutada)

- Línea base antes de cambiar: 1493 pruebas verdes, Pint limpio, PHPStan 0 errores.
- Última corrida completa en la rama: **1537 de 1538 pruebas pasaron**; Pint limpio; PHPStan 0 errores. La única falla (`AuditoriaSeguridadTest::test_solicitante_b_no_puede_descargar_documentos_de_plantel_de_a`) usaba un DTO incompleto que la nueva validación rechaza correctamente; se corrigió el fixture (`4eef244`) y esa clase más `DocumentoDownloadTest` pasan (17 de 17). **No se repitió la corrida completa después de esa corrección.**
- Migración: reversible, comprobada solo en la BD de **pruebas** (30 → 17 → 30 columnas). **No se ejecutó en la BD de desarrollo ni en una compartida.**
- La corrida completa necesita `php -d memory_limit=1G` (el límite por omisión de 128 MB mata PHP) y `public/build` copiado del checkout principal (manifest de Vite); ambos ajustes son del entorno del worktree, no del código.

## 3. Desviaciones del proceso (con honestidad)

- Las pruebas de **nivel de componente** de las tareas 4 y 5 se escribieron después del cambio de código; la protección contra datos residuales sí se vio fallar primero en `RegistrarDocumentoTest` (llamador que evita Livewire).
- La prueba de autorización por componente que escribí era incorrecta (`Livewire::test` no pasa por el middleware de ruta); se eliminó. La autorización queda cubierta por `Paso2DocumentosTest::test_un_no_dueno_recibe_403`, a nivel de ruta.
- El Compendio se editó primero en el checkout principal y se copió al worktree; el checkout principal conserva la misma edición sin commitear y chocará al hacer merge hasta que se descarte allí.
- No se actualizó el DDL de referencia: `docs/ddl_sistema_incorporacion_v3.sql` está ignorado por git (ya estaba desactualizado desde 2026-10-12).
- Queda sin hacer, a propósito: revisión visual y de teclado/móvil en navegador (requiere servidor del worktree con su propia BD y autorización).

## 4. Tabla de decisiones pendientes

| Decisión pendiente | Estado previo | Impacto de la implementación | Estado final | Evidencia |
|---|---|---|---|---|
| Ratificación notarial (`ratificado_notario`) con acreditación compartida por plantel | Abierta; casilla solo en comodato, sin datos ni regla | Se captura para arrendamiento y comodato con fecha, notario y número; **nunca se exige**; NULL si no está ratificado | **Parcialmente resuelta**: captura resuelta; obligatoriedad por nivel **normativamente sin resolver** | `RegistrarDocumentoTest::test_ratificacion_se_conserva_en_arrendamiento_y_comodato`, `::test_los_datos_de_ratificacion_se_anulan_si_no_esta_ratificado`, `::test_la_ratificacion_no_es_obligatoria`; `Paso2DocumentosTest::test_arrendamiento_guarda_ratificacion_y_facultades_del_arrendador`; PENDIENTE actualizado |
| Dictámenes (Protección Civil / Uso de suelo) por nivel | Abierta | Ninguno (fuera de alcance) | Sin cambio | `PENDIENTE-dictamenes-por-nivel.md` |
| Certificado de número oficial condicional | Abierta | Ninguno (fuera de alcance) | Sin cambio | idem |
| La acreditación se conserva a nivel plantel | Provisional | Se conserva `documentos_plantel`; un instrumento cubre varios niveles; sin reglas por nivel en captura | **Decidida arquitectónicamente** (no es confirmación normativa) | `RegistrarDocumentoTest::test_la_acreditacion_es_compartida_por_las_escuelas_del_plantel`; `GuardaTramiteEnviadoTest::test_la_acreditacion_de_ocupacion_no_se_reemplaza_si_otra_escuela_del_plantel_ya_se_envio` |
| Contratos de vigencia indeterminada | No se podían capturar | Casilla `vigencia_indeterminada`; fin no requerido y guardado NULL | Captura **técnicamente resuelta**; admisibilidad **normativamente sin resolver** | `RegistrarDocumentoTest::test_vigencia_indeterminada_guarda_fin_nulo_aunque_se_envie_una_fecha`, `::test_vigencia_indeterminada_sin_marcar_exige_fin`; `Paso2DocumentosTest::test_comodato_con_vigencia_indeterminada_guarda_fin_nulo` |
| Uso autorizado / derecho de ocupación | Opcional | `uso_autorizado` obligatorio en arrendamiento, comodato y otro; en otro, además `otro_derecho`; sin exigir cláusula de uso educativo en otro | **Técnicamente resuelta** como completitud de datos; suficiencia jurídica a cargo de SEDEQ | `::test_contrato_exige_inicio_fin_y_uso_autorizado`, `::test_otro_exige_metadatos_del_instrumento_derecho_y_uso`, `::test_otro_persiste_metadatos_sin_exigir_leyenda_de_uso_educativo` |
| Datos residuales al cambiar de tipo | Defecto no documentado | `RegistrarDocumento` anula las columnas ajenas a la variante; el formulario envía solo la variante | **Técnicamente resuelta** | `RegistrarDocumentoTest::test_arrendamiento_persiste_el_contrato_y_anula_campos_de_otras_variantes`, `::test_escritura_anula_campos_de_contrato_y_de_otro_enviados_por_un_caller`, `::test_reemplazar_la_acreditacion_por_otra_variante_deja_nulos_los_campos_anteriores`; `Paso2DocumentosTest::test_cambiar_de_tipo_antes_de_guardar_no_persiste_la_variante_anterior` |
| Vigencia mínima de contrato (ciclo escolar) | Solo en Compendio (Básica) | **No se impone** | **Normativamente sin resolver** | Ausencia deliberada de regla; ver PENDIENTE |
| Obligatoriedad de datos RPP; suficiencia de "otro"; aplicabilidad estatal de los Acuerdos 450 y 17/11/17; documento de facultades aceptado | No documentadas | Datos RPP opcionales; "otro" se captura y se revisa a mano; facultades solo como referencia | **Nuevas preguntas abiertas para SEDEQ** | Sección 6 de este informe y PENDIENTE |
| Edición hasta el envío (`PENDIENTE-edicion-hasta-envio.md`) | Abierta (WS-7b-2) | Ninguno; solo regresión | Sin cambio | `GuardaTramiteEnviadoTest` (fila nueva del proveedor y prueba específica) |
| Cardinalidad plantel–solicitante (ADR-009) | Resuelta | Ninguno | Sin cambio | ADR-009 |

## 5. Matriz de trazabilidad: norma → campo → validación → prueba

Certeza: **C** confirmada en texto leído; **D** documentada, no contrastada; **O** decisión del dueño, sin fuente normativa; **I** integridad técnica.

| Norma / origen | Cert. | Campo | Validación | Prueba |
|---|---|---|---|---|
| Ac. 450 art. 23 fr. I; Ac. 17/11/17 art. 23 inc. a (escritura a nombre del particular) | C | `propietario_nombre` | requerido en escritura | `RegistrarDocumentoTest::test_escritura_rechaza_datos_esenciales_faltantes`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_escritura_exige_propietario` |
| Ac. 450 fr. I (inscripción en RPP) | C (solo media sup.) | `folio_rpp`, `fecha_inscripcion_rpp` | **opcionales**, fecha no futura | `::test_escritura_persiste_propietario_y_datos_registrales`, `::test_rechaza_fechas_futuras` |
| Requisitos Básica (número y fecha de escritura, notario) | D | `numero_escritura`, `fecha_escritura`, `notario_*` | requeridos número, fecha y notario; número de notaría y municipio opcionales | `::test_escritura_rechaza_datos_esenciales_faltantes`; `Paso2DocumentosTest::test_acreditacion_cada_variante_muestra_solo_sus_campos` (etiquetas) |
| Ac. 450 fr. II–III (ratificación) | C (solo media sup.) | `ratificado_notario`, `ratificacion_*` | se captura; **no se exige** | `::test_ratificacion_se_conserva_en_arrendamiento_y_comodato`, `::test_la_ratificacion_no_es_obligatoria`, `::test_los_datos_de_ratificacion_se_anulan_si_no_esta_ratificado` |
| Ac. 450 último párrafo; Compendio (uso educativo) | C | `uso_autorizado` | requerido en arrendamiento, comodato y otro (sin exigir leyenda) | `::test_contrato_exige_inicio_fin_y_uso_autorizado`, `::test_otro_persiste_metadatos_sin_exigir_leyenda_de_uso_educativo` |
| Ac. 450 fr. IV (datos del instrumento, fecha, vigencia, autoridad) | C | `otro_*` | requeridos denominación, referencia, titular, expedición, autoridad, derecho | `::test_otro_exige_metadatos_del_instrumento_derecho_y_uso`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_otro_exige_instrumento_derecho_y_uso` |
| Fecha de firma y vigencia (Compendio) | D | `fecha_contrato`, `fecha_inicio_vigencia`, `vigencia_contrato` | celebración no futura; fin ≥ inicio y ≥ celebración | `::test_el_fin_de_vigencia_no_puede_ser_anterior_al_inicio_ni_a_la_celebracion`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_la_vigencia_no_es_anterior_al_inicio` |
| Decisión del dueño (vigencia indeterminada) | O | `vigencia_indeterminada` | fin no requerido y NULL | `::test_vigencia_indeterminada_guarda_fin_nulo_aunque_se_envie_una_fecha`; `ValidacionFormulariosTramiteTest::test_paso2_documentos_la_vigencia_indeterminada_no_exige_fecha_de_fin` |
| Decisión del dueño (facultades) | O | `arrendador_es_propietario`, `facultades_referencia` | referencia requerida si no es propietario; NULL si lo es | `::test_arrendador_que_no_es_propietario_exige_referencia_de_facultades`, `::test_arrendador_propietario_anula_la_referencia_de_facultades` |
| Integridad de fechas | I | todas las fechas | formato `Y-m-d`, no futuras salvo vigencias | `::test_rechaza_fechas_futuras`, `::test_rechaza_valores_que_exceden_la_columna_o_fechas_mal_formadas` |
| Integridad de variante | I | todas | columnas ajenas = NULL; tipo inválido rechazado | `::test_tipo_invalido_se_rechaza_antes_de_escribir`, y las de la tabla 4 |
| Sin pérdida silenciosa al reemplazar | I | fila y PDF | validación antes de escribir | `::test_una_validacion_fallida_al_reemplazar_conserva_fila_y_archivo_previos`, `::test_reemplazar_con_la_misma_variante_actualiza_los_datos_en_la_misma_fila` |
| Compatibilidad con filas anteriores | I | columnas nuevas | NULL permitido; cuenta como capturada | `AcreditacionOcupacionLegalTest::test_persiste_columnas_nuevas_y_las_deja_nulas_en_filas_anteriores`; `RegistrarDocumentoTest::test_una_acreditacion_anterior_a_la_migracion_cuenta_como_capturada_y_se_puede_reemplazar` |
| Trámite enviado no editable | I | documento de plantel | `TramiteEditable` | `GuardaTramiteEnviadoTest::test_la_acreditacion_de_ocupacion_no_se_reemplaza_si_otra_escuela_del_plantel_ya_se_envio` y fila del proveedor |
| Los campos de otra variante no se validan ni se envían | I | formulario | reglas por variante; `datosParaTipo()` | `ValidacionFormulariosTramiteTest::test_paso2_documentos_los_campos_de_otra_variante_no_se_validan`, `::test_paso2_documentos_el_payload_incluye_solo_la_variante_elegida` |

## 6. Decisiones jurídicas que siguen sin resolverse (no se supusieron)

1. Ratificación (arrendamiento y comodato) por nivel, con acreditación compartida por plantel.
2. Admisibilidad de contratos sin fecha de terminación y condiciones.
3. Vigencia mínima por nivel.
4. Obligatoriedad de datos del RPP para Básica e Inicial.
5. Qué otros instrumentos se aceptan y con qué suficiencia.
6. Aplicabilidad de los Acuerdos 450 y 17/11/17 al ámbito estatal de SEDEQ (por delegación o norma propia) y vigencia actual del Acuerdo 450.
7. Documento de facultades aceptable cuando el arrendador o comodante no es el propietario.

**Fuentes no verificadas:** guía DGB 2025 (PDF de imágenes, sin texto extraíble); página de trámites de SEDEQ (sin documentos publicados en la vista consultada); texto consolidado vigente del Acuerdo 17/11/17 y de sus transitorios más allá de las reformas al art. 22.

## 7. Verificaciones manuales pendientes

- Recorrido en navegador con servidor propio del worktree y BD propia (no la de `:8000`): cambio de tipo, casillas que muestran/ocultan campos, mensajes de error por campo, toasts.
- Teclado y lector de pantalla en las casillas dinámicas; ancho móvil.
- Previsualizar, descargar y reemplazar el PDF de la acreditación.
- Con autorización: aplicar la migración a la BD de desarrollo y revisar una fila previa.

## 8. Decisión pendiente del dueño

¿Se redacta un ADR para "`RegistrarDocumento` es la autoridad de las invariantes por variante (anula columnas ajenas)"? Por ahora queda documentado aquí y en la especificación; no se escribió ADR para no inflar el registro sin confirmación.
