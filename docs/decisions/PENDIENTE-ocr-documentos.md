# PENDIENTE — Lectura automática (OCR) de todos los documentos que sube el solicitante

**Estado:** abierto. Requiere respuesta de SEDEQ (tratamiento de datos personales, qué se espera "revisar" de cada documento) y del dueño del proyecto (alcance, proveedor, presupuesto).
**Origen:** pregunta del dueño (2026-10-19): "¿cómo revisamos realmente los documentos? ¿construimos un sistema de OCR?", y precisión posterior: el OCR debe revisar **todos** los documentos del flujo, no solo los de identidad. Contexto técnico: `ADR-007-motor-validacion-hechos.md`.
**Relación con otros documentos:** extiende el motor documental (ADR-007) sin modificarlo. Es independiente del motor de capacidad instalada (ADR-010/011) y del proyecto de revisión SEDEQ (ADR-016, P2), aunque la revisión humana es el control complementario de lo que aquí se decida. Toca `PENDIENTE-dictamenes-por-nivel.md` (los dictámenes se capturan hoy por plantel).

## 1. Situación actual (hechos verificados en el código)

- **No hay OCR ni lectura automática del archivo.** El motor documental (`app/Domain/Validaciones/Documental/`) trabaja solo con *hechos* (`Hecho`, `TipoHecho`).
- Cada hecho tiene un origen (`OrigenHecho`):
  - `Declarado`: lo que el solicitante capturó en el wizard (p. ej. Paso 2.1).
  - `Documento`: lo que dice un documento subido, "como sea que se haya obtenido (captura manual hoy; un extractor después)". Las reglas no distinguen cómo se obtuvo.
- Hoy el solicitante **teclea** lo que dice cada documento junto a la carga del archivo (nombre y CURP de la INE, folio del recibo, autoridad/folio/giro del dictamen, fechas de emisión y vigencia, número de escritura, títulos del acervo, etc.). Varios viven en tablas tipadas (`credenciales_ine`, `constancias_curp`, `constancias_situacion_fiscal`, `certificados_numero_oficial`, `dictamenes_uso_suelo`, `constancias_seguridad_estructural`).
- Reglas del motor hoy (`Reglas/`): `NombreCoincide`, `IdentificadorCoincide` (CURP/RFC), `DomicilioCoincide`, `DocumentosRequeridosPresentes`, `ReciboNoReutilizado`, `CantidadCoincide` (acervo), `InventarioConLaboratorio`. Además `ValidarVigenciaDocumentos` bloquea documentos de plantel vencidos con base en la fecha tecleada.
- Reglas vigentes (ADR-007, P2): cualquier diferencia de nombre o domicilio es `advertencia`; CURP, RFC o código postal distintos son `no_cumple`. `NormalizadorNombre` ignora el orden de los tokens.

### Debilidad de fondo

El motor compara lo que el solicitante **escribió**, no lo que el archivo **contiene**. Detecta errores de captura honestos, pero no impide teclear datos coherentes y subir cualquier archivo, ni teclear una fecha de emisión distinta de la impresa. El control sobre el contenido real del archivo es hoy únicamente la revisión humana de SEDEQ (proyecto P2).

## 2. Pregunta

¿Se incorpora lectura automática de **todos** los documentos del flujo y, si sí, qué significa "revisar" para cada tipo, con qué proveedor y con qué consecuencias (sugerir, advertir o bloquear)?

Subpreguntas que bloquean la decisión:

1. **SEDEQ:** ¿se permite enviar escrituras, actas, poderes, INE, CURP y fiscales a un proveedor en la nube fuera de México? ¿Qué debe decir el aviso de privacidad? (§6).
2. **Dueño:** ¿el OCR es *sugerencia* (prellena y el solicitante confirma) o *fuente de verdad* (rechaza si no coincide)? ¿Distinto por tipo de documento?
3. **Dueño:** ¿hay presupuesto y cuenta de nube, o debe ejecutarse en infraestructura propia?
4. **SEDEQ:** de cada documento, ¿qué datos deben coincidir y cuáles no importan? (§3 propone una lista; SEDEQ la confirma).
5. **Dueño:** ¿se acepta que los documentos largos y los planos solo reciban comprobaciones básicas (legible, tipo correcto, páginas, fechas) y no extracción de contenido?

## 3. Alcance: los 18 tipos de documento del catálogo

Fuente: `database/seeders/TiposDocumentosSeeder.php` (el catálogo de la base puede diferir si no se ha resembrado). "Hechos" = lo que el motor ya compara o lo que hoy se teclea.

