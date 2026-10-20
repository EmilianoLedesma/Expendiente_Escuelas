# PENDIENTE — Constancia de Seguridad Estructural y de Ocupación (Paso 2.2)

**Estado:** abierto. Requiere respuesta de SEDEQ (norma) y contraste con el `COMPENDIO_MAESTRO` que se está actualizando por separado.
**Origen:** refactor de la captura de `constancia_seguridad_estructural` (informe `docs/reports/2026-10-20-constancia-seguridad.md`, especificación local `docs/superpowers/specs/2026-10-20-constancia-seguridad-design.md`).
Este archivo **no se renombra**: ninguna de las preguntas de abajo está resuelta.

## Qué se decidió provisionalmente (técnico, no jurídico)

- Se **retiró** la regla bloqueante "el año del registro del perito debe coincidir con el año de emisión" (`ValidarVigenciaDocumentos`). Ningún artículo del Reglamento de Construcción para el Municipio de Querétaro la respalda; solo aparece en los requisitos de SEDEQ de Básica *citados por el Compendio* (no se pudieron leer los PDF). Rechazaba registros vigentes que cruzan de año y aceptaba registros ya vencidos al emitir.
- El dato histórico `perito_registro_vigencia` se conserva sin reinterpretar (semántica no determinada) y nada lo evalúa.
- Los datos nuevos del registro profesional son explícitos: `perito_registro_fecha` (registro o refrendo) y `perito_registro_vencimiento`.
- Un vencimiento del registro anterior a la emisión de la constancia **avisa pero no bloquea**.
- La vigencia de la constancia es la que declara el documento (`tipo_vigencia` + `vigencia_constancia`); sin fecha expresa no se inventa ninguna (no se aplica el plazo de 5 años).
- La calidad del firmante se captura (`dro`, `corresponsable_seguridad_estructural`, `otra`) sin presumir que todas son suficientes.

## Fundamento verificado (Municipio de Querétaro, Reglamento de Construcción, texto con reformas hasta 2022)

| Art. | Contenido | Certeza |
|---|---|---|
| 120 | Las escuelas son construcción Grupo A | Confirmado, solo ese municipio |
| 274 a) | Escuelas requieren Visto Bueno de Seguridad y Operación | Confirmado, solo ese municipio |
| 277 | Grupo A: Constancia de Seguridad Estructural registrada ante la Secretaría, renovada cada cinco años, en la que un Corresponsable en Seguridad Estructural hace constar las condiciones de seguridad | Confirmado el texto; aplicabilidad a cada trámite pendiente |
| 279 | Cambio de uso: VoBo en 90 días naturales con, "en su caso", Constancia de Seguridad Estructural | Confirmado |
| 289 fr. III | El DRO da su responsiva al suscribir el VoBo de Seguridad y Operación | Confirmado |
| 291, 293–295, 297 | Registro y refrendo de DRO y Corresponsables; padrón anual con listas de colegios | Confirmado el texto |

No se encontraron reformas posteriores a la Gaceta Municipal No. 14 (29-mar-2022); la búsqueda no es concluyente. El Reglamento **no nombra** un instrumento "Constancia de Seguridad Estructural y de Ocupación": ese nombre es de SEDEQ.

## Preguntas para SEDEQ (no se asumen)

1. ¿Qué documento exactamente es la "Constancia de Seguridad Estructural y de Ocupación": la CSE del art. 277, el Visto Bueno de Seguridad y Operación del art. 274, la autorización de ocupación del art. 275, o un formato propio de SEDEQ?
2. ¿Se presenta en **un solo PDF** con la carta responsiva del DRO (criterio actual del Compendio) o son documentos separados? Si son separados, ¿la carta responsiva y el VoBo se agregan al catálogo (ámbito `plantel`)?
3. La regla "año del registro = año de emisión": ¿existe en la normativa o requisitos vigentes de SEDEQ? ¿Qué mide exactamente (año de registro, de refrendo o de vigencia)? ¿Aplica a todos los niveles (Inicial, Básica, Media Superior, Superior) o solo a Básica?
4. ¿Qué calidades aceptan como firmante: solo Corresponsable en Seguridad Estructural (art. 277), también el DRO, otras?
5. ¿Exigen que el registro profesional esté **vigente a la fecha de emisión**? ¿Con qué evidencia (constancia de refrendo, padrón)? Hoy solo se avisa.
6. ¿Para inmuebles fuera del Municipio de Querétaro qué documento y qué autoridad aceptan? El Reglamento estudiado no aplica a los otros municipios.
7. ¿Aceptan una constancia sin fecha de vencimiento? ¿Cuentan los cinco años del art. 277 desde la emisión, y de ser así debe el sistema calcular el vencimiento?
8. ¿Requieren el folio de la constancia? Hoy es opcional.
9. ¿Se aplica la Constancia a toda escuela, o solo a las construcciones Grupo A / ciertos supuestos (art. 279 "en su caso")?

## Para el contraste con el nuevo Compendio

- La sección que dice que el año del registro "debe coincidir" con el año de emisión debería quedar como *Documentado, no contrastado* o *Pendiente de SEDEQ*, no como regla confirmada.
- El Compendio ya reconoce que el criterio de "un solo PDF" con la carta responsiva lo debe confirmar SEDEQ; este refactor lo conserva.
- Si el Compendio confirma alguna regla con fundamento vigente y aplicable (p. ej. registro vigente a la emisión), convertir el aviso en bloqueo es un cambio pequeño y aislado en `ValidarVigenciaDocumentos`.

## Si se resuelve

Renombrar a `ADR-0NN-constancia-seguridad-estructural.md` en el mismo commit que la resuelva.
