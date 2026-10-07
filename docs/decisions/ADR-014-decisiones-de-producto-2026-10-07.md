# ADR-014 — Decisiones de producto del 2026-10-07 y trabajo derivado

**Estado:** resuelto el 2026-10-07 (dueño del proyecto). Reúne dos decisiones que no tenían archivo PENDIENTE propio y la lista de trabajo que salió de ADR-008 a ADR-013.

## Decisiones nuevas

### Borrar la cuenta de un solicitante que ya tiene trámites

Hoy falla: `solicitantes.user_id` no tiene `ON DELETE`. **Decisión:** se permite borrar la cuenta solo si ningún trámite se ha enviado a SEDEQ (todos los niveles `en_captura`); en ese caso se borran los trámites junto con la cuenta (misma limpieza que `EliminarTramite`, incluidos los archivos). Si algún trámite ya se envió, se rechaza con un mensaje claro. Se descartan "bloquear siempre" y "anonimizar".

### Número de escritura del poder del representante legal (Formato de Solicitud)

**Decisión:** se agrega un campo opcional `numero_escritura_poder` a `personas_morales` y al formulario del responsable legal moral; el Formato de Solicitud lo imprime en "Acreditación del Representante Legal mediante escritura pública número". Cambio de esquema autorizado (migración nueva, DDL actualizado).

## Decisiones tomadas en los demás archivos

Ver `ADR-008` (municipios como catálogo de 18), `ADR-009` (1 plantel : 1 solicitante), `ADR-010` (capacidad: bloquea el envío, acervo cuenta todo el material, Educación Física por clave estable), `ADR-011` (origen de la magnitud en código por `clave`), `ADR-012` (`condicion_grado` y tres reglas de personal), `ADR-013` (relectura del inmueble dentro de WS-7).

## Trabajo derivado (aún sin hacer)

1. **Municipios**: tabla `municipios` + FK en `planteles` y `certificados_numero_oficial`, selector en Paso 1 y en el certificado, motor documental sin normalizar texto de municipio, sembrar 18 filas, actualizar DDL.
2. **1 plantel : 1 solicitante**: hacerlo cumplir en `IniciarTramiteNuevo`, limpiar el plantel de desarrollo con escuelas de más de un solicitante, ajustar la prueba `test_dos_solicitantes_distintos_obtienen_escuelas_distintas_en_el_mismo_plantel`.
3. **Borrar cuenta**: regla de arriba (caso de uso en `app/Application`, política, mensaje, pruebas).
4. **Campo del poder**: migración, modelo `PersonaMoral`, DTO/formulario/caso de uso del responsable legal, Formato PDF, pruebas.
5. **Capacidad bloquea el envío**: se hace dentro de WS-7 (`listaParaEnvio` debe incluir las reglas de capacidad que fallan). Pregunta abierta para el plan de WS-7: ¿una regla `no_evaluable` también bloquea?
6. **Acervo bibliográfico**: contar todo el material de las bibliotecas, no solo `libros`.
7. **Educación Física en Secundaria**: coincidir por clave estable en lugar del nombre (`ConstruirDatosCapacidad::ASIGNATURA_EDUCACION_FISICA`).
8. **`condicion_grado`**: migración, DDL, tres reglas sembradas, motor y `ConstruirDatosCapacidad` con los grados ofertados (derivados de `matricula_grados`).
9. **`numero_int`**: validar que sean solo dígitos también en `IniciarTramiteNuevo` (hoy solo la pantalla).

El punto 5 y la relectura del inmueble (ADR-013) se absorben en WS-7; los demás son tareas acotadas que se ordenan después del plan de WS-7.

## Siguen abiertos (requieren a SEDEQ)

`PENDIENTE-umbral-educacion-fisica.md` (incluye P1), `PENDIENTE-perfiles-trabajador-social-prefecto.md`, `PENDIENTE-dictamenes-por-nivel.md`, `PENDIENTE-matriz-espacios-por-nivel.md`. Preguntas redactadas en `docs/reports/2026-10-07-consulta-a-sedeq.md`. Y `PENDIENTE-edicion-hasta-envio.md`, que se cierra con el plan de WS-7.