### 3.1 Qué puede significar "revisar" (cuatro comprobaciones)

| Comprobación | Pregunta que responde | Dificultad |
|---|---|---|
| C1. Legibilidad y tipo | ¿El archivo es legible y realmente es este documento (una INE y no una foto cualquiera)? | Baja: clasificación o palabras clave |
| C2. Fechas y vigencia | ¿La fecha impresa (emisión, vigencia) coincide con la tecleada y cumple `vigencia_max_dias`? | Baja-media |
| C3. Hechos contra lo declarado | ¿Nombre, CURP, RFC, domicilio, folio, monto, notario coinciden con lo declarado y entre documentos? | Media |
| C4. Elementos visuales | ¿Hay firma, sello o fotografía donde corresponde? | Alta; no equivale a validar autenticidad |

### 3.2 Documentos por familia

**A. Texto impreso corto y estructurado (OCR + extracción de campos funciona bien)**

| Clave | Ámbito | Hechos / comprobaciones útiles |
|---|---|---|
| `ine` | escuela | C1, C3: nombre, CURP; vigencia de la credencial (fecha impresa) |
| `constancia_curp` | escuela | C3: nombre, CURP |
| `constancia_situacion_fiscal` | escuela | C3: nombre o razón social, RFC; fecha de emisión |
| `certificado_numero_oficial` | plantel | C3: domicilio (calle, número, colonia, CP, municipio) |
| `recibo_pago_derechos` | escuela_nivel | C3: folio (regla `ReciboNoReutilizado`), monto, fecha |
| `dictamen_uso_suelo` | plantel | C2, C3: autoridad, folio, giro, fecha; vigencia 30 días |
| `visto_bueno_proteccion_civil` | plantel | C2: fecha de emisión; vigencia 30 días |
| `constancia_seguridad_estructural` | plantel | C2, C3: responsable, fecha, vigencia |
| `acta_nacimiento` | escuela | C1, C3: nombre (hoy no hay regla que la use) |

**B. Texto legal largo y de formato libre (OCR sí; extracción de campos requiere un modelo de lenguaje o un extractor entrenado)**

| Clave | Ámbito | Hechos / comprobaciones útiles |
|---|---|---|
| `escritura_inmueble` | plantel | C1, C2, C3: número de escritura, fecha, notario, propietario, domicilio del inmueble |
| `escritura_poder_facultades` | escuela (moral) | C3: representante, facultades, notario, número, fecha |
| `acta_constitutiva` | escuela (moral) | C3: razón social, objeto social, representante |
| `poder_gestor` | escuela (con gestor) | C3: otorgante, gestor, facultades de administración, fecha |

Son documentos de decenas de páginas. El texto completo sale barato por OCR; extraer "quién es el propietario" o "tiene facultades de administración" exige interpretar lenguaje notarial. Es la parte que **más** valor tendría para SEDEQ y también la **menos** fiable de automatizar.

**C. Tablas y listas**

| Clave | Ámbito | Hechos / comprobaciones útiles |
|---|---|---|
| `acervo_bibliografico_primaria` / `_secundaria` | escuela_nivel | C3: número de títulos contra el mínimo exigido (`CantidadCoincide`) |
| `inventario_laboratorio` | escuela_nivel | C3: que existan laboratorios y equipo acordes a lo declarado (`InventarioConLaboratorio`) |

Requieren reconocimiento de tablas (más caro que OCR plano) y tolerarán mal los formatos libres de Excel o Word impresos.

**D. Dibujos y documentos firmados**

| Clave | Ámbito | Hechos / comprobaciones útiles |
|---|---|---|
| `plano_inmueble` | plantel | Solo C1: que el archivo sea legible y de tamaño razonable. El OCR casi no aporta sobre un plano; las medidas contra lo capturado en Paso 3 no son extraíbles de forma fiable |
| `formato_solicitud` | escuela_nivel | C4: presencia de firma y que el contenido corresponda al Formato que generó el sistema (el propio PDF del sistema permite comparar contra datos conocidos) |

### 3.3 Lo que el OCR no resuelve

Validar **autenticidad** (documento falsificado, alterado o expedido por quien no corresponde), biometría o comparación facial, y verificación contra fuentes oficiales (RENAPO, SAT, INE, registros públicos). Eso es "proofing" de identidad y de documentos, un proyecto distinto con otros costos y requisitos legales. Una firma o sello detectados por software dicen "hay una marca ahí", no "es válida".

