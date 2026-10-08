# Consulta a SEDEQ — preguntas normativas abiertas (2026-10-07)

Preguntas que el equipo del proyecto no puede resolver por sí mismo: dependen de la norma o de la práctica de SEDEQ. Redactadas para una persona no técnica. Cada una indica qué archivo de `docs/decisions/` la origina y qué cambia en el sistema según la respuesta. Mientras no haya respuesta, el sistema usa el valor provisional indicado.

## 1. Maestro de Educación Física: ¿desde cuántos alumnos?

*(`PENDIENTE-umbral-educacion-fisica.md` y P1 de `ADR-010`)*

Para que una escuela de Preescolar, Primaria o Secundaria esté obligada a tener un maestro de Educación Física:

1. ¿El número de alumnos debe ser **de 60 en adelante** (una escuela con exactamente 60 ya lo requiere) o **mayor a 60, es decir, 61 o más**?
2. ¿Esa cifra se mide sobre la **capacidad instalada** (cuántos alumnos caben físicamente) o sobre la **matrícula** (cuántos están inscritos)?

Nuestros documentos internos usan ambas redacciones. **Provisional hoy:** 61 y matrícula. Cambiar el umbral es un cambio de datos, no de código.

## 2. Trabajador Social y Prefecto en Secundaria: ¿qué perfiles se aceptan?

*(`PENDIENTE-perfiles-trabajador-social-prefecto.md`)*

El Profesiograma de Secundaria no define las carreras ni los documentos aceptados para estos dos cargos, que sí son obligatorios con más de 60 alumnos. ¿Qué carreras o documentos de acreditación se aceptan para (a) Trabajador Social (¿Lic. en Trabajo Social, Psicología, otras?) y (b) Prefecto? **Provisional hoy:** no se verifica el perfil de estos dos cargos.

## 3. Dictámenes de Protección Civil y Uso de Suelo: ¿por nivel o por plantel?

*(`PENDIENTE-dictamenes-por-nivel.md`)*

Los Requisitos de Educación Básica dicen que ambos deben estar "emitidos para el nivel educativo que se ofertará". ¿Se presenta **uno por cada nivel** que pide la escuela, o basta **uno por plantel** que cubra todos los niveles solicitados? Y: el certificado de número oficial, ¿es obligatorio para todos los niveles o solo en ciertas condiciones (Requisitos §16)? **Provisional hoy:** uno por plantel; el certificado se pide siempre.

## 4. Espacios por nivel: ¿cuáles aplican y cuáles son obligatorios?

*(`PENDIENTE-matriz-espacios-por-nivel.md`)*

Para cada tipo de espacio (dirección, oficinas administrativas, control escolar, cubículo, cancha de usos múltiples, chapoteadero, arenero, zona de juegos, áreas verdes, área de recreo, campo de futbol, otros), ¿a qué niveles de Básica (Inicial, Preescolar, Primaria, Secundaria) aplica y cuáles son **obligatorios**? **Provisional hoy:** los aplicamos a los cuatro niveles y ninguno es obligatorio, para no impedir la captura de un espacio que sí exista.

## Cómo se usan las respuestas

- Respuesta 1 → ajuste de datos en `ReglasValidacionSeeder` (y, si es capacidad, cambio de magnitud en el motor).
- Respuesta 2 → filas nuevas en `perfiles_profesionales`.
- Respuesta 3 → si es "por nivel", migración que mueve los dos documentos al ámbito `escuela_nivel` (mismo patrón que WS-5b); las escuelas que ya completaron el Paso 2.4 tendrían que recargarlos.
- Respuesta 4 → solo datos en `TiposEspaciosSeeder`.

Al recibir cada respuesta, el archivo `PENDIENTE-*` correspondiente se renombra a `ADR-0NN-*` en el mismo commit que aplica el cambio.
