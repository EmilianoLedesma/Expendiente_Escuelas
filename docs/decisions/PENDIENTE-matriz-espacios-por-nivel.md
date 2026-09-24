# PENDIENTE — Matriz de aplicabilidad de tipos de espacios por nivel educativo

**Estado:** abierto
**Abierto:** 2026-09-21
**Requiere:** confirmación normativa de SEDEQ
**Bloquea:** nada hoy. `TiposEspaciosSeeder` siembra una matriz utilizable; esta
pregunta afecta su exactitud, no su existencia.

## Pregunta

¿A qué niveles de Educación Básica aplica cada tipo de espacio, y cuáles son
obligatorios?

## Lo que COMPENDIO sí afirma explícitamente

- `filtro_recepcion` → solo Inicial ("Inicial incluye Filtro/Recepción, que el genérico no tiene").
- `subdireccion`, `atencion_publico`, `bodega`, `sala_maestros` → catálogo genérico de Básica, no Inicial.
- `biblioteca` → solo Primaria y Secundaria.
- `taller`, `laboratorio_polifuncional`, `salon_usos_multiples`, `auditorio`, `cocina`, `comedor`, `sala_artes` → "instalaciones especiales exclusivas de Básica", no Inicial.

Estas filas están sembradas exactamente así y no son objeto de esta pregunta.

## Filas inferidas (COMPENDIO guarda silencio)

Sembradas para los 4 niveles de Básica con `obligatorio = false`:

`direccion`, `oficinas_administrativas`, `control_escolar`, `cubiculo`,
`cancha_usos_multiples`, `chapoteadero`, `arenero`, `zona_juegos`,
`areas_verdes`, `area_recreo`, `campo_futbol`, `otra_recreativa`,
`otra_especial`.

Razón de la inferencia: mostrar un espacio capturable que no aplica es
recuperable (el solicitante lo deja vacío); ocultar uno que sí aplica
haría la captura imposible. El sesgo elegido es el recuperable.

## Obligatoriedad

`niveles_tipos_espacios.obligatorio` está sembrado en `false` para **todas**
las filas, incluidas las explícitas. COMPENDIO enumera espacios capturables
pero en ningún punto marca alguno como obligatorio; la columna existe en el
DDL para cuando SEDEQ confirme esa matriz. Ninguna regla de obligatoriedad
fue inventada.

## Qué cambiaría al resolverse

Solo datos de `TiposEspaciosSeeder` (constante `APLICABILIDAD_EXPLICITA` y
los `obligatorio`). Ningún cambio de esquema, ningún cambio de código de
captura: el formulario de sub-paso 2 se genera desde estas filas.

---

## Actualización — 2026-09-24: Tres espacios movidos a Inicial

**Cambio:** `salon_usos_multiples`, `cocina`, `comedor` pasaron de la sección "inferidas" 
a la sección "explícitas" (línea 19 anterior del COMPENDIO), aplicables ahora a Inicial.

**Evidencia (WS-3.1):**
- Requisitos Inicial §(ñ): "Sala de usos múltiples: 1.2 m² per child"
- `reglas_validacion` en BD: `inicial.superficie.sala_usos_multiples` (1.2 m²/niño)
- `mobiliario_conceptos` seeded: tres filas de SUM en el catálogo, incluyendo "Silla para niño" y mobiliario de comedor

Estos espacios son **explícitamente requeridos** en Inicial, no inferidos. 
Se han movido en `TiposEspaciosSeeder::APLICABILIDAD_EXPLICITA` (WS-3.1 commit).

**Impacto:** La matriz ahora refleja más precisamente la normativa. 
La pregunta principal (obligatoriedad) sigue abierta.
