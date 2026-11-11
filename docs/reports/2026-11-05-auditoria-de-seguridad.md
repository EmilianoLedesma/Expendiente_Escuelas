# Auditoría de seguridad (2026-11-05)

Auditoría de solo lectura del código en `master` `9984a0e`, hecha con el skill de Cloudflare `security-audit` (instalado en `~/.claude/skills/security-audit`). Los resultados completos viven fuera del repositorio, en `C:\Users\Emili\security-audit-skill\sedeq-incorporacion\run-1\` (`REPORT.md`, `NEEDS-VALIDATION.md`, `findings.json`, `coverage-ledger.json`).

## Alcance y método

- Perfil `standard`, modo completo, presupuesto de 40 invocaciones de agente; se usaron 29.
- Alcance: `app-laravel/{app,routes,config,database,resources/views,bootstrap,composer.json,.env.example}` y `.github`. Fuera: `vendor`, `node_modules`, `storage`, `tests`, `docs`.
- Solo código fuente: este equipo (Windows) no tiene sandbox del sistema operativo, así que no se ejecutó nada del proyecto. Lo que requiere ejecutar o abrir un programa queda como "por validar", no como confirmado.
- 23 unidades de cobertura: 20 cubiertas y 3 con candidato. Tres rondas de crítica de cobertura agregaron 5 unidades; el crítico final distinto confirmó 8 afirmaciones de alto riesgo y no pidió más trabajo.

## Resultado

**Cero vulnerabilidades confirmadas.** El diseño de autorización resistió todas las rutas revisadas: políticas en `/tramite/*`, comprobaciones en los casos de uso de `/sedeq/*`, rol admin dentro de cada acción destructiva y `CuentaActiva` cubriendo también `/livewire/update`.

Dos pistas quedan **por validar** (sin severidad, no confirmadas):

1. `mail-markdown-injection-free-text-in-system-mail`: los nombres que escribe el usuario (registro, invitado, quien invita) se insertan con `{{ }}` en plantillas Markdown de correo. `{{ }}` solo escapa HTML, y `Markdown::withSecuredEncoding()` no está activado, por lo que `[texto](https://...)` probablemente se convierta en enlace en un correo legítimo enviado a un tercero. Rastro: `register.blade.php:27`, `InvitarResponsableNivel.php:66`, `VerificarCorreo.php:21`, `correo/invitacion-nivel.blade.php:2`.
2. `reportes-csv-formula-injection-unneutralized-fputcsv`: `ConsultarReportes::csv()` escribe nombre de cuenta, calle, colonia, municipio, nombres de terna y correo del plantel con `fputcsv` sin neutralizar `=`, `+`, `-`, `@`. Un admin que abra el CSV en Excel podría evaluar una fórmula. Rastro: `Perfil.php:38`, `ConsultarReportes.php:198` y `:72`, `Reportes.php:72`.

## Cómo se corrigió (rama `seguridad-endurecimiento`, ya aplicado)

Las dos correcciones son pequeñas y valen sin importar el resultado de la validación:

- Correo: llamar `Illuminate\Mail\Markdown::withSecuredEncoding()` en `AppServiceProvider::boot()` y agregar una regla de nombre que rechace `[ ] ( ) < >` en registro e invitación.
- CSV: en `csv()`, anteponer una comilla simple a toda celda de texto que empiece con `=`, `+`, `-`, `@`, tabulador o retorno de carro, antes de `fputcsv`.

Pruebas de validación acotadas (sin tráfico real): renderizar `VerificarCorreo` e `InvitacionResponsableNivel` con un nombre `[x](https://example.invalid)` en una base de pruebas; exportar un nombre `=HYPERLINK(...)` y abrirlo en Excel/LibreOffice; en Mailtrap, registrar una cuenta de prueba con sintaxis Markdown en el nombre.

## Pistas descartadas

- Middleware persistente de Livewire: incluye `ExigeRol`, `Authorize` y `Authenticate`, y las propiedades de id de los componentes SEDEQ son `#[Locked]`.
- `/sedeq/revision/{escuelaNivel}` no tiene `can:`, pero `AccesoSedeq`, `ConsultarRevision` y `DecidirPaso` comprueban acceso antes de leer o escribir.
- La cuenta del seeder (`test@example.com` / `password`) solo es solicitante: no concede más que el registro abierto.
- `serve.log` y `serve.err.log` no están versionados.
- Registrar la cuenta de la víctima y esperar a que SEDEQ la invite no funciona: las invitaciones nunca promueven ni verifican una cuenta existente.

## Endurecimiento sugerido (no son hallazgos)

Sesión y acceso: sin throttle en registro y recuperación de contraseña; el cambio o restablecimiento de contraseña no invalida otras sesiones; `Auth::login` sin `Session::regenerate()` en el registro; `SESSION_SECURE_COOKIE` sin definir.
Invitaciones: una persona puede usar las invitaciones para enviar correos a cualquier dirección sin tope; `InvitarRevisor` reactiva sin revisar `archivado_at`; tokens de activación en texto plano dentro de `jobs`.
Defensa en profundidad: `#[Locked]` en las propiedades `Escuela`/`EscuelaNivel` que faltan; casos de uso que confían en que el llamador autorice (importante si se crea la API de la etapa 3); `whereNumber()` en rutas de Paso 2 y 3; `canAccess` explícito en recursos de Filament; sin reautenticación en acciones destructivas de admin.
Archivos: sin `Content-Type` ni `nosniff` explícitos al servir PDFs; los documentos de plantel sobreviven a la purga de un trámite (decisión documentada).
Configuración: `DatabaseSeeder` sin guarda de entorno; `.env.example` con `APP_DEBUG=true`, `APP_ENV=local`, `SESSION_ENCRYPT=false`; `laravel/tinker` en `require`; acciones de GitHub fijadas por etiqueta y no por SHA; `composer audit` y `npm audit` solo informativos.

## Límites

No se vio la configuración de producción (`.env`, TLS, proxy, supervisión de la cola, permisos de almacenamiento, transporte de correo). Nada se ejecutó. Las rutas excluidas no se revisaron.

## Estado

Las dos correcciones y el endurecimiento de código se aplicaron en la rama `seguridad-endurecimiento` (pruebas enfocadas, Pint y PHPStan limpios; la suite completa la verifica CI). La base de datos de desarrollo no se tocó.

Aplicado además: guarda de entorno en `DatabaseSeeder`; ids de ruta numéricos acotados (404 en vez de 500); tope por IP en registro (10/hora) y recuperación (5/minuto); cierre de las otras sesiones y rotación del token de recuerdo al cambiar o restablecer la contraseña, y regeneración de sesión al registrarse; `#[Locked]` en los siete modelos públicos que faltaban (con una prueba que lo exige); `InvitarRevisor` rechaza cuentas archivadas; `VerificarAccesoResponsable` ignora cuentas archivadas; `Reportes::$porPagina` solo acepta 10, 25 o 50; `ApiInfoMexico` exige `https://`; los PDF se sirven como `application/pdf` con `nosniff`; `canAccess` solo-admin en los cuatro recursos y dos páginas de Filament; comentarios de producción en `.env.example`. La prueba de purga con admin degradado ahora comprueba el 403 de la lista (el rechazo del caso de uso sigue cubierto en `PurgarTest`).

No se hizo (requiere diseño del dueño o red): reautenticación o 2FA en acciones destructivas, tope de invitaciones por solicitante, token en texto plano dentro de `jobs`, mover `tinker` a dependencias de desarrollo (cambia el lockfile), fijar las acciones de GitHub por SHA, y comprobar la propiedad dentro de `EnviarTramite` (la política de ruta ya la decide, ADR-004).
