# Campos faltantes en los documentos de Paso 2, vigencias y CI (2026-10-12)

**Rama de trabajo:** `master` (cada tarea en su propio worktree, fusionada con `--ff-only`). **Último commit:** `3c9afc9`.
**Origen:** auditoría de los 4 formularios de captura de documentos del plantel (`Paso2Documentos`, ámbito plantel) contra `COMPENDIO_MAESTRO`, `REQUISITOS DE EDUCACIÓN INICIAL` y `REQUISITOS Y PROCEDIMIENTO ... TIPO BÁSICA`: campos huérfanos (en Form/BD sin UI), campos normativos faltantes y una vigencia sin captura.

## Qué se hizo

| # | Commit | Cambio |
|---|---|---|
| — | `aeaa25d` (commit del dueño) | Se quita el campo "Observaciones" del paso "Escritura o acreditación de ocupación del inmueble". Se conserva en Form/DTO/BD (queda `NULL`). |
| 0 | `f286fb8` | `PENDIENTE-dictamenes-por-nivel.md`: nueva pregunta para SEDEQ sobre `ratificado_notario` (el comodato se ratifica ante notario solo en Básica, pero la escritura se captura a nivel plantel). Sin regla de obligatoriedad condicional hasta que se resuelva. |
| 1 | `1b21dda` | Se exponen tres campos que ya existían en Form/DTO/BD: cédula profesional del perito, autoridad del registro (opcionales) y casilla "ratificado ante notario" (solo variante comodato). |
| CI | `73a7825` | El servicio Postgres de CI pasa de la imagen espejo de ECR Public a `postgres:18` de Docker Hub **con credenciales** (`secrets.DOCKERHUB_USERNAME`/`DOCKERHUB_TOKEN`), en los dos bloques `services` (test y coverage). |
| 2 | `532fd08` | Visto Bueno de Protección Civil: `vigencia_max_dias = 30` (seeder + migración de datos), bloque propio con "Fecha de emisión" requerida (`ProteccionCivilForm`, `guardarProteccionCivil`, clave en `CON_DATOS_ESTRUCTURADOS`) y control de vencimiento genérico en `ValidarVigenciaDocumentos`. |
| 3 | `daf8ade` | "Fecha de la escritura" (requerida solo para escritura pública): columna `acreditaciones_ocupacion_legal.fecha_escritura` (migración `2026_10_12_000100`). |
| 4 | `1ca3f06` | "Vigencia de la constancia" (requerida) en la Constancia de Seguridad Estructural: columna `constancias_seguridad_estructural.vigencia_constancia` (migración `2026_10_12_000200`). |
| 5 | `47a20e7` | Dictamen de Uso de Suelo: autoridad que expide, folio y giro que autoriza, los tres requeridos, en una tabla nueva `dictamenes_uso_suelo` (migración `2026_10_12_000300`). |
| 4b | `3c9afc9` | `RegistrarDocumento`: `vigenciaConstancia` alimenta `documentos_plantel.fecha_vigencia`, de modo que la constancia vencida la detecta el mismo control genérico. |

## Decisiones

- **Task 2 no podía hacerse como estaba escrita.** El plan decía "nada más cambia", pero `RegistrarDocumento` solo calcula `fecha_vigencia` si recibe `fechaEmision`, y Protección Civil se subía por la vía genérica `guardarDocumentoSimple`, que no manda fecha. Con solo el `30` en el catálogo, nada habría vencido nunca. Se eligió (dueño) la opción 1: bloque propio con fecha de emisión requerida. El test guarda `test_solo_claves_con_datos_estructurados_de_paso_2_2_tienen_vigencia` lo detecta justamente por esto.
- **Revisión independiente de Task 2 encontró un HIGH real:** `ValidarVigenciaDocumentos::ejecutar()` solo revisaba el dictamen y la constancia por nombre, así que un Protección Civil vencido seguía contando como completo. Se corrigió con un ciclo genérico sobre los documentos del plantel con `fecha_vigencia` vencida (excluye el dictamen, que conserva su mensaje).
- **Task 5 (dictamen) requería decisión de esquema.** El DDL (`ddl_sistema_incorporacion_v3.sql`) no tiene tabla de extensión para el dictamen. El dueño autorizó una tabla nueva `dictamenes_uso_suelo` (mismo patrón que las otras dos tablas de extensión) y los tres campos requeridos. Las columnas son `NULL`-ables en BD; la obligatoriedad está en el formulario.
- **Filas ya capturadas se dejan como están** (decisión recomendada y no objetada): sin fecha de emisión / fecha de escritura / vigencia de constancia / datos de dictamen. No bloquean nada y no vencen; la regla aplica desde la siguiente subida.
- **La vigencia de la constancia ahora vence** (`3c9afc9`) usando `fecha_vigencia` en lugar de agregar una segunda revisión.
- **CI con Docker Hub autenticado** para evitar el límite anónimo (`toomanyrequests: Data limit exceeded`), que ya había roto el job de pruebas una vez en `aeaa25d`.

