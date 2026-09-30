# Motor de validación documental: integración al flujo del trámite

**Fecha:** 2026-09-30
**Rama:** `spike/validation-engine-eval` (continúa la evaluación del mismo día; no mergeada)
**Decisiones:** `docs/decisions/ADR-007-motor-validacion-hechos.md` (antes `PENDIENTE-motor-validacion-hechos.md`)
**Informe anterior:** `docs/reports/2026-09-30-evaluacion-motor-validacion.md`

## 1. Qué se pidió

El owner respondió las 10 preguntas del PENDIENTE y pidió integrar el motor al flujo del solicitante, ampliar sus capacidades y mantener el alcance, tomando las decisiones restantes. El registro de cada respuesta y de cada decisión del agente está en ADR-007.

## 2. Qué se construyó

### Flujo del solicitante

1. **Paso 2.1 (Responsable legal):** con gestor, se pide la **CURP del gestor** (obligatoria, formato validado).
2. **Paso 2.2 (Documentos):** cuatro documentos se capturan como **datos + PDF**:
   - **INE:** nombre y CURP como aparecen en la credencial.
   - **Constancia de CURP** (nueva en el catálogo): nombre y CURP.
   - **Constancia de Situación Fiscal** (nueva): nombre o razón social y RFC.
   - **Certificado de número oficial:** calle, número exterior, colonia, municipio y código postal.

   Los formatos de CURP, RFC y código postal que exige el formulario son las mismas constantes que usa el motor (`FormatosIdentificador`), para que la captura y la validación no se desfasen.
3. **Resumen:** cuando todas las secciones están completas, la tarjeta "Siguiente paso" lleva a la **Validación final**.
4. **Validación final** (`/tramite/{escuela}/validacion`, Livewire `ValidacionFinal`):
   - Al entrar, ejecuta el motor, guarda la evaluación como PDF y fila, y muestra el veredicto: "no tiene errores que impidan enviarlo", o "hay errores que debes corregir antes de enviar".
   - Lista cada revisión ordenada por gravedad (debe corregirse, alerta, no verificado, correcto), con lo que se comparó línea por línea.
   - Cada revisión con problemas trae un enlace **"Corregir: {documento}"** a `paso2-documentos?corregir={clave}`. Ese enlace reabre el documento aunque Paso 2.2 ya esté completo, y al guardarlo regresa a la validación final.
   - Botones "Validar de nuevo" y "Descargar reporte en PDF".

### Reglas (Domain, sin Laravel)

| Clave | Compara | Si difiere |
|---|---|---|
| `documentos_requeridos_presentes` | catálogo aplicable vs. subidos | `no_cumple` |
| `nombre_identidad_coincide` | nombre declarado vs. INE y Constancia de CURP | `advertencia` |
| `curp_coincide` | CURP declarada vs. INE y Constancia de CURP (sin declarada: entre documentos) | `no_cumple` |
| `nombre_fiscal_coincide` | nombre o razón social declarado vs. Constancia de Situación Fiscal | `advertencia` |
| `rfc_coincide` | RFC declarado vs. Constancia de Situación Fiscal | `no_cumple` |
| `domicilio_coincide` | domicilio del plantel vs. certificado de número oficial | CP: `no_cumple`; resto: `advertencia` |

Reglas comunes a todas:

- Un documento **subido sin sus datos** (por ejemplo, subido antes de este cambio) es `no_cumple` y se señala, para que el solicitante lo vuelva a subir con sus datos.
- Un documento **no subido** no se evalúa en las reglas de coincidencia; lo reporta `documentos_requeridos_presentes`.

Las reglas se definen en `CatalogoReglasDocumentales`. `NombreCoincide` e `IdentificadorCoincide` comparten la lógica de agregación en la clase base `ReglaDeCoincidencia`. `ResultadoRegla` ahora trae `documentos`: las claves que el solicitante debe corregir.

### Cambios de esquema y justificación contra el DDL v3

| Migración | Tipo | Justificación |
|---|---|---|
| `2026_09_30_000000_create_datos_de_documentos_tables` | 4 tablas nuevas | ADR-007 P3. Mismo patrón que `constancias_seguridad_estructural` (PK = FK al documento, `ON DELETE CASCADE`). No tocan tablas existentes. Tamaños de domicilio iguales a `planteles`. |
| `2026_09_30_000001_add_curp_to_gestores_table` | `ALTER TABLE gestores ADD COLUMN curp VARCHAR(18)` | ADR-007 P8 (decisión del owner). **Única modificación a una tabla del DDL v3.** Nullable, para no romper filas existentes; el formulario la exige para gestores nuevos. |
| `2026_09_30_000002_create_evaluaciones_validacion_table` | 1 tabla nueva | ADR-007 P6. Evidencia de cada evaluación (ruta del PDF, veredicto, filas en JSONB). |
| (eliminada) `2026_09_30_000000_create_hechos_documento_table` | — | Sustituida por las tablas tipadas. Nunca llegó a `master`. |

