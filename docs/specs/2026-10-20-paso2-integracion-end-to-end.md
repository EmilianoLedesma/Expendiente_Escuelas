# Especificación SDD — Paso 2 reordenado, motores integrados, geografía y lectura SEDEQ

**Fecha:** 2026-10-20 · **Base:** `master` @ `c9a97cd` · **Rama:** `claude/gifted-franklin-7il8b2`
**Estado:** implementada en la rama (ver `docs/reports/2026-10-20-paso2-integracion-end-to-end.md` y `docs/decisions/ADR-018-orden-paso2-niveles-antes-de-documentos.md`).

Esta especificación es versionada a propósito: `docs/superpowers/` está en `.gitignore` y no viaja con el repositorio.

## 1. Diagnóstico inicial

| Componente | Responsabilidad | Defecto o riesgo | Evidencia | Clase | Severidad | Solución |
|---|---|---|---|---|---|---|
| `EstadoPaso2::puedeSeleccionarNiveles()` | Precondición de niveles | Exige documentos completos y vigentes: los niveles no pueden elegirse antes que los documentos | Código: devuelve `etapaFaltante() === null` | Requisito funcional (orden) | Alta | Contratos separados (§4) |
| `CompuertaPaso3`, `Paso24DocumentosNivel::mount()` | Entrada a 2.4 / Paso 3 | Reutilizan `puedeSeleccionarNiveles()` con el sentido «Paso 2 completo»; al cambiar ese método abrirían un bypass | Código | Dependencia arquitectónica problemática | Alta | Pasan a `generalesSatisfechos()` |
| `RegistrarNivelesSeleccionados` | Alta de niveles | Rechaza agregar sin documentos completos | Prueba `RegistrarNivelesSeleccionadosTest` | Requisito funcional | Alta | Solo exige responsable |
| `RegistrarDocumento` (ámbito escuela/plantel) | Carga de documentos generales | Acepta documentos sin niveles: la aplicabilidad (Número Oficial) depende de los niveles | Código | Integración incompleta | Media | Exige al menos un nivel |
| `ResumenTramite::estadoDocumentos()` | Estado de «Documentos» | Deriva el estado de `etapaFaltante()`; con el nuevo orden un expediente sin niveles y documentos completos se vería «en curso» | Código | Defecto latente (se activa con el nuevo orden) | Media | Estado propio por completitud + vigencia |
| `EvaluarAplicabilidadDocumentos::domiciliosMunicipales()` | Fuentes de evidencia de domicilio | Método protegido que devuelve `[]` sin explicar por qué ni con qué procedencia | Código | Integración incompleta | Media | Servicio `FuentesDomicilioMunicipal` explícito (§6) |
| `planteles.municipio` | Municipio del plantel | Texto libre; ADR-008 decidió FK al catálogo y quedó sin hacer | ADR-008 «Actualización» | Requisito pendiente | Media | FK nullable + selector + vinculación (§8) |
| `ConsultarTramiteSedeq` | Proyección SEDEQ | Sin metadatos documentales, sin turno/alumnado, sin datos de Paso 3, sin resultado de validación guardado | Código y vista | Integración incompleta | Alta | Proyección ampliada (§7) |
| `EscuelaPolicy::verReporte` | Reporte PDF | Solo dueño; SEDEQ no puede ver el reporte con que se envió | Código | Integración incompleta | Media | Habilidad nueva `verReporteEnviado`, acotada (§7.3) |
| `ComparadorDomicilios` | Comparación | Compara municipio solo por texto aun cuando ambas fuentes tienen clave del catálogo | Código | Mejora | Baja | Compara por id cuando ambas lo traen |

No se encontraron fallas reproducidas en la suite base: **1810/1810 pruebas pasan**, Pint y PHPStan limpios en `c9a97cd`.

## 2. Comparación de ramas

| Rama remota | Commits exclusivos frente a `master` | Detrás de `master` | Decisión |
|---|---|---|---|
| `hud-revision` | 0 | 43 | Ya integrada. No se fusiona. |
| `hud-sedeq-1` | 0 | 46 | Ya integrada. No se fusiona. |
| `worktree-numero-oficial-geografia` | 0 | 3 | Ya integrada. No se fusiona. |

El módulo SEDEQ se audita directamente en `master`.

## 3. Flujo

**Anterior:** Prerregistro → Responsable → Documentos generales → Niveles → Documentos por nivel → Paso 3 → Validación final → Envío.

