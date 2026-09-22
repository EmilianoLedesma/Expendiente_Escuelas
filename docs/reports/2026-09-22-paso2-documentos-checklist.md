# Reporte de Sesión: Rediseño del Checklist Documentos (Paso 2)

**Fecha:** 2026-09-22  
**Rama:** `worktree-paso2-documentos-checklist`  
**Commits asociados:** la rama completa (incluye una ola final de fixes tras revisión de código; ver nota de cierre al final del documento)

---

## Resumen de cambios

La propiedad `$fase` de `Paso2Documentos` fue eliminada completamente. El componente ha sido rediseñado de un modelo secuencial (una fase por documento) a un checklist de orden independiente.

### Qué cambió

**Estructura de datos:**
- Eliminada la propiedad `public string $fase` que controlaba el estado del "paso actual" dentro de Paso 2
- Introducida `public array $archivos` (array asociativo keyed por `$clave` de documento, p. ej. `'ine'`, `'acta_nacimiento'`/`'escritura_poder_facultades'`, `'escritura_inmueble'`, `'dictamen_uso_suelo'`, `'constancia_seguridad_estructural'`, `'formato_solicitud'`)
- Cada clave del array `$archivos` aloja el objeto `UploadedFile` correspondiente (o null si no hay captura aún)

**Lógica de captura:**
- Los métodos `guardar*` ahora leen/escriben `$this->archivos[$clave]` en lugar de depender de `$fase` para el dispatch
- Hay 5 métodos de captura reales:
  - `guardarDocumentoSimple(string $clave)` — método parametrizado, sin formulario estructurado propio (solo el archivo). Su whitelist son las 4 claves "simples": `ine`, `acta_nacimiento`, `escritura_poder_facultades`, `formato_solicitud`. Esa whitelist está además scoped por tipo_persona: se intersecta con `DocumentosCompletos::clavesAplicables($tipoPersona)`, así que una escuela `moral` no puede persistir `acta_nacimiento` (le corresponde `escritura_poder_facultades`) ni viceversa — ver corrección aplicada en la ola final de fixes, abajo.
  - `guardarAcreditacion(...)` — documento `escritura_inmueble`, con `AcreditacionOcupacionForm` (tipo de acreditación, datos notariales/RPP, arrendamiento, etc.)
  - `guardarDictamen(...)` — documento `dictamen_uso_suelo`, con `DictamenUsoSueloForm` (fecha de emisión)
  - `guardarConstancia(...)` — documento `constancia_seguridad_estructural`, con `ConstanciaSeguridadForm` (fecha de emisión, datos del perito)
  - `guardarFormatoSolicitud()` — documento `formato_solicitud`, sin formulario estructurado propio
- No existen `guardarInmueble(array $datos)` ni `guardarPlanEstudios(array $datos)` en este componente — no forman parte de Paso 2.2.

**Vista (Blade):**
- Transformada de 6 iteraciones secuenciales (por fase) a un checklist con secciones independientes por documento
- Cada sección tiene su propio toggle read-only/editable basado en si existe una captura
- `wire:model` vinculados a `archivos.<clave>` para cada upload
- Query `documentosCapturados()` determina en tiempo real qué secciones muestran captura vs. formulario — método de solo lectura que devuelve `{nombreArchivo, subidoEn}` por clave ya capturada; no evalúa vigencia.
- Toggle "Reemplazar" permite al usuario overwrite de un documento ya capturado

**Validación:**
- La vigencia se sigue validando, pero no dentro de los métodos `guardar*` ni de `documentosCapturados()`: vive en `ValidarVigenciaDocumentos`, invocada desde `mount()` (al entrar a la vista) y desde `avanzar()` (llamado al final de cada `guardar*`, una vez que `DocumentosCompletos::clavesPendientes()` reporta cero pendientes). Este flujo no cambió con el rediseño.
- La pregunta "¿completo?" ahora es: "¿todos los documentos requeridos están capturados y vigentes?" — vía `DocumentosCompletos::clavesPendientes()` + `ValidarVigenciaDocumentos`, no iteración por fase

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

### Nota de cierre: ola final de fixes (revisión de código pre-merge)

Tras la revisión final de la rama completa se aplicaron 3 correcciones en un solo commit:

1. **Whitelist de `guardarDocumentoSimple` scoped por tipo_persona:** antes era un array estático hardcodeado (`['ine', 'acta_nacimiento', 'escritura_poder_facultades', 'formato_solicitud']`), lo que permitía que una escuela `moral` persistiera `acta_nacimiento` (clave que no le aplica) y viceversa para `fisica`/`escritura_poder_facultades`. Ahora se intersecta esa lista con `DocumentosCompletos::clavesAplicables($tipoPersona)`. Se agregó `test_guardar_documento_simple_rechaza_clave_no_aplicable_al_tipo_persona`.
2. **Test duplicado eliminado:** `test_sube_ine_y_avanza_a_la_siguiente_clave` era idéntico byte-a-byte a `test_sube_ine_de_forma_independiente_sin_pasar_por_las_demas_claves` y describía un comportamiento (avance de fase) que ya no existe en este rediseño. Eliminado.
3. **Este reporte corregido:** se retiraron afirmaciones fabricadas sobre `guardarDocumentoSimple` (no maneja 6 claves ni valida vigencia internamente — son 4 claves, y la vigencia se valida aparte, en `avanzar()`/`mount()` vía `ValidarVigenciaDocumentos`) y sobre métodos inexistentes (`guardarInmueble`, `guardarPlanEstudios`).

Conteo final de tests tras esta ola: 318 (−1 duplicado eliminado, +1 test nuevo de tipo_persona — neto sin cambio sobre los 318 previos). Ver comandos de verificación ejecutados: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M` — los tres limpios.
