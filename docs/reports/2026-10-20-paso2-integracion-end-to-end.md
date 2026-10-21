# Refactor integral del Paso 2, motores, geografía e integración SEDEQ

**Fecha:** 2026-10-20 · **Rama:** `claude/gifted-franklin-7il8b2` · **Base:** `master` @ `c9a97cd70dc9fa4d78be687c5612a83b305d0f84`
**Especificación:** `docs/specs/2026-10-20-paso2-integracion-end-to-end.md` · **Decisiones:** `docs/decisions/ADR-018-orden-paso2-niveles-antes-de-documentos.md`, `docs/decisions/PENDIENTE-fuentes-domicilio-municipal.md`

Este informe es el registro de la sesión. `docs/progress.md` no se tocó (CLAUDE.md: lo actualiza el controlador al fusionar, a partir de este informe).

## 1. Estado inicial

### 1.1 Entorno

El contenedor traía PHP 8.3, pero `composer.lock` exige PHP 8.4 (Symfony 8 usa sintaxis de 8.4). Se compiló PHP 8.4.26 desde el repositorio oficial `php/php-src` (con `pdo_pgsql`, `intl`, `gd`, `zip`, `mbstring`) y se usó PostgreSQL 16 local con la base desechable `sedeq_incorporacion_testing` que pide `phpunit.xml`. La descarga del paquete `phpstan/phpstan` desde la API de GitHub estaba bloqueada por la red del entorno; se instaló su mismo commit fijado en el lock (`9ba9ac7`) desde git, con un `composer.local.json/lock` sin versionar (excluido en `.git/info/exclude`). No cambia `composer.json` ni `composer.lock`.

| Verificación base en `c9a97cd` | Resultado |
|---|---|
| Suite completa (`php artisan test`) | **1810/1810** pruebas, 6442 aserciones |
| Pint (`--test`) | sin cambios |
| PHPStan nivel 5 + PHPat | 0 errores |

### 1.2 Ramas remotas

| Rama | Commits exclusivos | Detrás de `master` | Decisión |
|---|---|---|---|
| `hud-revision` | 0 | 43 | Ya integrada; no se fusiona |
| `hud-sedeq-1` | 0 | 46 | Ya integrada; no se fusiona |
| `worktree-numero-oficial-geografia` | 0 | 3 | Ya integrada; no se fusiona |

No hay ramas con trabajo exclusivo. El módulo SEDEQ se auditó y se extendió directamente sobre `master`. No se recuperó código de ramas antiguas ni se hizo merge.

## 2. Matriz de hallazgos

| Componente | Responsabilidad | Hallazgo | Evidencia | Clase | Severidad | Solución |
|---|---|---|---|---|---|---|
| `EstadoPaso2::puedeSeleccionarNiveles` | Precondición de niveles | Exigía documentos completos y vigentes | Código; prueba anterior `EstadoPaso2Test` | Requisito funcional (orden) | Alta | Contratos separados (ADR-018) |
| `CompuertaPaso3`, `Paso24DocumentosNivel::mount` | Entrada a 2.4/Paso 3 | Reutilizaban `puedeSeleccionarNiveles` como «Paso 2 completo»: al cambiar el método, 2.4 se abría con niveles y sin documentos | **Reproducido**: `OrdenPaso2Test::test_con_niveles_y_sin_documentos_generales_las_paginas_del_nivel_mandan_a_documentos` (RED: 200 en 2.4) | Dependencia problemática / bypass latente | Crítica | `generalesSatisfechos` |
| `ResumenTramite::estadoDocumentos` | Estado «Documentos» | Derivado de `etapaFaltante`: documentos completos sin niveles salían «en curso» | **Reproducido**: `ResumenTramiteTest::test_paso2_completo_sin_niveles` (RED: `en_curso`) | Defecto latente | Media | Estado propio por completitud y vigencia |
| `RegistrarDocumento` (generales) | Carga | Aceptaba documentos generales sin niveles | Código | Integración incompleta | Media | Exige un nivel (`EstadoPaso2::NIVELES`) |
| `EvaluarAplicabilidadDocumentos::domiciliosMunicipales` | Evidencia de domicilio | Método protegido vacío, sin procedencia ni explicación | Código | Integración incompleta | Media | `FuentesDomicilioMunicipal` con matriz |
| `acreditaciones_ocupacion_legal.vigencia_contrato` | Vigencia del contrato | Se capturaba y ningún consumidor la leía | `grep` sin consumidores | Integración incompleta | Media | Observación (no bloquea) |
| `ValidarVigenciaDocumentos::avisos` | Observaciones | Solo llegaban a la pantalla de documentos; no al reporte ni a SEDEQ | Código | Integración incompleta | Media | Filas `advertencia` en la validación final |
| `planteles.municipio` | Municipio | Texto libre; ADR-008 pedía FK y no se hizo | ADR-008 «Actualización» | Requisito pendiente | Media | `municipio_id` + selector + vinculación |
| `ConsultarTramiteSedeq` | Proyección SEDEQ | Sin metadatos, sin turno/alumnado/matrícula, sin resultado guardado | Código y vista | Integración incompleta | Alta | Proyección ampliada |
| `EscuelaPolicy::verReporte` | Reporte PDF | SEDEQ no podía ver el reporte enviado | Código | Integración incompleta | Media | `verReporteEnviado` acotado |
| `2026_01_02_000002_add_fisica_con_gestor_to_tipos_documentos_check::down` | Reversibilidad | `migrate:reset` con datos sembrados falla (filas `fisica_con_gestor` violan el CHECK anterior) | Probado en base desechable | Riesgo (preexistente) | Baja | **No se modificó** (migración aplicada); ver §9 |

