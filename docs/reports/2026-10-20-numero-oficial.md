# Informe — Certificado de Número Oficial (Paso 2.2): auditoría normativa y refactor

Fecha: 2026-10-20. Rama: `worktree-numero-oficial` (sin commit, sin push, sin merge). Especificación y plan locales (gitignored): `docs/superpowers/specs/2026-10-20-numero-oficial-design.md` y `docs/superpowers/plans/2026-10-20-numero-oficial.md`. Fase I modificó `docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md` (nuevo apartado «Fundamento normativo del Certificado de Número Oficial» en §5 y «Tercera pasada» en §9).

## 1. Fuentes oficiales consultadas (2026-10-20)

- Cédula TR-170200-013 «Obtén tu Certificado de Número Oficial», Municipio de Querétaro, rev. 36, actualizada 04/FEB/2025: `https://tramitesqro.municipiodequeretaro.gob.mx/cedula.php?id=TR-170200-013`.
- Cédula TR-170100-065 «Dictamen de Uso de Suelo con Número Oficial (2 en 1)», rev. 8, actualizada 13/FEB/2025: `https://tramitesqro.municipiodequeretaro.gob.mx/cedula.php?id=TR-170100-065`.
- Reglamento de Construcción para el Municipio de Querétaro (PDF del registro municipal, reformas hasta 2022), arts. 22, 38, 39, 40, 41, 43.
- Código Urbano del Estado de Querétaro, arts. 321, 357, 358 — leídos en una **copia no oficial** (`leyes-mx.com/codigo_urbano_queretaro/<artículo>.htm`).
- **No leídos / no accesibles:** Código Municipal de Querétaro arts. 192 y 431 (solo citados por las cédulas); reglamentación de otros municipios; requisitos de SEDEQ por nivel (los PDF de SEDEQ no se pudieron abrir).

## 2. Requisitos confirmados y pendientes

**Confirmados (Municipio de Querétaro):** el certificado asigna el número oficial exterior y/o interior del predio; **vigencia permanente**; tramitación en 3 días hábiles; el trámite combinado con el Dictamen de Uso de Suelo contiene **dos determinaciones independientes** con vigencia diferenciada; el número oficial es único por predio (Reglamento art. 39; Código Urbano art. 357) y el número interior se distingue con letra o número precedido de guión. La cédula no lista los campos impresos del certificado y no enumera cuándo se actualiza.

**Documentado, no contrastado:** SEDEQ exige el certificado solo cuando los documentos municipales traen domicilios diferentes (requisitos de Básica §16, vía Compendio), y el «domicilio oficial» de referencia es el del certificado.

**Pendiente de SEDEQ:** alcance por nivel de la condición (Inicial, Media Superior, Superior), definición de «discrepancia» (¿cuenta una abreviatura?), qué documentos se comparan, quién resuelve un caso dudoso, aceptación de un único PDF del trámite 2 en 1, mismo certificado para varios niveles, planteles fuera del Municipio de Querétaro. Las doce preguntas del encargo están respondidas con fundamento, jurisdicción y certeza en el Compendio §5.

## 3. Diferencias antes / después

| | Antes | Después |
|---|---|---|
| Captura | calle, número exterior, colonia, municipio, CP | además fecha de expedición, autoridad emisora, folio, número interior y clave catastral (todos **opcionales**); `S/N` admitido; sin vencimiento |
| Persistencia | 5 columnas | +4 columnas nullable (`numero_int`, `autoridad_emisora`, `folio_certificado`, `clave_catastral`); la fecha usa `documentos_plantel.fecha_emision` |
| Obligatoriedad | siempre, para todo plantel y nivel | **igual por defecto**; existe la arquitectura para volverlo condicional cuando SEDEQ confirme el alcance |
| Completitud | `clavesAplicables` = requeridas | `clavesAplicables` (se muestra y acepta subir) ≠ `clavesRequeridas` (cuenta para el avance) |
| Comparación de domicilios | solo regla `DomicilioCoincide` del motor de validación final | además `ComparadorDomicilios` independiente (reutiliza `NormalizadorDomicilio`) |

## 4. Decisiones arquitectónicas

