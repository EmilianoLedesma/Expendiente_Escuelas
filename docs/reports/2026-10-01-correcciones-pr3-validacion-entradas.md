# Correcciones a PR #3 (validación y limpieza de entradas) tras integrar `master`

**Fecha:** 2026-10-01
**Rama:** `claude/beautiful-brahmagupta-ask783` (worktree `.claude/worktrees/pr3-fixes`)
**Base:** PR #3 sobre `8f2fb1e`; se integró `master` en `dbf341e` (PR #1 motor documental, ADR-007; PR #2 motor de capacidad y sub-pasos 4-6 de Paso 3; ola de correcciones).
**Suite:** 989 tests tras la fusión, 987 en verde y 2 en rojo (línea base) → 997/997 al terminar · Pint limpio · PHPStan nivel 5 + PHPat: 0 errores.

## 1. Fusión con `master`

`git merge master` dejó dos conflictos, los dos esperados en el reporte original de PR #3:

| Archivo | Resolución |
| --- | --- |
| `app/Livewire/Forms/GestorForm.php` | Se conservan las reglas de PR #3 (`ReglasCaptura`, nombres visibles) y se agrega el campo `curp` de PR #1, ahora con `#[Normalizar(Identificador)]`, `ReglasCaptura::curp(requerido: true)` y el nombre visible "CURP del gestor". |
| `app/Livewire/Tramite/Paso2Responsable.php` | Se conserva el flujo de PR #3 (`validarEnConjunto` y la captura de `DatosInvalidos`) y se agrega `gestorCurp` al DTO, como en PR #1. El `mb_strtoupper(trim(...))` manual de `master` ya no hace falta porque el hook normaliza al capturar. |

Commit de fusión: `246fe34` (automático, sin otros cambios). Todo lo demás está **sin commit**.

**Línea base, antes de corregir nada** (`php -d memory_limit=1G vendor/bin/phpunit`): 989 tests, 987 pasan y 2 fallan. Los dos son las pruebas de guardia de PR #3, que ya detectaban lo que trajo `master`:

- `SinVentanasDelNavegadorTest::test_todo_formulario_livewire_desactiva_la_validacion_nativa`: los formularios de `paso3/matricula`, `paso3/plan-estudios` y `paso3/plantilla-docente` no tenían `novalidate`.
- `SinVentanasDelNavegadorTest::test_los_campos_de_texto_se_validan_al_salir_de_ellos`: 19 campos de PR #1 y PR #2 usaban `wire:model` diferido.

## 2. Hallazgos y correcciones

Cada corrección se escribió primero como test, se vio fallar por la razón correcta y luego se aplicó el cambio mínimo.

### H1. Las vistas de PR #1 y PR #2 no seguían las convenciones de PR #3

- **Evidencia:** las 2 fallas de la línea base.
- **Corrección:** se agregó `novalidate` a los 3 formularios de Paso 3. Se cambió a `wire:model.blur` en los `x-ui.input` de `paso2-documentos`, `paso2-responsable` (CURP del gestor), `paso24-documentos-nivel` (número de títulos), `matricula`, `plan-estudios` y `plantilla-docente`. Se agregó `maxlength` igual a la columna: identidad 200; calle, colonia y municipio 150; número exterior 20; CURP del gestor 18; plantilla 200/100/200/100.
- **Efecto colateral:** `Paso24DocumentosNivelTest::test_la_relacion_del_acervo_exige_y_guarda_el_numero_de_titulos` (de `master`) verificaba el HTML exacto `wire:model="acervoTitulos..."`. Se actualizó a `wire:model.blur=`, que es el cambio buscado; la aserción de comportamiento no cambió.

### H2. Los formularios de PR #1 no limpiaban ni validaban con las reglas compartidas

- **Archivos:** `IdentidadDocumentoForm`, `SituacionFiscalForm` y `NumeroOficialForm`, que dependían de `Application/Validaciones/FormatosIdentificador`.
- **Defectos con test:**
  - `ineForm.curp = ' pegj800101hqtrml09 '` se quedaba en minúsculas, porque el hook aplicaba `Texto` y no `Identificador`.
  - `numeroOficialForm.codigoPostal = '76 000'` no se aceptaba (en Paso 1 sí).
  - Una CURP con mes 13 (`PEGJ801301HQTRML09`) se aceptaba, porque el patrón de PR #1 no revisa la fecha.
  - Los mensajes eran los genéricos (`regex`/`size`), no los de `ReglasCaptura`.