## Evidencia

- Cada tarea: pruebas nuevas vistas fallar primero por la razón correcta, luego en verde. Suites afectadas al final: Application 365, Livewire 254, Database 117, Unit 202, Validacion 47; Pint limpio; PHPStan 0 errores.
- CI (GitHub Actions) en verde en `73a7825`, `47a20e7` y `3c9afc9` (Test suite, Pint, PHPStan, cobertura y auditoría). El rerun del job fallido en `aeaa25d` pasó, confirmando que era transitorio.
- Migraciones aplicadas a la BD de desarrollo por instrucción del dueño ("primero todas las tareas y luego todas las migraciones"): 4 migraciones, 0 pendientes. Verificación de solo lectura: `vigencia_max_dias = 30`, existen `fecha_escritura`, `vigencia_constancia` y `dictamenes_uso_suelo`.
- Cada tarea tuvo revisor independiente (APPROVE salvo el HIGH de Task 2, ya corregido). No se corrió la suite completa en local sobre el árbol final; sí en CI.

## Trampas encontradas (para no repetir)

- `EnterWorktree` crea el worktree desde `origin/master`, no desde el `master` local: hay que `git reset --hard master` (o rebasar) antes de empezar, o se pierden los commits locales no empujados.
- El `replace_all` de una edición de pruebas alcanzó también una prueba negativa recién escrita (le agregó el dato que debía faltar). Se revisó y se corrigió antes de correr.
- En PowerShell, `--filter=$t[1]` se expande mal: usar `"--filter=$($t[1])"`.
- Pegar un token en el chat lo expone en la transcripción: ocurrió con el token de Docker Hub; se pidió revocarlo y regenerarlo. Los secretos se cargan con `gh secret set NOMBRE` desde una terminal real (en `!` no hay prompt y se guarda vacío).
- El gate de primera edición (GateGuard, plugin ecc) niega la primera edición de cada archivo; en lotes paralelos algunas ediciones se aplican y otras no. Se revisó el orden tras cada denegación.

## Pendiente / no hecho

- **No se vio nada en navegador** (campos nuevos de Paso 2, bloque de Protección Civil, stepper).
- `docs/ddl_sistema_incorporacion_v3.sql` (ignorado por git) no refleja `fecha_escritura`, `vigencia_constancia` ni `dictamenes_uso_suelo`.
- `ratificado_notario` obligatorio para comodato en Básica: esperando la respuesta de SEDEQ (`PENDIENTE-dictamenes-por-nivel.md`).
- Limpieza de `/doctor`: aplicado el recorte de `CLAUDE.md` (sin commit) y `permissions.defaultMode = "auto"` en la configuración de usuario. Quedan por correr `/mcp disable context7` y `/mcp disable 21st`; y no se aplicaron los 17 plugins y ~33 skills sin uso.
- Token de Docker Hub pegado en el chat: revocarlo en Docker Hub y regenerar (el secreto actual de CI es el que se cargó después).
- Modales/diálogos de envío y páginas de responsables de la sesión 2026-10-08 siguen sin veredicto del dueño en navegador.
- WS-7b, WS-6, WS-8, WS-9 siguen pendientes.

## Ajuste posterior (sin commit al guardar)

- **"Responsables por nivel": campo de correo desalineado.** El correo llevaba una línea de ayuda ("Le llegará un enlace…") y el nombre no, así que el input del correo quedaba más abajo. Se agregó una ayuda equivalente a "Nombre completo" y `sm:items-end` a la cuadrícula para que los inputs queden a la misma altura aunque la ayuda se parta en dos líneas. Prueba nueva en `ResponsablesNivelPaginaTest` (vista fallar antes). `ResponsablesNivelPaginaTest` 11/11, carpeta Livewire 255/255, Pint limpio, `npm run build` hecho. Solo se verificó el HTML; el alineado visual lo confirma el dueño en el navegador.
- **Recorte de `CLAUDE.md` (de `/doctor`, sin commit):** 19 615 → 16 575 caracteres; se quitó la lista de stack, el bloque de comandos estándar y el árbol `app/` con conteos desactualizados; se conservaron todas las reglas.