Fixtures de prueba: `CompletaPaso2::completarPaso2` sube los documentos con un nivel temporal y lo quita (estado histórico «documentos sin niveles»); el método nuevo `elegirNivel` elige un nivel sin duplicarlo. Se ajustaron 30+ archivos de prueba que subían documentos sin nivel; cada cambio de expectativa responde al cambio legítimo de secuencia (se documenta en la prueba), no a ocultar una regresión. Una prueba (`EnviarTramiteTest::test_sin_niveles_no_se_envia`) había perdido su intención con el fixture nuevo y se corrigió para que vuelva a no tener niveles.

## 3. Arquitectura

### 3.1 Flujo

- **Anterior:** Prerregistro → Responsable → Documentos generales → Niveles → Documentos por nivel → Paso 3 → Validación final → Envío.
- **Nuevo:** Prerregistro → **2.1 Responsable** → **2.2 Niveles** → **2.3 Documentos generales** → **2.4 Documentos por nivel** → Paso 3 → Validación final → Envío. Rutas sin cambio.

### 3.2 Contratos y compuertas

| Contrato (`EstadoPaso2`) | Condición | Consumidores |
|---|---|---|
| `puedeSeleccionarNiveles` | responsable | `RegistrarNivelesSeleccionados`, `Paso2Responsable` |
| `puedeCapturarDocumentosGenerales` | responsable + nivel | `RegistrarDocumento`, `Paso2Documentos::mount` |
| `generalesSatisfechos` | responsable + nivel + documentos completos y vigentes | `Paso24DocumentosNivel::mount`, `CompuertaPaso3` |
| `etapaFaltante` | `responsable` → `niveles` → `documentos` → `null` | `RegistrarDatosNivel`, `RegistrarDocumento` (nivel), `MarcarPasoCompletado`, `RegistrarDatosInmueble`, `RegistrarInfraestructuraNivel`, `RegistrarMobiliarioNivel`, `CompuertaSubPaso3`, `ResumenTramite`, `RedirigeAPaso2` |

`RedirigeAPaso2` manda al dueño a la etapa pendiente exacta (niveles o documentos), no siempre a `/tramite/paso2`. El envío no cambió de diseño: bloquea, revalida dentro de la transacción y no confía en evaluaciones anteriores.

### 3.3 Navegación

`Paso2Responsable`: alta del responsable → fase niveles (sin redirigir); guardar niveles → documentos generales si faltan, si no, resumen; sin responsable, una llamada forjada a `guardarNiveles()` no escribe. `Paso2Documentos`: sin niveles redirige a `?seccion=niveles`; al completar vuelve al resumen. Resumen: Plantel, Responsable, Niveles, **Documentos generales** (antes «Documentos»), y por nivel sus secciones; «Paso X de N» derivado del orden.

### 3.4 Compatibilidad histórica (probada)

