# ADR-019 — Edición de pasos anteriores hasta el envío

**Estado:** resuelto el 2026-10-21 (WS-7b-1 cerró Paso 1 y Paso 2; WS-7b-2 cerró Paso 3)
**Fecha:** 2026-09-24
**Relacionado:** `PENDIENTE-inmueble-relectura-datos.md` (D8), ADR-002, ADR-004, ADR-005, brief de remediación §WS-7

## Contexto

Al probar la aplicación, el propietario pidió poder regresar desde cualquier paso posterior (por ejemplo, Infraestructura) a cualquier paso anterior (por ejemplo, Responsable legal) y encontrar sus datos ya capturados, precargados.

Estado actual (`master` en `ce3a154`):

- La barra de progreso ya enlaza a los pasos anteriores.
- Casi todos los pasos muestran lo capturado en solo lectura:
  - **Preregistro:** los datos del plantel no se editan.
  - **Responsable legal:** resumen de solo lectura. `RegistrarResponsableLegal` no hace nada si ya existe una fila, así que un formulario editable descartaría los cambios en silencio.
  - **Niveles:** no se modifican una vez elegidos.
  - **Datos del inmueble e Infraestructura:** solo lectura una vez capturados.
- **Documentos** es el único paso que ya permite reemplazar lo capturado.

## Decisiones del propietario (2026-09-24)

1. **Alcance: "editar hasta el envío".** Todos los pasos anteriores muestran formularios precargados y editables mientras el expediente siga en captura (`en_captura`). Se mantienen dos límites:
   - El **domicilio del plantel** sigue siendo de solo lectura, por la regla del COMPENDIO de que un cambio de domicilio requiere autorización previa de la Dirección de Educación.
   - Una edición que invalide pasos posteriores (por ejemplo, cambiar de persona física a moral cambia el documento de identidad aplicable) marca esos pasos otra vez como pendientes.
2. **Secuencia: se integra en WS-7** y se respeta el orden del plan. WS-7 deja de ser solo "corrección de datos del plantel" (D8) y pasa a ser "edición hasta el envío" para todos los pasos. Va después de:
   - **WS-4**, porque reutiliza sus consultas de Application;
   - **WS-5**, porque Paso 2.4 y los documentos nuevos también deben poder editarse.

## Preguntas abiertas (a resolver en el plan de WS-7)

- **Cambio de tipo de persona** con documentos ya subidos: ¿se conservan los documentos que dejan de aplicar, se ocultan o se eliminan?
- **Quitar un nivel** que ya tiene datos de Paso 3 o de Paso 2.4: ¿se prohíbe, se pide confirmación y se borran sus datos, o se archiva?
- **Terna de nombres:** ¿es editable sin restricción?
- **Datos del plantel compartidos** entre varios niveles o escuelas del mismo solicitante: se aplica la regla D8 (editable solo mientras todos los niveles que usan el plantel sean del mismo solicitante y sigan en captura).
- **Qué significa "envío":** hoy no existe un paso de envío. Hasta que exista, todo expediente está en captura y todo sería editable. El plan debe definir el disparador que cierra la edición.
- **Motor de Validación:** si se implementa como evaluación única al final de Paso 3, una edición posterior debe invalidar su resultado.

## Cierre

Renombrado de `PENDIENTE-edicion-hasta-envio.md` a ADR-019 al cerrar WS-7b-2, con las respuestas de 7b-1 y 7b-2 abajo.

## Relación con la validación final (2026-09-30, rama `spike/validation-engine-eval`)

`ADR-007-motor-validacion-hechos.md` agregó el último paso del flujo, **"Validación final"**: evalúa los documentos contra lo capturado, guarda un PDF y dice si el trámite puede enviarse (cualquier `no_cumple` lo impide). El envío en sí sigue siendo de WS-7. Al implementarlo:

- El disparador de envío debe **volver a ejecutar** `App\Application\Validaciones\EjecutarValidacionFinal` y rechazar si `listaParaEnvio` es falso. No debe confiar en la última evaluación guardada, porque los datos pueden cambiar después.
- Si se abre la edición de Paso 2.1, los errores en datos **declarados** (no en documentos) se vuelven corregibles. Hoy la validación final solo puede mandar a corregir documentos.

