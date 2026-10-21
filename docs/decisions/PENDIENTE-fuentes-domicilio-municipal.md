# PENDIENTE: fuentes de domicilio en los documentos municipales

**Estado:** abierto (2026-10-20). **Tipo:** normativo (SEDEQ) y de producto (dueño del proyecto).
**Origen:** `docs/reports/2026-10-20-paso2-integracion-end-to-end.md` §5.

## Pregunta

¿Qué documentos municipales deben capturar el domicilio del inmueble como dato estructurado, para que la condición documentada del Certificado de Número Oficial («solo cuando los documentos municipales traen domicilios diferentes», Educación Básica) pueda evaluarse con evidencia?

## Situación verificada

`App\Application\Documentos\FuentesDomicilioMunicipal` documenta la matriz: hoy **ningún** documento municipal, aparte del propio certificado, guarda un domicilio estructurado (Dictamen de Uso de Suelo, acreditación de la ocupación, Seguridad Estructural y Protección Civil guardan autoridad, folio y vigencia, no domicilio). El certificado no puede ser evidencia de su propia exención. Por eso, aunque SEDEQ configure el alcance (`documentos.numero_oficial.alcance_condicional`), la aplicabilidad queda «Pendiente de determinar» (exigible).

## Qué se necesita decidir

1. Si el alcance de la condición se confirma (niveles, municipios).
2. Qué documentos deben traer domicilio y si es obligatorio u opcional en su captura.
3. Si una «discrepancia confirmada» la declara una persona revisora de SEDEQ (no existe ese flujo).

## Qué queda preparado

El servicio y el evaluador ya cuentan cada documento una vez, excluyen el certificado, comparan el municipio por clave del catálogo cuando ambas fuentes la tienen y conservan los documentos usados como evidencia en `ResultadoAplicabilidad::evidencia`. Agregar una fuente es marcarla en la matriz y leer su domicilio en `para()`. No se usan OCR ni modelos de lenguaje.
