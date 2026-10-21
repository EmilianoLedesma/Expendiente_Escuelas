# WS-7b-2 — Edición de Paso 3 hasta el envío (reporte)

**Fecha:** 2026-10-21 · **Rama:** `ws7b2-edicion-paso3` (commits `07cf6c3` a `36f98a1`, más el de cierre) · **Plan:** `docs/superpowers/plans/2026-10-21-ws7b2-edicion-paso3.md` · **Spec:** `docs/superpowers/specs/2026-10-21-ws7b2-edicion-paso3-design.md` · **Decisión:** `docs/decisions/ADR-019-edicion-hasta-envio.md`

## Qué se hizo
1. **RegistroCompartidoBloqueado** (T1): clase única de consulta (`app/Application/`) que dice si otro nivel del plantel ya capturó datos propios de Paso 3, calculado de los datos. Incluye `bloquearNiveles`, que toma `FOR UPDATE` sobre todas las `escuela_niveles` del plantel, ordenadas por id, como primera sentencia.
2. **RegistrarDatosInmueble editable** (T2): reemplaza campos del plantel y los conjuntos de servicios cercanos y estudios actuales en una transacción; con el registro bloqueado solo rellena nulos.
3. **Pantalla Datos del inmueble precargada** (T3): ya no redirige para el dueño; usa `EstadoPaso3::completado`.
4. **FilaSignificativa y bloqueo en RegistrarInfraestructuraNivel** (T4): una fila totalmente vacía se omite en el alta (el rechazo está solo en `EditarInfraestructuraCompartida`); el bloqueo de niveles reemplaza el `FOR SHARE` de una fila.
5. **EditarInfraestructuraCompartida** (T5): editar y borrar filas compartidas de infraestructura y sanitarios, solo para el dueño (política `editarCompartido`); `ReglasFilaInfraestructura` comparte las reglas con el alta; la edición valida que el tipo ya esté capturado en el plantel (más estricto que la unión de niveles).
6. **Sección «Lo ya capturado» editable** en la página de infraestructura (T6), con guarda para que «Guardar» nunca descarte en silencio cambios sin guardar de esa sección.
7. **Mobiliario: vaciar una cantidad borra la fila** (T7).
8. **Pruebas de caracterización** de plan de estudios, plantilla y matrícula (T8): los casos de uso ya reescribían; no se halló defecto.

## Evidencia
Suite completa en bloques por directorio (`php -d memory_limit=2G`; la corrida única se cae con «Premature end of PHP process»):

| Bloque | Pruebas | Aserciones |
|---|---|---|
| tests/Unit | 241 | 407 |
| Feature/Application | 794 | 2484 |
| Feature/Auditoria | 8 | 13 |
| Feature/Auth | 49 | 216 |
| Feature/Database | 152 | 351 |
| Feature/Filament | 3 | 3 |
| Feature/Livewire | 452 | 1900 |
| Feature/Policies | 44 | 194 |
| Feature/Sedeq | 8 | 71 |
| Feature/Tramite | 75 | 460 |
| Feature/Validacion | 72 | 322 |
| Feature/View | 77 | 725 |
| Feature (raíz, 6 archivos) | 48 | 105 |
| **Total** | **2023** | **7251** |

Todo verde. Pint `--test`: limpio. PHPStan nivel 5 + PHPat: 0 errores. Línea base de 7b-1: 1358 / 4563. No hubo verificación manual en navegador (no verificado).

## Decisiones (del dueño, 2026-10-21)
- Cada sub-paso de Paso 3 abre precargado y editable en captura.
- **Regla de bloqueo:** datos compartidos del plantel (inmueble, infraestructura, sanitarios) se bloquean cuando **otro nivel del plantel** (cualquier escuela, ADR-009) tiene filas en `aulas_nivel`, `mobiliario_nivel`, `personal`, matrícula o plan guardado. Las banderas de `escuela_nivel_pasos` no cuentan. Bloquea cambiar o quitar lo capturado; **no** bloquea agregar un tipo de espacio o categoría de sanitario que el plantel aún no tiene (ADR-005). Se levanta si los datos del otro nivel desaparecen.
- Registros bloqueados con nulos: solo se completan los nulos; `false` y `null` son distintos.
- Editar y borrar filas compartidas de infraestructura: solo el dueño. El responsable por nivel conserva lo de ADR-015.
- Concurrencia: `FOR UPDATE` de todas las `escuela_niveles` del plantel, por id, primera sentencia; `asegurarPlantel` en toda escritura compartida.
- Sin cambios de esquema ni reinicio de banderas de pasos.

## Lo que NO se hizo
- **M2** (una escuela hermana enviada cambia los insumos de reglas): al proyecto 3 (HUD / devolución de SEDEQ, reevaluación contra insumos congelados).
- **Descargas tras el envío:** tarea pequeña aparte.
- **Un responsable por nivel sigue sin ver el inmueble capturado** (ADR-015; se redirige).
- **Plantel reutilizado por otra escuela:** su dueño puede sobrescribir datos del inmueble hasta que algún nivel capture Paso 3.
- Los reportes antiguos conservan el nombre original `PENDIENTE-edicion-hasta-envio` por ser registros inmutables.
- La pantalla de Datos del inmueble, con el registro bloqueado, muestra de solo lectura los campos con valor (falso cuenta como valor) y los conjuntos de servicios/estudios ya capturados; solo quedan editables los campos nulos (corrección de la revisión final).
- `EditarInfraestructuraCompartida` no tiene precondición propia de Paso 2 ni de alcanzabilidad; depende de la compuerta del adaptador (página).

## Menores diferidos que vale la pena conservar
- `bloqueado()` hace hasta 6 consultas `exists` y se repite en cada render de la pantalla; `plantel()` también se consulta varias veces.
- Un `tiene_asta_bandera` nulo se precarga como `false`; al guardar con el registro bloqueado rellenaría el nulo (teórico). En infraestructura, `null→false` en un booleano se lee como cambio (conservador).
- Las filas marcadas para quitar siguen validándose.
- El constructor de `RegistrarInfraestructuraNivel` usa `new RegistroCompartidoBloqueado` por defecto (el revisor preferiría requerido); la prueba de bloqueo no demuestra que sea la primera sentencia de la transacción ni el orden por id.
- Editar y quitar el mismo tipo en una llamada, o `tipoEspacioId` duplicado, no se rechaza en `validar()`; `tipoMaterialId` duplicado da error 500 por la unicidad en BD (previo a 7b-2).
- `getAttributes()` en la comparación de valores crudos necesita comentario.
- Mobiliario: sin prueba de «null para fila ausente no hace nada» ni de cantidad 0. `ConstruirDatosCapacidad` se auditó en la revisión final: mobiliario borrado o infraestructura quitada pasa a `null` (`ConstruirDatosCapacidad.php:121-150,204-215`) y da `no_evaluable`, o `declarada ?? 0` da `no_cumple` (`EvaluadorMobiliario`); ambos bloquean el envío, y `EnviarTramite.php:47` vuelve a ejecutar `evaluar`, así que una bandera vieja no deja pasar datos incompletos.
- `edicionPendiente` solo se muestra por toast; `categoriasCapturadas` es público; «quitar» es una casilla, no un botón.
- Escrituras de prueba: la prueba de bloqueo usa solo la modalidad como fuente de bloqueo.