## Actualización (2026-10-01)
La lista "Estado actual" de arriba (`master` en `ce3a154`) es anterior a varios cambios: ya existen el Paso 2.4 (documentos por nivel, WS-5b) y los sub-pasos 4–6 de Paso 3 (Plan de estudios, Plantilla docente y Matrícula, PR #2), además de la página de Validación final (PR #1), que vuelve a ejecutarse con "Validar de nuevo" y que el envío deberá volver a ejecutar en lugar de confiar en una fila anterior. Sigue sin existir el paso de envío ni la transición de estado. El alcance y las preguntas abiertas de este archivo no cambian.

## Respuestas (WS-7b-1, 2026-10-14) — Paso 1 y Paso 2

- **Cambio de tipo de persona con documentos subidos:** los documentos que dejan de aplicar se conservan, ocultos y sin contar; al volver al tipo anterior cuentan otra vez. No se exige volver a subir INE/CURP (D2); la página avisa y la validación final detecta una identidad que no corresponde.
- **Quitar un nivel:** permitido. Con datos, una confirmación lista lo que se elimina; sin datos, sin diálogo. Quitar el último nivel se rechaza (para descartar todo: «Eliminar trámite»).
- **Terna de nombres:** editable sin restricción, en el lugar.
- **Datos del plantel:** solo teléfono y correo; el domicilio sigue de solo lectura. Se rechaza si cualquier trámite del plantel ya se envió.
- **Datos del plantel compartidos:** ver ADR-009.
- **Formato de Solicitud (D1):** un cambio en lo que imprime descarta el Formato firmado de cada nivel.
- Queda abierto para 7b-2: guardas de una sola escritura de Paso 3 y filas antiguas que no pueden enviarse. Lo pendiente de 7b-2 se resuelve abajo.

## Respuestas (WS-7b-2, 2026-10-21) — Paso 3

Spec: `docs/superpowers/specs/2026-10-21-ws7b2-edicion-paso3-design.md`. Reporte: `docs/reports/2026-10-21-ws7b2-edicion-paso3.md`.

- **Alcance:** cada sub-paso de Paso 3 abre precargado y editable mientras el trámite esté en captura. Datos del inmueble deja de ser de una sola escritura; infraestructura y sanitarios permiten corregir y quitar filas ya capturadas; mobiliario permite vaciar una cantidad (borra la fila).
- **Datos compartidos del plantel (inmueble, infraestructura, sanitarios):** se bloquean en cuanto **otro nivel del plantel** (de cualquier escuela, ADR-009) ha capturado datos propios de Paso 3: filas en `aulas_nivel`, `mobiliario_nivel`, `personal`, matrícula o plan de estudios guardado. Las banderas de `escuela_nivel_pasos` no cuentan. La regla se calcula de los datos y vive en una sola clase (`RegistroCompartidoBloqueado`). El bloqueo cubre al nivel que escribió el dato y a los demás, y se levanta si los datos del otro nivel desaparecen.
- **Qué bloquea y qué no:** bloquea cambiar o quitar lo ya capturado; **no** bloquea agregar un tipo de espacio o una categoría de sanitario que el plantel aún no tiene (ADR-005).
- **Filas antiguas con nulos:** un registro bloqueado permite completar solo los campos nulos; los que ya tienen valor no cambian. `false` y `null` son distintos.
- **Quién edita:** editar y borrar filas compartidas de infraestructura es solo del dueño del trámite (política `editarCompartido`). Un responsable por nivel conserva lo que ADR-015 le da (agregar filas de su nivel).
- **Concurrencia:** todo escritor de datos compartidos bloquea todas las `escuela_niveles` del plantel con `FOR UPDATE`, ordenadas por id, como primera sentencia, y después evalúa la regla; llama a `TramiteEditable::asegurarPlantel`.
- **Fila de infraestructura totalmente vacía:** se rechaza; borrar es una acción explícita.
- **Banderas de `escuela_nivel_pasos`:** no se reinician; `EnviarTramite` reevalúa todo.
- **Fuera de 7b-2:** el hueco M2 (una escuela hermana enviada cambia los insumos de reglas) pasa al proyecto 3 (HUD/devolución de SEDEQ, reevaluación contra insumos congelados). Las descargas tras el envío son una tarea pequeña aparte.