**Nuevo:**

| Etiqueta funcional | Ruta (sin cambio) | Sección en el resumen |
|---|---|---|
| Paso 1 — Prerregistro y plantel | `tramite.preregistro`, `tramite.plantel` | `plantel` |
| Paso 2.1 — Responsable legal | `tramite.paso2?seccion=responsable` | `responsable` |
| Paso 2.2 — Niveles educativos | `tramite.paso2?seccion=niveles` | `niveles` |
| Paso 2.3 — Documentos generales | `tramite.paso2-documentos` | `documentos` |
| Paso 2.4 — Documentos por nivel | `tramite.paso2-nivel-documentos` | `documentos_nivel` (por nivel) |
| Paso 3 — Académico e infraestructura | `tramite.paso3-*` | sub-pasos por nivel |
| Validación final | `tramite.validacion` | — |
| Envío | acción en `tramite.validacion` | — |

Las rutas se conservan: renombrarlas no aporta valor y rompería enlaces guardados.

### Máquina de estados (derivada, no almacenada)

```
SIN_RESPONSABLE --registrar responsable--> SIN_NIVELES
SIN_NIVELES --agregar niveles--> DOCUMENTOS_PENDIENTES
DOCUMENTOS_PENDIENTES --documentos aplicables completos y vigentes--> GENERALES_SATISFECHOS
GENERALES_SATISFECHOS --(por nivel) 2.4 completo--> PASO3_DISPONIBLE(nivel)
PASO3_DISPONIBLE --sub-pasos en orden--> NIVEL_CAPTURADO
todos NIVEL_CAPTURADO --validación final sin bloqueantes--> LISTO_PARA_ENVIO
LISTO_PARA_ENVIO --EnviarTramite (revalida en transacción)--> EN_REVISION (inmutable para el solicitante)
```

Retrocesos derivados (nada se borra): agregar un nivel que cambia la aplicabilidad, un documento que vence, un cambio de tipo de persona que hace aplicables otros documentos — el estado se recalcula del estado vigente en cada lectura.

## 4. Contratos (`EstadoPaso2`)

| Método | Verdadero cuando | Consumidores |
|---|---|---|
| `responsableCapturado(e)` | existe `responsables_legales` | `RegistrarDocumento`, `Paso2Documentos` |
| `nivelesSeleccionados(e)` | existe al menos un `escuela_niveles` | `Paso2Documentos`, `RegistrarDocumento` |
| `puedeSeleccionarNiveles(e)` | responsable capturado (la editabilidad la exige el caso de uso dentro de su transacción; la lista blanca de niveles, también) | `RegistrarNivelesSeleccionados`, `Paso2Responsable` |
| `puedeCapturarDocumentosGenerales(e)` | responsable **y** al menos un nivel | `RegistrarDocumento` (escuela/plantel), `Paso2Documentos::mount()` |
| `documentosCompletos(e)` / `documentosVigentes(e)` | sin cambio | `ResumenTramite` |
| `generalesSatisfechos(e)` | `etapaFaltante(e) === null` | `Paso24DocumentosNivel::mount()`, `CompuertaPaso3` |
| `etapaFaltante(e)` | primera etapa pendiente en el nuevo orden: `responsable` → `niveles` → `documentos` → `null` | casos de uso de 2.4 y Paso 3, `ResumenTramite`, `CompuertaSubPaso3` |

Regla: ningún consumidor que necesite «documentación general satisfecha» usa `puedeSeleccionarNiveles()`. La prueba `EstadoPaso2Test` fija cada contrato.

**Puede entrar a 2.4:** el nivel pertenece al expediente (Policy `update`), `generalesSatisfechos`, trámite editable (Policy). **Puede acceder a Paso 3:** lo anterior + `EstadoPaso24::etapaFaltante === null` + orden de sub-pasos (`EstadoPaso3`). **Puede enviar:** `EnviarTramite` (sin cambio de diseño): bloqueo, todo en captura, `ResumenTramite::completo`, reevaluación documental y de capacidad dentro de la transacción, sin bloqueantes.

## 5. Mapa de consumidores (antes → después)