- **Tests:** `ValidacionFormulariosTramiteTest::test_paso2_documentos_normaliza_los_datos_que_compara_el_motor_documental` y `::test_paso2_documentos_los_datos_del_motor_usan_los_mismos_formatos_y_mensajes`.
- **Corrección:**
  - Los tres Forms usan ahora `ReglasCaptura` (`nombrePersona`, `curp`, `rfc`, `texto`, `codigoPostal`), `#[Normalizar]` (Identificador en CURP/RFC, Digitos en CP) y `validationAttributes()`.
  - Se eliminaron los métodos `normalizar()` y sus llamadas en `Paso2Documentos`, porque el hook los cubre.
  - Se borró `FormatosIdentificador`; ya no tiene usos.

### H3. Errores en dos rondas en los guardados de PR #1

- **Defecto:** `guardarIne`, `guardarConstanciaCurp`, `guardarSituacionFiscal` y `guardarNumeroOficial` validaban primero el archivo y después el Form. Es el mismo defecto que PR #3 ya había corregido en 2.1, 2.2 y 2.4.
- **Test:** `ValidacionFormulariosTramiteTest::test_paso2_documentos_los_datos_del_motor_muestran_todos_los_errores_en_una_sola_ronda`. En rojo faltaba `ineForm.nombre`.
- **Corrección:** se usa `validarEnConjunto` y `self::REGLAS_ARCHIVO`.

### H4. El caso de uso aceptaba una CURP de gestor mal formada (paridad de backend, ADR-001)