`TiposDocumentosSeeder` agrega `constancia_curp` y `constancia_situacion_fiscal` al final (15 filas). **La base dev no se tocó**: al mergear hay que correr `php artisan migrate` y `php artisan db:seed --class=TiposDocumentosSeeder` (idempotente, `insertOrIgnore`), con confirmación del owner según CLAUDE.md.

### Capas

- **Domain** (`app/Domain/Validaciones`): reglas, normalizadores y catálogo. Sigue sin depender de `Illuminate\*`; PHPStan/PHPat lo verifican.
- **Application** (`app/Application/Validaciones`):
  - `ConstruirContextoValidacion`: la única lectura a la base; decide de quién es "lo declarado".
  - `EjecutarValidacionDocumental`: el motor.
  - `EjecutarValidacionFinal`: la compuerta y la persistencia.
  - `PresentadorValidacion`: convierte el reporte en filas legibles.
  - `UltimaValidacionFinal`, `ReporteValidacionGuardado`: lecturas.
  - DTOs `ValidacionFinal` / `FilaValidacion`, con puros strings: Livewire no importa Domain (ADR-001).
- **Infrastructure:** `Pdf/ReporteValidacionPdf` (dompdf, disco privado).
- **Presentación:** `ValidacionFinal` (Livewire), `DescargarReporteValidacionController`, formularios `IdentidadDocumentoForm`, `SituacionFiscalForm` y `NumeroOficialForm`.

## 3. Evidencia

### TDD

Cada paso empezó con pruebas que se vieron fallar por la razón correcta: clase, constante, propiedad o ruta inexistente, o aserción incorrecta. Nunca fallaron por sintaxis o entorno.

### Pruebas existentes que cambiaron, y por qué

Todos son cambios de comportamiento pedidos, no pruebas debilitadas:

- `DocumentosCompletosTest` y `TiposDocumentosSeederTest`: los conteos del catálogo suben en 2 (P5).
- `Paso2DocumentosTest` (11 llamadas) y `Paso2DocumentosGuardarHttpRoundTripTest`: la INE ya no se guarda con `guardarDocumentoSimple`, sino con `guardarIne` y sus datos (P3).
- `Paso2ResponsableTest`: el alta con gestor ahora incluye la CURP del gestor (P8).

### Mutaciones de no-vacuidad

Cada mutación fue temporal, no commiteada, y se revirtió.

| Mutación | Resultado |
|---|---|
| `ReglaDeCoincidencia::evaluar()` siempre `cumple` | 13 fallas + 1 error de 17 |
| `NombreCoincide`: diferencia → `cumple` | 3 de 9 fallan |
| `NombreCoincide`: comparación sensible al orden | 2 de 9 fallan |
| `IdentificadorCoincide`: diferencia → `advertencia` | 3 de 8 fallan |
| `DomicilioCoincide::evaluar()` siempre `cumple` | 5 fallas + 1 error de 7 |
| `DomicilioCoincide`: CP tratado como texto | 1 de 7 falla |
| `NormalizadorDomicilio` sin expansión de abreviaturas | 1 de 7 falla |
| `ConstruirContextoValidacion`: identidad del gestor → titular | 2 de 12 fallan |
| `ConstruirContextoValidacion`: sin datos del certificado del plantel | 4 de 12 fallan |
| `ConstruirContextoValidacion`: sin RFC de la constancia fiscal | 3 de 12 fallan |
| `EjecutarValidacionFinal`: compuerta siempre abierta | 1 de 8 falla |
| `EjecutarValidacionFinal`: sin precondición de trámite completo | 1 de 8 falla |
| `Paso2Documentos`: sin regreso a la validación tras corregir | 1 de 8 falla |

### PDF

Se generó un reporte de muestra (CURP distinta en la INE y número exterior distinto en el certificado) y se revisó visualmente. Encabezado con número de trámite, domicilio y fecha; veredicto en rojo; una fila por revisión con estado coloreado y cada valor comparado. Pesa unos 860 KB porque dompdf incrusta la fuente DejaVu (necesaria para acentos y «»).

## 4. Resultados de verificación

