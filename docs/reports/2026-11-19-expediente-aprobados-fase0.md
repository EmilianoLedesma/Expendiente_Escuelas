# Expediente de aprobados y marco legal público (fase 0)

Fecha: 2026-11-19. Rama `worktree-expediente-aprobados` (worktree `.claude/worktrees/expediente-aprobados`), sin commit ni push.

## Qué se pidió y por qué

Cuando un nivel pasa a `aprobado` desaparecía de todas las listas del personal (el tablero solo lista `en_revision`) y el solicitante lo veía como «En revisión». El dueño pidió: un aviso por correo, una vista para el solicitante, un módulo del supervisor (un Expediente de todas las escuelas, hoy solo con las aprobadas) y una página pública con todas las leyes y acuerdos.

Antes de construir se investigó el procedimiento posterior (ver `2026-11-19-investigacion-posaprobacion.md`). Hallazgo que cambió el diseño: «aprobado» es la **revisión documental aprobada**, no la incorporación. Falta la visita de inspección, la resolución y el acuerdo.

## Decisiones del dueño (2026-11-19)

1. Los plazos legales solo se **muestran**, no se imponen (sin desechamiento automático).
2. Se conserva la CCT provisional `PROV-22-…`; se sustituirá por la clave real que dé la USEBEQ.
3. La devolución se limita a una sola ronda (la ley permite una prevención). **No se implementó en esta fase.**
4. `aprobado` se conserva como clave en la base de datos y se muestra «Revisión documental aprobada».
5. Preguntas a SEDEQ: lazo de visita (Acuerdo 243), qué firma el acuerdo y su folio, CCT con USEBEQ (el dueño buscará una API), qué pasa si SEDEQ no resuelve a tiempo, si Inicial requiere autorización.

## Qué se construyó

- **Solicitante.** `ResumenTramiteDTO` trae `aprobado` (todos los niveles que mira) y `nivelesAprobados`. «Mis trámites» muestra «Revisión documental aprobada»; el hub muestra una alerta de éxito con el siguiente paso (visita de inspección) y, si solo algunos niveles están aprobados, los nombra. Nuevo componente `x-tramite.aviso-sin-acuerdo` en «Mis trámites» y en el hub: sin acuerdo no se puede inscribir, cobrar ni anunciar «RVOE en trámite» (LGE art. 150, LEEQ art. 56).
- **Correo.** `RevisionSinObservaciones` conserva clase y disparo (evento `RevisionAprobada`); cambia asunto y texto: revisión documental aprobada, siguiente paso y recordatorio de que aún no hay acuerdo. Sigue sin mencionar «SEDEQ».
- **Expediente del supervisor.** `Application\Revision\ListarExpedientes` (+ `FilaExpediente`), solo supervisor, un estado ofrecido por ahora (`ESTADOS = ['aprobado']`; otro estado lanza `DatosInvalidos`), búsqueda por escuela o nivel, excluye escuelas archivadas. Página Livewire `Sedeq\Expedientes` en `/sedeq/expedientes`, enlace «Expedientes» en la barra del supervisor. Cada fila abre `sedeq.tramite` (solo lectura existente), que ahora muestra una sección «Historial» **solo al supervisor** (`ConsultarHistorialTramite::ejecutar($id, incluirAcciones: false)`: sin la bitácora de acciones del admin).
- **Marco legal público.** Ruta `/marco-legal` sin sesión, vista `marco-legal.blade.php`, contenido en `config/marco_legal.php` (etapas del procedimiento y 4 grupos de leyes, acuerdos, procedimientos e inmueble). Solo enlaza textos que se leyeron; las entradas sin enlace verificado lo dicen. Enlace en el pie de todas las páginas y en el aviso de «sin acuerdo».

## Evidencia

- Pruebas nuevas: `ResumenTramiteAprobadoTest` (3, rojo antes del código), `TramiteAprobadoVistaTest` (4, rojo antes), `ListarExpedientesTest` (6), `Livewire/Sedeq/ExpedientesTest` (7), `MarcoLegalTest` (7), ampliaciones en `ObservacionesEnTramiteTest` (rojo antes), `ConsultarHistorialTramiteTest` y `TramiteLecturaTest` (rojo antes).
- **No se vieron en rojo** `ListarExpedientesTest`, `ExpedientesTest` ni `MarcoLegalTest`: se escribieron antes del código pero la primera corrida fue después de implementar.
- Verdes: `Application/Tramite` 247 (con `-d memory_limit=1G`; con 128 MB se agota la memoria), `Tramite` 87, `Application/Revision` 188, `Validacion` 71, `Notifications` 9, `Livewire/Sedeq`+`Auditoria`+`Admin` 158, `View` y las nuevas 100; Pint limpio; PHPStan 0.
- `tests/Feature/Livewire/Tramite` completo muere con «Premature end of PHP process» (límite conocido); la suite completa la verifica CI. Nada visto en navegador.

## Actualización tras revisar en navegador (mismo día)

- **Filtro de estado funcional.** `ListarExpedientes::ESTADOS` ofrece Aprobados (predeterminado), En revisión, Con observaciones y Rechazado (nunca el borrador). Un estado inválido en la URL vuelve a Aprobados. La columna pasa a «En este estado desde» (`FilaExpediente::desde`).
- **Tarjetas por escuela.** `/sedeq/expedientes` deja la tabla: una tarjeta por escuela con sus niveles (fecha y revisor) y un solo botón «Ver expediente»; resumen «N escuelas · M niveles».
- **Pestañas en el expediente.** `sedeq.tramite` se organiza en pestañas (Plantel y responsable, Documentos, una por nivel, Validación y, solo supervisor, Historial) con Alpine; todos los paneles siguen en el DOM. Encabezado con el estado de cada nivel. Teclado: flechas izquierda/derecha. Nada de esto se ha visto en navegador por el agente.

## Qué queda fuera (a propósito)

Visita de inspección, resolución y acuerdo, CCT real, plazos, devolución de una sola ronda, nuevos estados, formatos oficiales (acuerdo, oficio, acta, dictamen negativo, solicitud de alta de la USEBEQ: no se localizaron; pedirlos a SEDEQ/USEBEQ sin datos personales).

## Pendiente

- Revisión en navegador del Expediente, del hub aprobado, del aviso y de `/marco-legal` (responsivo 375 px).
- Verificar los artículos del marco legal contra el Periódico Oficial; la copia de LPAEQ (Corregidora) y las entradas sin enlace.
- Commit y push solo cuando el dueño lo pida; luego espera silenciosa de CI.