| Estado | Comportamiento | Prueba |
|---|---|---|
| Responsable sin documentos ni niveles | Abre niveles | `OrdenPaso2Test::test_un_responsable_sin_niveles_abre_la_eleccion_de_niveles` |
| Documentos completos sin niveles | Pide niveles; documentos «completado» y conservados, sin duplicar | `OrdenPaso2Test::test_historico_con_documentos_sin_niveles_abre_la_eleccion_de_niveles`, `ResumenTramiteTest::test_paso2_completo_sin_niveles`, `EstadoPaso2Test::test_historico_documentos_completos_sin_niveles_pide_los_niveles` |
| Niveles y datos ya capturados | Sin cambio; quitar un nivel no toca documentos generales ni pasos de otros niveles | `CoherenciaEntreConsumidoresTest::test_quitar_un_nivel_no_borra_los_documentos_generales_ni_reinicia_los_demas_niveles` |
| Expediente enviado | Congelado | `ExpedienteEnviadoARevisionTest::test_tras_el_envio_el_solicitante_ya_no_puede_modificar_niveles_ni_documentos` |

## 4. Motores

### 4.1 Regla → Fuente → Evaluador → Consumidor → Efecto → Prueba

| Regla | Fundamento | Fuente de datos | Evaluador | Consumidor | Efecto | Prueba |
|---|---|---|---|---|---|---|
| Documentos requeridos presentes | Catálogo `tipos_documentos` (Compendio §4) | `documentos_escuela`, `documentos_plantel` | `DocumentosCompletos` / `DocumentosRequeridosPresentes` | Checklist, compuertas, resumen, validación final, envío | Bloquea | `CoherenciaEntreConsumidoresTest` (3 estados de aplicabilidad) |
| Aplicabilidad del Certificado de Número Oficial | Documentado, no contrastado; alcance Pendiente de SEDEQ | Niveles, plantel, `FuentesDomicilioMunicipal` (vacía) | `EvaluarAplicabilidadDocumentos` | `DocumentosCompletos::clavesRequeridas` (todos los consumidores) | Obligatorio por defecto; nunca autoexención | `EvaluarAplicabilidadDocumentosTest` (+3), `FuentesDomicilioMunicipalTest` |
| Nombre/CURP de identidad | Técnica (coherencia documental) | Responsable + `credenciales_ine`, `constancias_curp` | `NombreCoincide`, `IdentificadorCoincide` | Validación final, envío | Bloquea si no cumple | Suite existente (`EjecutarValidacionDocumentalTest`) |
| Nombre/RFC fiscal | Técnica | `constancias_situacion_fiscal` | `NombreCoincide`, `IdentificadorCoincide` | Validación final, envío | Bloquea | Suite existente |
| Domicilio del plantel vs certificado | Técnica | `planteles`, `certificados_numero_oficial` | `DomicilioCoincide` | Validación final | Según motor (sin cambio) | Suite existente |
| Vigencia del Dictamen de Uso de Suelo | Cédula TR-170100-017 («la que indique el documento») | `dictamenes_uso_suelo` | `ValidarVigenciaDocumentos::ejecutar` | Compuerta 2.4/Paso 3, resumen, envío | Bloquea solo con fecha expresa pasada | `CoherenciaEntreConsumidoresTest::test_un_documento_que_vence_despues_de_validar_impide_enviar` |
| Vigencia de Protección Civil | Reglamento PC Mpio. Qro. 2017 art. 46 | `vistos_buenos_proteccion_civil` | `ValidarVigenciaDocumentos` | Igual | Bloquea con fecha (documento o normativa) pasada; observaciones no bloquean | Suite existente + avisos en reporte |
| Registro del profesional vencido antes de la emisión | Observación (no hay norma que lo invalide) | `constancias_seguridad_estructural` | `ValidarVigenciaDocumentos::avisos` | **Ahora también** reporte final y SEDEQ | Advertencia | `AvisosEnValidacionFinalTest` |
| Contrato con vigencia declarada pasada | **Nueva observación**; vigencia mínima normativamente sin resolver | `acreditaciones_ocupacion_legal.vigencia_contrato` | `ValidarVigenciaDocumentos::avisos` | Reporte final, SEDEQ | Advertencia, nunca bloquea | `AvisosEnValidacionFinalTest` |
| Recibo no reutilizado, acervo, inventario/laboratorio | Técnica / Compendio | Tablas por nivel | Reglas por nivel | Validación final, envío | Bloquea si no cumple | Suite existente |
| Capacidad instalada | Acuerdos secretariales (reglas_validacion) | Paso 3 | `EjecutarValidacionCapacidad` | Validación final, envío | Bloquea `no_cumple`/falta de captura | `EnviarTramiteTest` |