- **Tres estados** `Obligatorio` / `NoRequerido` / `PendienteDeterminar` (`Aplicabilidad`), con `ResultadoAplicabilidad` (estado, motivo, fundamento, certeza). `PendienteDeterminar` cuenta como requerido; ausencia de evidencia no es ausencia de discrepancia.
- **Política segura por defecto:** `config/documentos.php` → `numero_oficial.alcance_condicional = []`. Vacía = condición no confirmada = certificado obligatorio en todos los casos. Fijar la lista (por ejemplo `['preescolar','primaria','secundaria']`) es decisión del dueño con respaldo de SEDEQ; solo entonces importan niveles y evidencia.
- **Con alcance configurado:** sin niveles elegidos → pendiente (en Paso 2.2 aún no se eligen; 2.3 es posterior); algún nivel fuera → obligatorio; todos dentro → `NoRequerido` solo con ≥2 documentos municipales de domicilio coincidente y ninguno en duda; cualquier otro caso → pendiente.
- **Hoy `NoRequerido` es inalcanzable en producción:** ningún documento municipal capturado (salvo el propio certificado) guarda un domicilio; `EvaluarAplicabilidadDocumentos::domiciliosMunicipales()` devuelve vacío y es el punto de extensión. Es deliberado: no se inventa evidencia.
- **El solicitante no interviene:** no existe propiedad ni campo de formulario que cambie la aplicabilidad (una prueba lo comprueba); la evalúa el servidor.
- **Decisión manual auditable: solo contrato documentado, sin tabla, rol ni pantalla.** `DiscrepanciaConfirmada` existe en el enum del comparador pero este nunca la devuelve; requeriría una persona autorizada de SEDEQ, con estado, motivo, fundamento, actor, fecha y alcance.
- **Un certificado ya cargado que deja de ser exigible se conserva** y puede reemplazarse; si la condición cambia, vuelve a contar.
- El comparador **no** convierte una diferencia de texto en discrepancia: devuelve `CoincidenciaAcreditada` o `EvidenciaInsuficiente`.

## 5. Archivos

Código: `app/Domain/Documentos/{Domicilio,ResultadoComparacion,ComparacionDomicilios,ComparadorDomicilios}.php` (nuevos), `app/Application/Documentos/{Aplicabilidad,ResultadoAplicabilidad,EvaluarAplicabilidadDocumentos}.php` (nuevos), `DocumentosCompletos.php`, `DTO/DatosDocumento.php`, `RegistrarDocumento.php`, `app/Application/Tramite/ResumenTramite.php`, `app/Application/Validaciones/ConstruirContextoValidacion.php`, `app/Livewire/Forms/NumeroOficialForm.php`, `app/Livewire/Tramite/Paso2Documentos.php`, `app/Models/CertificadoNumeroOficial.php`, `resources/views/livewire/tramite/paso2-documentos.blade.php`, `config/documentos.php` (nuevo).
Migración: `database/migrations/2026_10_20_000300_extend_certificados_numero_oficial.php` (aditiva, reversible, **no ejecutada en la BD de desarrollo**).
Pruebas nuevas: `CertificadosNumeroOficialMigracionTest`, `RegistrarNumeroOficialTest`, `Paso2NumeroOficialTest`, `ComparadorDomiciliosTest`, `EvaluarAplicabilidadDocumentosTest`, `DocumentosCompletosAplicabilidadTest`.
Documentación: Compendio (Fase I), este informe y la actualización añadida a `PENDIENTE-dictamenes-por-nivel.md`.

## 6. Pruebas y resultados

Línea base antes de modificar: suites relevantes 451/451; suite completa de `master` 1728/1728. Después de los cambios: **suite completa 1795/1795** (6386 aserciones), `pint --test` limpio, PHPStan con 0 errores. Las corridas de suites se hicieron en primer plano (las de fondo fueron detenidas dos veces por el sistema por poca memoria en sesiones anteriores).

**Desviaciones respecto al TDD:** el RED de T1/T4 se observó por clase o columna inexistente; una prueba de evaluador necesitó sembrar el catálogo de niveles tras el primer RED; varias escrituras tropezaron con el control de primer toque del entorno y se reintentaron sin cambiar el contenido.

## 7. Compatibilidad con datos históricos

Las filas de `certificados_numero_oficial` conservan sus cinco columnas; los cuatro campos nuevos quedan NULL y la fecha de expedición NULL; la completitud no cambia con la política por defecto. `down()` elimina las columnas nuevas y conserva el domicilio. Los certificados históricos siguen validándose con la regla `DomicilioCoincide` del motor sin cambios.

## 8. Riesgos pendientes

(a) Mientras SEDEQ no confirme el alcance, el catálogo seguirá exigiendo el certificado a todos aunque los requisitos de Básica lo condicionen; (b) la fuente del Código Urbano fue una copia no oficial; (c) `hud-revision` (`ContenidoGeneralRevision`) debe mostrar los campos nuevos; (d) para que `NoRequerido` pueda alcanzarse, otros documentos municipales tendrán que capturar su domicilio (fuera de alcance); (e) no se investigaron otros municipios.

## 9. Confirmación de alcance

No se implementó OCR, LLM, geocodificación, consultas al catastro, trámite municipal, corrección automática de domicilios ni cambios al domicilio principal del plantel; no se tocaron las reglas de Protección Civil ni la vigencia del Dictamen de Uso de Suelo; no se integró `hud-revision`; no se reestructuraron los documentos por nivel. No se hizo push, merge ni despliegue, y no se modificó ninguna base de datos compartida.
