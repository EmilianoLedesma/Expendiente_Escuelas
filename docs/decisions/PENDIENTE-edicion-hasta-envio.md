# PENDIENTE — Edición de pasos anteriores hasta el envío

**Estado:** abierto (alcance decidido; reglas de detalle pendientes para el plan de WS-7)
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

Este archivo se renombra a `ADR-00N-edicion-hasta-envio.md` en el mismo commit que implemente las reglas en WS-7, con las respuestas a las preguntas abiertas.