Reglas pendientes de SEDEQ que **no** se convirtieron en restricciones: alcance del certificado, vigencia mínima del contrato, obligatoriedad de ratificación, dictámenes por nivel. Las reglas retiradas en refactors anteriores (30 días de PC, año del registro del perito, caducidad arbitraria del dictamen) **no** se reintrodujeron.

### 4.2 Ejecución por etapas

Captura: formato e integridad en `RegistrarDocumento`. Cambio de niveles: la aplicabilidad y la completitud se derivan en cada lectura (no hay caché). Avance: compuertas con `EstadoPaso2`/`EstadoPaso24`. Validación final: motor documental, por nivel, capacidad y, desde ahora, observaciones de vigencia. Envío: reevaluación completa dentro de la transacción. Los renders de Livewire no ejecutan el motor documental.

## 5. Fuentes municipales de domicilio

Matriz verificada contra migraciones y modelos (`FuentesDomicilioMunicipal::disponibilidad`):

| Documento | Domicilio estructurado | Autoridad | Jurisdicción |
|---|---|---|---|
| Dictamen de Uso de Suelo | No | `autoridad_expide` | No |
| Acreditación de ocupación | No (solo notaría) | — | No |
| Seguridad Estructural | No | `perito_registro_autoridad` | No |
| Protección Civil | No | `autoridad_emisora` | `jurisdiccion_emisora` |
| Número Oficial | Sí — **excluido** (no prueba su propia exención) | `autoridad_emisora` | — |

Resultado: no hay evidencia independiente; la aplicabilidad se mantiene conservadora. No se leyeron PDF ni se usó OCR/LLM. Pendiente en `PENDIENTE-fuentes-domicilio-municipal.md`.

## 6. Integración SEDEQ

- **Encontrado en `master`:** `AccesoSedeq` (supervisor/revisor/asignaciones, sin niveles en captura), `ConsultarTramiteSedeq`, `TramiteLectura`, `ColaRevisor`, `TableroSupervisor`, Policies `view` para descargas.
- **Agregado:** `MetadatosDocumentales` (lectura fiel de las tablas de extensión; NULL histórico = «No capturado»; vigencia con estado semántico); proyección con municipio del catálogo, responsable completo (RFC, CURP, gestor, escritura constitutiva), terna, metadatos por documento, datos por nivel (turno, alumnado, modalidad, alumnos, personal, avance de Paso 3) y el **resultado guardado al envío** (rotulado así, filtrado a los niveles visibles, sin reevaluar ni escribir; verificado con `DB::listen`).
- **Reporte PDF para SEDEQ:** `EscuelaPolicy::verReporteEnviado` + ruta `sedeq.tramite.reporte`. Como el PDF cubre todos los niveles, solo lo descarga el supervisor o un revisor asignado a todos los niveles.
- **Seguridad verificada** (`ExpedienteEnviadoARevisionTest`, 8 pruebas): revisor completo lee y descarga; revisor de un nivel no ve el otro nivel, su resultado ni el PDF completo, y no descarga documentos del otro nivel; sí descarga documentos generales (decisión vigente: datos de la escuela compartidos por sus niveles); revisor sin asignación y solicitante reciben 403; un expediente en captura no es visible aunque exista una asignación; tras el envío el solicitante no puede escribir.

### Trazabilidad

| Dato | Captura | Persistencia | Validación | Envío | Vista SEDEQ |
|---|---|---|---|---|---|
| Responsable | `Paso2Responsable` | `responsables_legales` + subtipos | Identidad/fiscal | Revalidado | Visible (ampliado) |
| Niveles | `Paso2Responsable` (fase niveles) | `escuela_niveles` | Compuertas | Cambia a `en_revision` | Visible por permiso |
| Documentos | `Paso2Documentos`, `Paso24DocumentosNivel` | `documentos_*` | Completitud, vigencia | Revalidado | Visible + descarga |
| Metadatos | Formularios | Tablas de extensión | Evaluadores/observaciones | Incluidos | **Visible (nuevo)** |
| Capacidad | Paso 3 | Tablas de Paso 3 | Motor de capacidad | Revalidado | **Resultado guardado (nuevo)** |
| Resultado | Motor | `evaluaciones_validacion` | — | Guardado con el envío | **Visible (nuevo)** |

## 7. Geografía