| Consumidor | Antes | Después |
|---|---|---|
| `RegistrarNivelesSeleccionados::ejecutar` (agregar) | `etapaFaltante !== null` ⇒ rechaza | `!puedeSeleccionarNiveles` ⇒ rechaza con `RESPONSABLE` |
| `RegistrarDocumento` (generales) | responsable | responsable + nivel (`NIVELES`) |
| `RegistrarDocumento` (nivel), `RegistrarDatosNivel`, `MarcarPasoCompletado`, `RegistrarInmueble`, `RegistrarInfraestructura`, `RegistrarMobiliario`, `CompuertaSubPaso3` | `etapaFaltante` | `etapaFaltante` (nuevo orden; mismo sentido «generales satisfechos») |
| `Paso2Responsable::mount` | con responsable y Paso 2 completo sin niveles ⇒ fase niveles | con responsable y sin niveles ⇒ fase niveles |
| `Paso2Responsable::guardarResponsable` (alta) | ⇒ documentos si faltan | ⇒ fase niveles |
| `Paso2Responsable::guardarNiveles` | ⇒ resumen | ⇒ documentos generales si no están satisfechos; si no, resumen |
| `Paso2Documentos::mount` | responsable | responsable; sin niveles ⇒ `paso2?seccion=niveles` |
| `Paso24DocumentosNivel::mount` | `puedeSeleccionarNiveles` | `generalesSatisfechos` |
| `CompuertaPaso3` | `puedeSeleccionarNiveles` | `generalesSatisfechos` |
| `ResumenTramite` | generales: plantel, responsable, documentos, niveles | plantel, responsable, niveles, documentos; numeración «Paso X de N» derivada |

## 6. Motores

### 6.1 Aplicabilidad

Estados conservados: `Obligatorio`, `NoRequerido`, `PendienteDeterminar` (este último **exige carga**). La política del Certificado de Número Oficial no cambia: con `documentos.numero_oficial.alcance_condicional = []` (valor por omisión) el certificado es obligatorio. Elegir niveles antes de los documentos reduce los casos `PendienteDeterminar` por «sin niveles», pero no sustituye el fundamento.

`FuentesDomicilioMunicipal` (Application) sustituye el método protegido: enumera las fuentes con `documentoId`, `tipoDocumento`, `domicilio`, `autoridad`, `jurisdiccion`, `procedencia`, `certeza`. Excluye por construcción el propio certificado y no cuenta dos veces un documento.

**Matriz de disponibilidad de domicilio estructurado** (verificada contra migraciones y modelos):

| Documento | Tabla | ¿Domicilio estructurado? | Autoridad | Jurisdicción |
|---|---|---|---|---|
| Plantel | `planteles` | Sí (referencia, no fuente) | — | — |
| Dictamen de Uso de Suelo | `dictamenes_uso_suelo` | **No** | `autoridad_expide` (texto) | No |
| Acreditación de ocupación | `acreditaciones_ocupacion_legal` | **No** (solo notaría) | notario | No |
| Seguridad Estructural | `constancias_seguridad_estructural` | **No** | `perito_registro_autoridad` | No |
| Protección Civil | `vistos_buenos_proteccion_civil` | **No** | `autoridad_emisora` | `jurisdiccion_emisora` |
| Número Oficial | `certificados_numero_oficial` | Sí | `autoridad_emisora` | — (excluido: no puede probar su propia exención) |

Conclusión: hoy **no existe ninguna fuente municipal independiente** con domicilio estructurado. El servicio lo devuelve de forma explícita (lista vacía con la matriz documentada) y la evaluación queda `PendienteDeterminar` cuando se configure un alcance. No se agregan campos de domicilio a otros documentos (decisión de captura que toca normativa; ver `docs/decisions/PENDIENTE-fuentes-domicilio-municipal.md`).

### 6.2 Validación

Matriz completa en el informe. Coherencia exigida: `DocumentosCompletos::clavesRequeridas` es la única lista de requeridos para checklist, resumen, contexto del motor (`ConstruirContextoValidacion`) y validación final; `EnviarTramite` vuelve a evaluar dentro de la transacción. No se agregan reglas jurídicas nuevas.

## 7. Integración SEDEQ

### 7.1 Proyección `ConsultarTramiteSedeq`

Agrega, sin escribir nada y sin ejecutar el motor: entidad y municipio del plantel (catálogo si hay FK; texto si no), datos completos del responsable (tipo, nombre/razón social, gestor, RFC, CURP, notaría), terna, por documento sus **metadatos** con estado semántico (`no_capturado`, `no_aplica`, `vigente`, `vencido`, `sin_fecha_expresa`, `no_determinada`), por nivel turno, tipo de alumnado, estado, folio y avance de Paso 3, y el **resultado de validación guardado al envío** (fecha, lista para envío, filas documentales, de capacidad y por nivel), acotado a los niveles visibles. Se rotula como «Resultado almacenado al envío»; no se presenta un recálculo.

