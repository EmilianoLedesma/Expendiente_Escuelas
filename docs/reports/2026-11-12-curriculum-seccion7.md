# 2026-11-12 — Proyecto Educativo, sección 7: Currículum Ampliado (Recursos Socioemocionales)

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD.

## Qué se construyó

Cinco ámbitos, uno por pantalla (como la sección 5), en el orden del dueño: Práctica y Colaboración Ciudadana, Educación para la Salud, Actividades Físicas y Deportivas, Educación Integral en Sexualidad y Género, Actividades Artísticas y Culturales.

| Campo por ámbito | Regla |
|---|---|
| ¿La trabajan? | radio Sí / No, sin opción marcada de inicio, obligatorio |
| Justificación | texto, 100 a 250 palabras, obligatorio **también con "No"** (la etiqueta del dueño dice "justifique la selección o no") |

- Siguiente valida solo el ámbito actual y no escribe; Anterior no valida ni pierde lo tecleado. Guardar (último ámbito) valida los cinco y guarda todo o nada; si falla, no escribe y lleva al primer ámbito con error.
- Contador de palabras y tope duro: el mismo componente `x-ui.textarea-palabras` de las otras secciones.
- Base: una fila por `escuela_nivel`; por ámbito `*_trabaja` BOOLEAN y `*_justificacion` TEXT, con CHECK de que van juntas o ambas nulas, y justificación no vacía. El DTO usa `'si'`/`'no'` (lo que manda el radio) y el caso de uso lo convierte a booleano.

## Archivos

Migración `2026_11_12_000200_create_proyecto_educativo_curriculum_table.php`; `ProyectoEducativoCurriculum`, `DatosCurriculumProyecto`, `ReglasCurriculumProyecto`, `GuardarCurriculumProyecto`, `CurriculumProyectoCapturada`, `SeccionesProyectoEducativo::curriculum()` (índice 6); componente `ProyectoEducativoSeccion` (`siguienteCurriculum`, `anteriorCurriculum`, reutiliza `bloque`, `guardarTexto`, `irAlPrimerBloqueConErrores`), su vista y `ProyectoEducativoIndice` (Completa/Pendiente).

Sin archivos: la limpieza al purgar o quitar un nivel ya la cubre el `ON DELETE CASCADE`.

## Evidencia

- `GuardarCurriculumProyectoTest` (27) y `Paso3CurriculumProyectoTest` (12): **ambos se vieron fallar antes de implementar** (clases y tabla inexistentes; el marcador "Contenido por definir" en la página).
- Verde, enfocadas: `tests/Feature/Application/ProyectoEducativo` y los tests Livewire de Proyecto Educativo (Curriculum, Presentación, ProyectoEducativo, Filosofía, Objetivos, Evaluación, Enfoque, PAEC, RequierePaso2Completo): 187 + 43 pasan. Pint limpio, PHPStan 0.
- Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 8 (índice 7, 13 secciones restantes, "Perfil de Ingreso").
- Fallo propio corregido: dos intentos de ajustar un `assertCount` quedaron en la línea equivocada; la prueba lo detectó.

## Qué NO se hizo / límites

- **No se vio en navegador** (contador por `entangle`, radios, navegación Siguiente/Anterior).
- La migración se corrió en la base de desarrollo a petición del dueño ("After each section run migrations").
- Un guardado con "No" exige la misma justificación de 100 a 250 palabras; es una lectura de la etiqueta del dueño, confirmada en el diseño.
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa (secciones 1 a 6): `ResumenEliminacionNivel` no cuenta los textos del Proyecto Educativo; esta sección la repite.
- `docs/progress.md` no se tocó (regla de un solo escritor).

## Siguiente

Revisión en navegador de las secciones 4 a 7 y sección 8 (Perfil de Ingreso).