- Catálogo reutilizado: `entidades_federativas`/`municipios` (ADR-017), fuente `api_info_mexico`. **No** se atribuye a INEGI; no se contrastó con una fuente oficial (el entorno no tiene acceso a sitios del INEGI) — riesgo vigente de ADR-017.
- Campo convertido: `planteles.municipio_id` (migración `2026_10_20_000500`, FK nullable `ON DELETE SET NULL`, índice). El texto `municipio` se conserva y se deriva del catálogo cuando llega un id. Paso 1 muestra un selector solo de Querétaro (clave 22) si el catálogo está importado; si no, el campo de texto. `IniciarTramiteNuevo` valida que el municipio pertenezca a Querétaro.
- Backfill: **no destructivo y no automático**. `php artisan geografia:vincular-municipios` simula por omisión; con `--aplicar` vincula solo nombres normalizados inequívocos (planteles dentro de Querétaro; certificados dentro de su entidad, o de todo el catálogo si no la tienen), nunca sobrescribe texto ni ids y reporta ambiguos y sin correspondencia («Qro.», «SJR» no se adivinan).
- No convertidos (a propósito): `acreditaciones_ocupacion_legal.notario_localidad` (localidad, histórico), nombres de autoridades.
- `ComparadorDomicilios` compara el municipio por id cuando ambas fuentes lo traen.
- Migración probada en base desechable: `migrate`, `rollback --step=1`, `migrate` de nuevo.

## 8. Calidad

| Verificación | Resultado en la rama |
|---|---|
| Suite completa | **1884/1884** (base 1810; +74 pruebas nuevas o divididas) |
| Pint | limpio |
| PHPStan nivel 5 + PHPat | 0 errores |
| Migraciones | `migrate`/`rollback`/`migrate` en PostgreSQL 16 desechable |

Pruebas nuevas principales: `EstadoPaso2Test` (reescrita, 7), `OrdenPaso2Test` (17), `CoherenciaEntreConsumidoresTest` (7), `AvisosEnValidacionFinalTest` (4), `FuentesDomicilioMunicipalTest` (2), `EvaluarAplicabilidadDocumentosTest` (+3), `MunicipioDelPlantelTest` (6), `VincularMunicipiosHistoricosTest` (7), `Paso1MunicipioCatalogoTest` (4), `ComparadorDomiciliosTest` (+4), `MetadatosDocumentalesTest` (7), `ExpedienteEnviadoARevisionTest` (8).

TDD: contratos, orden, resumen, aplicabilidad, observaciones, geografía y la proyección SEDEQ se escribieron en rojo primero y se vieron fallar por la razón esperada. `MetadatosDocumentalesTest` se escribió junto con su clase (no antes); `CoherenciaEntreConsumidoresTest` pasó al primer intento: caracteriza que las fuentes únicas ya coinciden (no requirió cambio de código).

## 9. Riesgos y pendientes

- **Entorno:** PostgreSQL 16 (el proyecto declara 18); PHP 8.4 compilado localmente. CI de GitHub sigue siendo la verificación independiente.
- **`migrate:reset` con datos:** la `down()` de `2026_01_02_000002` falla si hay filas `fisica_con_gestor` (preexistente; no se editó una migración aplicada). Una corrección sería una migración nueva cuyo `down()` limpie esas filas, decisión del dueño.
- **Fuentes de domicilio:** `PENDIENTE-fuentes-domicilio-municipal.md`.
- **Procedencia del catálogo geográfico:** sin contraste oficial (ADR-017).
- **Escenarios no automatizados:** concurrencia real entre dos conexiones (las pruebas existentes verifican el orden de bloqueos, no carreras reales); navegador real.
- **Fuera de alcance, registrado:** captura de domicilio en documentos municipales; flujo de «discrepancia confirmada» por SEDEQ; edición de Paso 3 hasta el envío (WS-7b-2).

## 10. GitHub

Rama `claude/gifted-franklin-7il8b2`, commits:

1. `docs(spec)` especificación SDD.
2. `feat(paso2)` orden y contratos.
3. `feat(motores)` fuentes de domicilio y observaciones en el reporte.
4. `feat(geografia)` municipio del plantel y vinculación.
5. `feat(sedeq)` proyección fiel y reporte enviado.
6. `docs` ADR-018, PENDIENTE e informe.

Pasos de integración tras fusionar: `php artisan migrate`; si el catálogo no está importado, `php artisan geografia:importar`; después `php artisan geografia:vincular-municipios` (revisar la simulación) y, si es correcta, `--aplicar`. El controlador actualiza `docs/progress.md` desde este informe.
