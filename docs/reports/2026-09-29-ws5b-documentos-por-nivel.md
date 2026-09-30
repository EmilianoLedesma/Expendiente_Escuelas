# WS-5b — Paso 2.4 "Documentos por nivel"

**Rama:** `worktree-worktree-ws5b-documentos-por-nivel` (base `cd91ffe`, cabeza `fb8cbba`, 13 commits).
**Plan:** `docs/superpowers/plans/2026-09-29-ws5b-documentos-por-nivel.md` · **Spec:** `docs/superpowers/specs/2026-09-29-ws5b-documentos-por-nivel-design.md`.
**Resultado:** suite 546 → 671 pruebas, Pint y PHPStan limpios. Revisión final (opus): sin hallazgos Críticos ni Importantes.

## Qué se construyó

Un paso nuevo entre 2.3 y Paso 3 por cada nivel educativo de la escuela: captura de turno y tipo de alumnado, descarga del Formato de Solicitud (PDF por nivel), y carga del Formato firmado, recibo de pago de derechos, acervo bibliográfico e inventario.

1. **Catálogo (T1):** el Formato de Solicitud pasa de ámbito escuela a `escuela_nivel`; filas nuevas para los documentos por nivel; migración O1; limpieza en 2.2 (conteos: física 8 / moral 10 / gestor 9) y M1 (fila genérica en el blade para claves desconocidas).
2. **Modelos y lecturas (T2):** modelos de nivel, `DocumentosNivelCompletos`.
3. **`RegistrarDatosNivel` + `EstadoPaso24` (T3):** captura de turno/tipo con bloqueo `lockForUpdate` dentro de la transacción.
4. **`RegistrarDocumento` rama nivel (T4):** recibo con monto, re-verificación del Formato bajo el bloqueo contra el turno/tipo visto antes del bloqueo, borrado de archivos en `afterCommit`.
5. **PDF y rutas (T5):** Formato por nivel, rutas de PDF y descarga (`can:view`).
6. **Página `Paso24DocumentosNivel` (T6):** `can:update`, `#[Locked] $escuelaNivel`.
7. **Resumen y numeración (T7):** sección en `ResumenTramite`; Inicial: documentos_nivel 5, inmueble 6, infraestructura 7, mobiliario 8.
8. **Compuerta y barrido de pruebas (T8):** `EstadoPaso3::puedeAcceder` exige 2.4 completo (punto único para los llamadores) más redirección explícita en `CompuertaPaso3`; helper `CompletaPaso24` en 12 archivos de prueba.

## Decisiones del dueño

1. Cambiar turno o tipo de alumnado **descarta** el Formato ya cargado (con confirmación en la UI).
2. Una escuela completa aterriza en `tramite.resumen`.
3. **G0:** la migración `000004` borra las filas placeholder `recibo_pago_derechos_plantel` solo cuando el dueño corre `migrate` en dev, tras un conteo de solo lectura.

## Evidencia

- Ejecución por subagentes: implementador → revisor separado → ronda de correcciones → re-revisión acotada. Modelos: sonnet (T1, T2, T5–T8), opus (T3, T4 y revisión final).
- T8: corrida en rojo de 85 pruebas antes del barrido (83 seguras + 2 posibles), un solo commit en verde.
- Revisión final: Minor 1 corregido en `fb8cbba` (el motivo del hub decía "Completa primero: Datos del inmueble" cuando quien bloqueaba era 2.4; ahora apunta a Documentos del nivel; prueba vista en rojo y luego en verde).
- Verificado por el controlador: 671/671 en la cabeza de la rama.

## Lo que se dejó sin hacer (triado en la revisión final)

- **Volumen de consultas:** `EstadoPaso24::etapaFaltante` hace ~11 consultas; el hub con 4 niveles ~150–200. Arreglo: sobrecarga con `EscuelaNivel` o memoización en `DocumentosNivelCompletos::clavesAplicables`. Aceptado por el plan ("revisar si se vuelve lento").
- `$rutaAnterior` se lee fuera de la transacción (archivo huérfano bajo carrera); 2.2 tiene el mismo hueco, arreglar ambos juntos.
- Barrido `#[Locked]` en los componentes Livewire hermanos (Paso3/*, Paso2Documentos): Livewire 3 ya rechaza el set directo de propiedades de modelo y `can:` persiste; tarea pequeña aparte.
- Estado de la sección en el hub: `pendiente` en vez de `en_curso` cuando solo hay recibo/acervo cargados (cosmético).
- Menores: regex de monto con `\z`, `in_array(null)` redundante, solapamiento `$titulos`/`$nombres`, filtro `aplica_persona` sin prueba, prueba de `fecha_vigencia` en rama nivel, ventana mágica `substr` en prueba del hub, sin prueba de concurrencia con dos conexiones.
- Fuera de alcance: maquetación del PDF (WS-5c), edición hasta el envío (WS-7), dictámenes por nivel (ver `docs/decisions/PENDIENTE-dictamenes-por-nivel.md`).
- Limitación conocida aceptada: no hay vínculo entre el PDF descargado y el escaneo cargado; lo cubre la revisión humana de SEDEQ.

## Pasos del dueño (no ejecutados)

1. G0: conteo de solo lectura de filas `recibo_pago_derechos_plantel` en dev → `php artisan migrate` → `db:seed --class=TiposDocumentosSeeder`.
2. Efecto de despliegue: las escuelas con Paso 3 avanzado regresan a 2.4 hasta completarlo.
3. `git push` de `master`; agregar `Paso24DocumentosNivel` a CLAUDE.md; refrescar la copia del COMPENDIO; llevar las preguntas a SEDEQ.