- **Defecto:** `RegistrarResponsableLegal::validar()` revisaba la CURP y el RFC del titular, pero no `gestorCurp` (campo nuevo de PR #1).
- **Test:** `InvariantesDeCapturaTest::test_responsable_rechaza_la_curp_del_gestor_mal_formada`.
- **Corrección:** se agrega la regla `gestorForm.curp` con `Formatos::esCurp`. Solo se revisa el formato: la obligatoriedad sigue siendo del formulario, porque la columna `gestores.curp` admite nulos y el fixture `CompletaPaso2` crea gestores sin CURP.

### H5. `RegistrarDocumento` guardaba datos tipados sin revisarlos

- **Defecto:** un adaptador que no pase por el formulario podía guardar una CURP, un RFC o un CP mal formados en `credenciales_ine`, `constancias_curp`, `constancias_situacion_fiscal` o `certificados_numero_oficial`. Un valor más largo que la columna (`VARCHAR(18/13/150/200)`) terminaba en un error 500 de base de datos, el mismo tipo de defecto que PR #3 corrigió para los números.
- **Test:** `InvariantesDeCapturaTest::test_documento_rechaza_datos_tipados_mal_formados_sin_escribir_nada`. Comprueba que las claves sean `ineForm.curp`, `constanciaCurpForm.nombre`, `situacionFiscalForm.rfc`, `numeroOficialForm.calle` y `numeroOficialForm.codigoPostal`, y que no se escriba ningún documento.
- **Corrección:** se agregó `validarDatosTipados()` antes de guardar el archivo. Revisa largo y formato (`Formatos`), con las rutas de campo que `Paso2Documentos::intentarRegistrar` ya traduce a `addError`. La obligatoriedad no cambia: se conserva el diseño de PR #1, donde si falta un dato se borra la fila.

### H6. Dos fuentes de verdad para los patrones de CURP y RFC

- **Situación:** existían `Domain/Captura/Formatos` (PR #3), `IdentificadorCoincide::PATRON_CURP/RFC` (PR #1, sin rango de mes/día) y `Application/Validaciones/FormatosIdentificador` (PR #1, que los reexportaba más un CP `\d{5}`).
- **Decisión:** `App\Domain\Captura\Formatos` queda como la única fuente. Se le agregó `RFC` (física o moral) y `esRfc()`. `IdentificadorCoincide::PATRON_CURP = Formatos::CURP` y `PATRON_RFC = Formatos::RFC`. Es una dependencia de Domain a Domain: ADR-001 y PHPat siguen en verde. `ReglasCaptura::rfc()` usa `Formatos::esRfc`.
- **Tests:**
  - `FormatosTest::test_el_motor_documental_usa_los_mismos_patrones_que_la_captura` (en rojo: los patrones diferían).
  - `FormatosTest::test_rfc_acepta_persona_fisica_o_moral` (en rojo: no existía `esRfc`).
  - `IdentificadorCoincideTest` (8/8) y las pruebas de motor y validación final siguen en verde sin cambios.
- **Cambio de comportamiento, deliberado:** el motor ahora marca `formato_invalido` en una CURP o RFC con fecha imposible (mes 13, día 32), que antes aceptaba. Esos valores ya no pueden entrar por los formularios. Todos los fixtures existentes usan fechas válidas, y la suite no cambió por esto.

## 3. Revisado sin defecto (con evidencia)

- **Hook `LimpiarYValidarAlCapturar`:**
  - Archivos: `_finishUpload` asigna objetos `TemporaryUploadedFile`, no texto, así que no se normalizan. `archivos.*` no tiene regla en `rules()`, así que no hay `validateOnly`.
  - Arreglos (`personas.*`, `grupos.*`, `salas.*`): se limpian con `Texto`. PR #2 no define `rules()` en esas páginas, así que no hay validación en vivo y su caso de uso sigue siendo la autoridad.
  - `#[Locked]`: Livewire lanza la excepción antes de que corra el hook.
  - Form objects anidados: cubiertos por tests.
  - Contraseñas: excluidas.
  - Errores: no se tragan, se combinan con `setErrorBag`.
- **Paridad de casos de uso (d):** todas las páginas que llaman a un caso de uso que lanza `DatosInvalidos` lo capturan y lo mapean con `addError`: Paso 1, 2.1, 2.2, 2.4, Inmueble, Infraestructura, Plan, Plantilla y Matrícula.
- **Normalización (e), probada a mano con el normalizador:**
  - `peña800101ab1` → `PEÑA800101AB1` (válido como RFC de persona física).
  - `ñ&ab…` → `Ñ&AB…`.
  - NBSP, tabulador y caracteres de ancho cero se eliminan de los identificadores.
  - `+52 (442) 123-4567` → `4421234567`.
  - Correo en minúsculas.
  - Texto con solo espacios → `''` → `null` en el DTO.
  - Caso límite: `+52 1 442…` (prefijo móvil antiguo) queda con 11 dígitos y **se rechaza con mensaje**; no se corrige en silencio. Se dejó así a propósito.
- **Idioma `es` (f):** con la fusión, la suite completa pasa, incluidas las pruebas de autenticación de Breeze. Ninguna aserción en inglés se rompió.
- **`.blur` (g):** en Livewire 3.8.7 (`dist/livewire.js`, `getModifierTail`), `wire:model.blur` quita el modificador del `x-model` interno, así que `$wire` se actualiza con cada tecla y solo el envío al servidor espera al blur. Un envío con Enter sin perder el foco manda el valor actual. No se encontró ningún flujo afectado.

## 4. Riesgos conocidos, no corregidos

- **El hook es global.** Hoy no hay recursos de Filament ni campos de texto con `wire:model.live`. Si se agregan, una `Textarea` sin `#[Normalizar(TextoLargo)]` perdería los saltos de línea, y un campo `.live` recortaría el espacio final mientras el usuario escribe. Cuando aparezca el primer caso habrá que limitar el hook a `App\Livewire\*` o declarar la normalización en el campo.
- **`IdentidadDocumentoForm` se usa dos veces** (INE y Constancia de CURP), así que los dos comparten el nombre visible "nombre como aparece en el documento".
- **Las páginas de PR #2 no validan al salir de cada campo.** Validan al enviar, en el caso de uso, con mensajes en español. Llevarlas a `ReglasCaptura` sería reescribir código que funciona, así que no se hizo.

## 5. Verificación

- Archivos afectados: `FormatosTest` 33/33, `InvariantesDeCapturaTest` 7/7, `ValidacionFormulariosTramiteTest` 21/21, `SinVentanasDelNavegadorTest` 3/3, `IdentificadorCoincideTest` 8/8, `RegistrarDatosDeDocumentoTest` 7/7, `Paso24DocumentosNivelTest` 32/32.
- Suite completa: **997/997** (2692 aserciones).
- `vendor/bin/pint --test`: limpio.
- `vendor/bin/phpstan analyse`: 0 errores.
- No se leyó el DDL de la copia principal: los largos de columna se tomaron de las migraciones del worktree (`2026_09_30_000000_create_datos_de_documentos_tables.php`, `2026_09_30_000001_add_curp_to_gestores_table.php`), que son una copia exacta del DDL.
- No se hizo commit ni push (salvo el commit automático de la fusión). `docs/progress.md` no se modificó.
