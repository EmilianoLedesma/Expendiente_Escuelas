# Reporte de Sesión: Rediseño del Checklist Documentos (Paso 2)

**Fecha:** 2026-09-22  
**Rama:** `worktree-paso2-documentos-checklist`  
**Commits asociados:** d6e95bc..5201568 (6 commits de implementación + arreglos, más 1 de reporte)

---

## Resumen de cambios

La propiedad `$fase` de `Paso2Documentos` fue eliminada completamente. El componente ha sido rediseñado de un modelo secuencial (una fase por documento) a un checklist de orden independiente.

### Qué cambió

**Estructura de datos:**
- Eliminada la propiedad `public string $fase` que controlaba el estado del "paso actual" dentro de Paso 2
- Introducida `public array $archivos` (array asociativo keyed por `$clave` de documento: `'ine'`, `'curp'`, ..., `'formato_solicitud'`)
- Cada clave del array `$archivos` aloja el objeto `UploadedFile` correspondiente (o null si no hay captura aún)

**Lógica de captura:**
- Los métodos `guardar*` ahora leen/escriben `$this->archivos[$clave]` en lugar de depender de `$fase` para el dispatch
- `guardarDocumentoSimple(string $clave)` — método parametrizado que maneja 6 documentos simples con validación de vigencia (ine, curp, rfc, etc.)
- `guardarInmueble(array $datos)`, `guardarPlanEstudios(array $datos)`, `guardarFormatoSolicitud()` — métodos estructurados independientes

**Vista (Blade):**
- Transformada de 6 iteraciones secuenciales (por fase) a un checklist con secciones independientes por documento
- Cada sección tiene su propio toggle read-only/editable basado en si existe una captura
- `wire:model` vinculados a `archivos.<clave>` para cada upload
- Query `documentosCapturados()` determina en tiempo real qué secciones muestran captura vs. formulario
- Toggle "Reemplazar" permite al usuario overwrite de un documento ya capturado

**Validación:**
- Siguen siendo validados per-document la vigencia (para documentos de identidad con fecha de expiración)
- La pregunta "¿completo?" ahora es: "¿todos los documentos requeridos están capturados y vigentes?" — consulta única `documentosCapturados()`, no iteración por fase

### Especificación y plan

La especificación y plan de esta tarea están ubicados en:
- **Especificación:** `docs/superpowers/specs/2026-09-22-paso2-documentos-checklist-redesign.md` (no versionado, `.gitignore`)
- **Plan:** `docs/superpowers/plans/2026-09-22-paso2-documentos-checklist-redesign.md` (no versionado, `.gitignore`)

Nota importante: ambos archivos viven en disco del desarrollador pero **no están versionados en git** según la convención del proyecto. Si el almacenamiento local cambia, estos archivos deben recuperarse de otro lugar; git no los tiene. El plan incluye la arquitectura completa de las 5 tareas y sus pre-vuelos de consistencia.

### Conteo de tests

| Punto en el flujo | Cantidad | Cambio |
|---|---|---|
| Baseline (antes de esta rama) | 311 | — |
| Después Task 1 | 313 | +2 |
| Después Task 2 | 316 | +3 |
| Después Task 3 | 316 | +0 (fix round) |
| Después Task 4 | 318 | +2 |
| Final (Task 5: verificación) | **318** | +7 neto |

Todos los 318 tests pasan. Pint y PHPStan limpios.

### Barrido grep (Task 5)

Búsqueda de referencias rezagadas a `->fase` y `'fase'` en el ámbito de `Paso2Documentos`:

```
Resultados encontrados:
- Paso2Responsable.php: 4 referencias (FUERA DE SCOPE — propiedad $fase independiente, sin tocar)
- Paso2ResponsableTest.php: 3 referencias (FUERA DE SCOPE — tests de Responsable, no Documentos)
- PHPStan cache: referencias en archivos de caché (ignoradas)

En el alcance de Paso2Documentos:
- Paso2Documentos.php: 0 referencias ✓
- Paso2DocumentosTest.php: 0 referencias ✓
- Paso2DocumentosHttpRoundTripTest.php: 0 referencias ✓
- paso2-documentos.blade.php: 0 referencias ✓
```

**Resultado:** Verificación limpia. Cero referencias rezagadas a `$fase` dentro del componente Documentos o sus tests.

### Verificación de suite completa

```
php artisan test:  318/318 PASSING ✓
vendor/bin/pint --test: PASSING ✓
vendor/bin/phpstan analyse --memory-limit=512M: 0 errors ✓
```

---

## Deliberadamente no incluido en este plan

Estos temas quedaron parked o fuera de scope:

1. **Vista de revisión/previsualización de documentos** (más allá de nombre + fecha de captura): Las secciones muestran solo el nombre del archivo y la fecha de última captura; no hay visor de PDF/imagen ni previsualización en el UI. Aplazado para una futura iteración (sería un Task 6 si fuera a hacerse).

2. **Cambios a `php.ini` / límites de subida** (`upload_max_filesize`, `post_max_size`): Ya fueron ajustados en el servidor antes de este plan, fuera de este repositorio. No tocados aquí.

3. **Paso2Responsable, Paso 3, o cualquier catálogo de datos:** Fuera de scope. Paso2Responsable retiene su propia (e independiente) propiedad `$fase` sin cambios. No aplica la redesign del checklist a ningún otro paso.

### Findings Minor parked (resolver después, si es necesario)

Dos hallazgos marked as Minor durante el ciclo de revisiones, sin bloquear pero documentados para futura mejora:

1. **Query no-batched en `documentosCapturados()`** (Task 4 review): La consulta que determina qué documentos están capturados/vigentes actualmente itera 6 consultas separadas (una por tipo de documento) en lugar de una batch JOIN. Es funcional y el costo es negligible (máximo 6 queries × ~1-2ms), pero diverge del patrón de batching que usa `InfraestructuraNivel`. Aplazado sin acción: si en futuro el rendimiento de Paso 2 es crítico, este es un microoptimization fácil.

2. **Nullability docblock para `updated_at`** (Task 4 review): El modelo `DocumentoEscuela` / `DocumentoPlantel` tiene `updated_at` declarado como nullable en la docblock del atributo, pero Eloquent lo establece siempre en la práctica (Laravel lo hace automático). Latente, sin impacto; aplazado para una limpieza de tipos en futuro refactor de modelos.

---

## Conclusión

El rediseño está completo y verificado. La propiedad `$fase` ha sido erradicada de `Paso2Documentos`. El checklist de orden independiente funciona con 318 tests all green, sin regresiones. El código está listo para revisión pre-merge.

**Siguiente paso:** pendiente de que el propietario del proyecto dé el go-ahead para mergear a `master` y pushear.