### 7.2 Metadatos

`MetadatosDocumentales` (Application, solo lectura) arma pares etiqueta → valor por tipo documental a partir de las tablas de extensión. Un campo `NULL` de un registro histórico se muestra «No capturado», nunca como incumplimiento.

### 7.3 Reporte PDF para SEDEQ

Nueva habilidad `EscuelaPolicy::verReporteEnviado`: supervisor, o revisor que ve **todos** los niveles de la escuela (el PDF cubre todos). Solo la evaluación guardada en el envío (la última, ya que tras el envío no se pueden guardar más). Ruta `sedeq.tramite.reporte`.

### 7.4 Autorización

Sin cambios a `AccesoSedeq`. Los documentos generales siguen visibles a cualquier revisor con un nivel asignado de la escuela (decisión vigente: datos a nivel escuela). Las descargas por nivel siguen exigiendo `view` sobre ese nivel.

## 8. Geografía

- `planteles.municipio_id` BIGINT NULL REFERENCES `municipios(id)` ON DELETE SET NULL, con índice. `planteles.municipio` (texto) se conserva y se deriva del catálogo en el servidor cuando llega un id.
- Paso 1: selector limitado a Querétaro (entidad clave 22, ADR-008) desde `ListarCatalogoGeografico`. El caso de uso valida que el municipio exista y pertenezca a Querétaro. Si el catálogo no está importado se conserva el campo de texto (mismo criterio que el certificado: el caso de uso acepta texto heredado).
- Vinculación histórica: `php artisan geografia:vincular-municipios` en modo simulación por omisión; con `--aplicar` asigna `municipio_id` solo a coincidencias inequívocas (nombre normalizado único dentro de Querétaro para planteles; para certificados, dentro de la entidad elegida o, si no la tienen, en todo el catálogo, donde un nombre repetido es ambiguo y no se asume Querétaro). Nunca sobrescribe texto ni un id existente; reporta ambigüedades y sin correspondencia.
- No se convierte `acreditaciones_ocupacion_legal.notario_localidad` (localidad, no municipio) ni nombres de autoridades.
- `ComparadorDomicilios` compara el municipio por id cuando ambas fuentes lo traen; si no, por texto normalizado.
- Procedencia: `api_info_mexico`, sin atribución oficial a INEGI (ADR-017).

## 9. Datos históricos

| Estado previo | Resultado con el nuevo flujo |
|---|---|
| Responsable sin documentos ni niveles | Siguiente paso: niveles |
| Documentos parciales sin niveles | Niveles; los documentos se conservan y cuentan al volver |
| Documentos completos sin niveles | Niveles; al elegirlos, documentos ya «completado» (salvo nueva exigibilidad) |
| Niveles ya registrados | Sin cambio; `escuela_niveles` no se recrea |
| Datos de 2.4 / Paso 3 iniciados | Sin cambio; estados `escuela_nivel_pasos` intactos |
| Expediente enviado | Congelado (`TramiteEditable`); el resumen no cambia |

## 10. Seguridad y concurrencia

Se conservan: `#[Locked]`, Policies `update`/`view`, `TramiteEditable` dentro de transacciones, orden de bloqueo niveles → escuela, borrado de archivos después del commit, confirmación de eliminaciones. Nuevas pruebas: subir documento general sin niveles por el caso de uso, abrir 2.4 o Paso 3 por URL sin generales, escribir en Paso 3 vía caso de uso sin generales, doble envío, revisor sin asignación, revisor de un nivel que intenta el reporte o un documento de otro nivel.

## 11. Estrategia de pruebas

TDD por tarea (RED → GREEN). Pruebas nuevas: contratos de `EstadoPaso2`, orden del resumen, redirecciones de Paso 2, compatibilidad histórica, fuentes de domicilio, comparador por id, migración y vinculación geográfica, selector de Paso 1, proyección SEDEQ con metadatos y resultado guardado, reporte SEDEQ, prueba integral de extremo a extremo. Las pruebas existentes que codificaban el orden anterior se actualizan con el nuevo orden explícito (no se relajan expectativas).

## 12. Criterios de aceptación

Los del encargo (§49). Se consideran cumplidos cuando la suite completa, Pint y PHPStan pasan en la rama y el informe documenta cada criterio con su evidencia.