Entorno: el mismo contenedor efímero; PostgreSQL 16 local, base `sedeq_incorporacion_testing`.

| Verificación | Resultado |
|---|---|
| Suite completa | **653/653** (antes de esta etapa: 604; `master`: 546) |
| Pint `--test` | Pasa (corregidos un import sin usar y el orden de imports antes del commit) |
| PHPStan nivel 5 + PHPat | 0 errores (se tiparon las relaciones `HasOne` leídas en `ConstruirContextoValidacion`) |

**No verificado:**

- La página "Validación final" y los formularios nuevos de Paso 2.2 **no se vieron en un navegador**. Las pruebas de Livewire y HTTP cubren el contenido y los enlaces, no la apariencia.
- El `down()` de las migraciones nuevas no se corrió por separado.

## 5. Deliberadamente fuera de alcance

- **Envío del trámite** (estado `en_revision`, candado de edición): WS-7. La nota para WS-7 quedó en `PENDIENTE-edicion-hasta-envio.md`.
- **RFC de persona moral:** no hay columna; la regla da `no_evaluable` para morales.
- **Corrección de datos declarados en Paso 2.1:** sigue de solo lectura (WS-7).
- **Retención/limpieza de evaluaciones y PDFs:** cada visita a la página final genera una evaluación.
- **Documentos subidos antes de este cambio** (INE y certificado sin datos): la validación final los marca `no_cumple` y pide volver a subirlos. Es intencional, pero afecta a expedientes existentes en dev.
- **Motor de capacidad instalada** (Paso 3): sin cambios, sigue en `PENDIENTE-origen-de-magnitud.md`.

## 6. Evaluación tras WS-5b (Paso 2.4 "Documentos por nivel", ya en `master`)

Se mezcló `master` en esta rama. Los conflictos se resolvieron conservando ambos lados:

- **Catálogo:** las dos constancias de ADR-007 quedan al final del catálogo de Paso 2.2 de WS-5b (18 filas en total).
- **Paso 2.2:** los cuatro documentos con datos se suman a `CON_DATOS_ESTRUCTURADOS`, y el Formato de Solicitud sale de 2.2 como en `master`.
- **Registro:** `RegistrarDocumento` guarda los datos tipados de ADR-007 y el recibo de WS-5b.
- **Fixture:** el de validación final completa Paso 2.4.
- **Resultado:** suite 778/778.

### ¿Cambia cómo funciona el motor?

**No hace falta.**

- La validación final solo corre con el trámite completo (`ResumenTramite::completo`), y desde WS-5b eso incluye Paso 2.4. Así que los documentos por nivel siempre están cargados cuando el motor corre.
- `DocumentosRequeridosPresentes` sigue cubriendo solo Paso 2.2, sin hueco real.
- La regla de domicilio ya trata un certificado de número oficial ausente como `no_evaluable`. Eso cubre el caso de que el certificado se vuelva condicional (`PENDIENTE-dictamenes-por-nivel.md`).

### Usos nuevos que WS-5b hace posibles (propuestos, no implementados)

Todos siguen el mismo patrón de ADR-007: hechos tipados, capturados con el documento, y reglas que solo leen hechos.

1. **Recibo de pago reutilizado.** `recibos_pago_derechos` ya guarda folio, monto y fecha. Regla: el mismo folio en otra escuela o nivel → `no_cumple` (un recibo pagado una vez no debería amparar dos trámites). Determinista, sin datos nuevos.
2. **Acervo bibliográfico: relación vs. biblioteca declarada.** WS-5b pide la "Relación del acervo bibliográfico" por nivel (Primaria y Secundaria). Si se captura con ella el número de títulos, una regla puede compararlo contra los títulos declarados en Infraestructura (Paso 3). El motor de capacidad (PR #2) ya revisa el mínimo normativo con estos últimos.
3. **Inventario de laboratorio vs. espacio declarado (Secundaria).** El inventario se sube en 2.4; el laboratorio polifuncional se declara en Infraestructura. Si hay uno sin el otro → `advertencia`.
4. **Documentos requeridos por nivel.** Extender `DocumentosRequeridosPresentes` a los documentos de 2.4, con enlace "Corregir" a la página del nivel. Hoy no aporta nada, porque la compuerta ya lo asegura. Tendrá sentido cuando WS-7 permita editar y quitar documentos antes del envío.

Sin cambio posible: el Formato de Solicitud firmado sigue sin poder cotejarse contra el PDF generado (limitación ya aceptada en el informe de WS-5b). El turno y el tipo que imprime salen de los mismos datos de 2.4, y cambiarlos descarta el Formato.