## 4. Opciones

### Opción A — No agregar OCR (estado actual)

Captura manual más revisión humana de SEDEQ (P2): el revisor ve el archivo junto a los datos tecleados.

- Costo: $0. Sin riesgo de datos personales adicional.
- Límite: no hay control automático sobre el contenido; el revisor carga todo el trabajo de comparar, incluidas las escrituras y actas.

### Opción B — OCR como sugerencia en todos los documentos (recomendada si se quiere OCR)

Un extractor produce hechos `OrigenHecho::Documento`; el formulario los muestra prellenados y el solicitante los confirma o corrige; el motor sigue igual. Para cada documento se aplican las comprobaciones C1-C4 que su familia soporta (§3.2).

- Reduce errores de captura y esfuerzo del solicitante.
- Un fallo del OCR **no bloquea** a nadie: se queda la captura manual de hoy.
- Si el solicitante corrige un valor leído, conviene guardar ambos (leído y confirmado) para que el revisor vea la discrepancia. Requiere cambio de esquema, no decidido.
- Entrega por fases: primero la familia A (más valor por menos riesgo), después C, y B/D al final o nunca.

### Opción B+ — Sugerencia más advertencia no bloqueante

Igual que B, pero si lo leído difiere de lo confirmado el sistema genera una `advertencia` visible para SEDEQ (mismo mecanismo que hoy usan nombre y domicilio). Sigue sin bloquear al solicitante.

### Opción C — OCR como fuente de verdad (bloquea)

El sistema rechaza la carga cuando lo leído no coincide con lo declarado.

- **No se recomienda.** El OCR sobre fotos de teléfono es poco fiable; un falso rechazo bloquea a un solicitante legítimo, y no existe vía de corrección para "datos declarados erróneos" (ADR-007, límite conocido: Paso 2.1 es de solo lectura una vez capturado).

## 5. Proveedores y costos

### 5.1 Supuestos de volumen (a corregir por SEDEQ / dueño)

El volumen real es mucho mayor que solo identidad porque las escrituras y actas son largas. Dos escenarios, ambos por trámite y con 1,000 trámites al año:

- **Típico:** 50 páginas por trámite → **50,000 páginas al año** (identidad 6, plantel y dictámenes 12, escritura 20, formato y recibo 6, acervo e inventario 6).
- **Pesado:** 150 páginas por trámite (escrituras y actas extensas, moral con poderes) → **150,000 páginas al año**.

Del total, solo unas **10 páginas por trámite** (10,000 al año en el escenario típico) necesitan extracción de **campos** (INE, CURP, fiscal, número oficial, recibo, dictámenes); el resto puede ir por OCR de texto plano.

### 5.2 Precios de lista (USD, consultados el 2026-10-19; cambian con frecuencia: confirmar antes de citarlos en una propuesta)

| Servicio | Modo | Precio de lista | Confianza del dato |
|---|---|---|---|
| Google Document AI | Enterprise Document OCR | $1.50 por 1,000 páginas (primeras 1,000 al mes gratis) | Resumen de búsqueda de la página oficial (la página no cargó completa) |
| | Form Parser | $30 por 1,000 páginas | Igual |
| | Layout Parser | $10 por 1,000 páginas | Igual |
| | Parser de identificación (licencias/pasaportes de EE. UU.) | ≈ $0.10 por documento | Fuente de terceros |
| | Hospedaje de procesador personalizado | $0.05 por hora por versión (≈ $438 al año) | Resumen de búsqueda |
| AWS Textract | DetectDocumentText | $0.0015 por página | Página oficial, leída directamente |
| | Tables | $0.015 por página | Página oficial |
| | Queries | $0.015 por página | Página oficial |
| | Forms + Tables + Queries | $0.07 por página | Página oficial |
| | Signatures | $0.0035 por página | Página oficial |
| | AnalyzeID | $0.025 por página | Página oficial |
| Azure Document Intelligence | Read | ≈ $1.50 por 1,000 páginas | Terceros; la página oficial mostró "$-" |
| | Modelos prediseñados | ≈ $10 por 1,000 páginas | **Sin confirmar**: fuentes en conflicto |
| | Extracción personalizada | ≈ $30 por 1,000 páginas | Terceros |
| Tesseract / PaddleOCR | Autohospedado | $0 de licencia | Licencia Apache 2.0 |

### 5.3 Costo anual estimado (solo servicio, sin desarrollo)

| Estrategia | Típico (50,000 páginas) | Pesado (150,000 páginas) |
|---|---|---|
| OCR plano en todo (Google o Azure ≈ $1.50/1,000; Textract $0.0015) | ≈ $75 | ≈ $225 |
| Extracción de campos en **todo** (Textract Forms+Tables+Queries $0.07/pág.) | ≈ $3,500 | ≈ $10,500 |
| Extracción de campos en **todo** (Google Form Parser $30/1,000) | ≈ $1,500 | ≈ $4,500 |
| **Híbrida:** OCR plano en todo + Queries solo en 10 págs./trámite (Textract $0.015) | ≈ $75 + $150 = **≈ $225** | ≈ $225 + $150 = **≈ $375** |
| **Híbrida con Google:** OCR plano en todo + Form Parser solo en 10 págs./trámite | ≈ $75 + $300 = **≈ $375** | ≈ $225 + $300 = **≈ $525** |
| Autohospedado (PaddleOCR) | $0 de licencia + servidor | $0 de licencia + servidor |

Conclusiones sobre costo:

- Con la estrategia **híbrida** el servicio cuesta **cientos de dólares al año**; el costo no es el factor decisivo. Aplicar extracción de campos a todas las páginas, escrituras incluidas, sí multiplica el costo por 10-40 sin aportar casi nada.
- Los parsers de identificación son los más caros por página útil y, ver §6, probablemente no sirven para la INE.
- Los servicios en la nube son SaaS de pago por uso: **no hay licencia que comprar**, pero sí cuenta de nube con tarjeta, cobro en dólares y dependencia del proveedor.
- Tesseract y PaddleOCR no cuestan licencia; el costo real es el servidor, la configuración y la menor precisión con fotos de teléfono (PaddleOCR rinde mejor que Tesseract). Cubren OCR plano, no extracción de tablas ni de campos.
- **No incluido y sin cotizar:** interpretar las escrituras y actas (familia B) con un modelo de lenguaje, que se cobra por token y depende de la longitud del documento. Habría que cotizarlo con el proveedor elegido antes de prometerlo; no se ha investigado aquí. También quedan fuera: desarrollo, almacenamiento temporal del archivo, reintentos y monitoreo.

## 6. Riesgos y restricciones

1. **INE mexicana.** No se confirmó que algún parser prediseñado soporte la INE. Hasta donde se sabe, los parsers de Google y AWS están hechos para licencias y pasaportes de EE. UU. La ruta práctica es OCR general más parseo propio (el motor ya tiene `NormalizadorNombre` y normalizadores de domicilio). **Antes de elegir proveedor hay que probar con documentos mexicanos reales (anonimizados o con consentimiento).** Lo mismo aplica a constancias, dictámenes y recibos de distintas autoridades: sus formatos varían por municipio.
2. **Datos personales y legales fuera de México.** INE, CURP, acta de nacimiento, constancia fiscal, escrituras y poderes contienen datos personales y patrimoniales. Enviarlos a un proveedor en el extranjero es una transferencia internacional: SEDEQ debe aprobarla y el aviso de privacidad debe cubrirla. *Esto no es asesoría legal; SEDEQ debe confirmarlo con su área jurídica o de transparencia.* Esta restricción puede inclinar la decisión hacia OCR autohospedado o hacia no agregar OCR.
3. **Formatos variables.** Dictámenes de uso de suelo, vistos buenos de Protección Civil y certificados de número oficial los emiten muchas autoridades con formatos distintos; una extracción por plantilla fija fallará con frecuencia. Un extractor basado en lenguaje tolera más variación pero tiene costo y riesgo de "alucinar" campos: la confirmación del solicitante (Opción B) es el contrapeso.
4. **Calidad de imagen.** Fotos con ángulo, sombras, reflejos y escaneos torcidos degradan el OCR. Con B el impacto es bajo; con C es alto.
5. **Archivos grandes.** Escrituras de decenas de páginas: límites de tamaño y páginas por solicitud de cada proveedor, tiempos de proceso y cola. Hoy `php.ini` ya tuvo que ampliarse para subidas.
6. **Almacenamiento y retención.** Decidir desde dónde se envía el archivo, qué retención ofrece el proveedor y si se puede excluir el uso de los datos para entrenamiento.
7. **Dependencia y disponibilidad.** Una caída del proveedor no debe bloquear el wizard (la Opción B degrada a captura manual).
8. **Cola.** El OCR sería asíncrono sobre la cola existente (`QUEUE_CONNECTION=database`) y necesita un `queue:work` corriendo; la interfaz debe mostrar "leyendo documento…" y permitir seguir sin esperar.
9. **Falsa confianza.** Un "OCR coincide" puede leerse como "documento verificado". La interfaz y el reporte deben decir "datos leídos coinciden", nunca "documento válido" (autenticidad fuera de alcance, §3.3).

## 7. Diseño propuesto para la Opción B (si se aprueba)

Respeta ADR-001 (capas) y la regla de `CLAUDE.md` de no crear carpetas vacías por adelantado.

- **Puerto en Application o Domain:** una interfaz `ExtractorDeDocumento` (entrada: archivo y clave de tipo de documento; salida: lista de `Hecho` con origen `Documento`, un indicador de confianza por hecho y el texto completo opcional). Sin dependencia de `Illuminate\*` en Domain (regla PHPat vigente).
- **Una "receta" por clave de documento** (qué hechos extraer, qué comprobaciones C1-C4 aplican) para que el catálogo de §3.2 viva en un solo lugar, igual que `CatalogoReglasDocumentales` para las reglas.
- **Implementaciones en `app/Infrastructure/`:** una por proveedor (nube o autohospedado), intercambiables por configuración. Pueden combinarse (OCR plano local + campos en nube, o al revés). Nada de lógica de negocio en ellas.
- **Caso de uso en `app/Application/Documentos/`:** invoca al extractor al registrar el documento (en cola), nunca desde Livewire directamente. Un fallo del extractor se registra y no impide guardar el documento.
- **Presentación:** el formulario del documento recibe los valores leídos como valores iniciales editables, con la etiqueta "leído automáticamente, verifique".
- **Motor:** sin cambios en las reglas. Los hechos leídos y los confirmados siguen siendo `Documento`. Si se adopta B+, una regla nueva compara "leído" contra "confirmado".
- **Esquema (pendiente de decidir):** conservar el valor leído por separado del confirmado exige columna o tabla nueva; cualquier cambio estructural debe seguir la regla de `CLAUDE.md` (no inventar esquema ad hoc) y aprobarse primero.
- **Pruebas:** TDD con un extractor falso (`FakeExtractor`); el proveedor real nunca se llama en CI. Corpus de prueba con documentos mexicanos reales anonimizados, y medición de precisión por tipo antes de activar.

## 8. Recomendación (del agente, no una decisión)

1. Mantener el motor de hechos como está y apoyarse en la revisión de SEDEQ (P2) para el contenido real de los archivos mientras no se decida lo demás.
2. Si se quiere OCR en todos los documentos, adoptar **Opción B (o B+) con estrategia híbrida y por fases**: familia A primero; C después; familia B (escrituras, actas, poderes) solo con OCR de texto completo y, si SEDEQ lo pide, extracción con modelo de lenguaje cotizada aparte; familia D solo C1.
3. **Proveedor:** si SEDEQ aprueba la transferencia, OCR general de Google, AWS o Azure (≈ $75-$525 al año según escenario). Si no, **PaddleOCR autohospedado** para texto plano y revisión humana para campos.
4. **No** comprar parsers de identificación especializados hasta probar que soportan la INE.
5. Antes de escribir código: respuesta de SEDEQ a la pregunta 1 de §2 y una muestra de 10-20 trámites completos con documentos mexicanos reales para medir precisión por tipo.

## 9. Qué cierra este PENDIENTE

- Respuesta de SEDEQ sobre transferencia de datos al extranjero y confirmación de qué se compara en cada documento (§3.2).
- Decisión del dueño: Opción A, B, B+ o C; proveedor; presupuesto; fases.
- Resultado de una prueba de precisión con documentos reales.

Cuando se resuelva, renombrar a `ADR-NNN-ocr-documentos.md` en el mismo commit (regla de `CLAUDE.md`).

## 10. Fuentes consultadas (2026-10-19)

- AWS Textract pricing: https://aws.amazon.com/textract/pricing/
- Google Document AI pricing: https://cloud.google.com/document-ai/pricing
- Resumen de terceros de precios de Google Document AI: https://aiproductivity.ai/pricing/google-document-ai/
- Azure Document Intelligence pricing: https://azure.microsoft.com/en-us/pricing/details/ai-document-intelligence/
- Respuesta de Microsoft Q&A sobre tarifas: https://learn.microsoft.com/en-us/answers/a/12439288
- Catálogo de documentos: `app-laravel/database/seeders/TiposDocumentosSeeder.php`
