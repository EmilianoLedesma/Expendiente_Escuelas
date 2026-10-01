# Validación y limpieza de todas las entradas del usuario

**Fecha:** 2026-09-30
**Rama:** `claude/beautiful-brahmagupta-ask783` (sobre `master` 8f2fb1e, ya con WS-5b / Paso 2.4)
**Suite:** 546 → 774/774 · Pint limpio · PHPStan nivel 5 + reglas PHPat: 0 errores

## Problema

Se pidió asegurar la limpieza de los datos: validar todas las entradas en frontend y backend, con mensajes en español junto al campo (no ventanas emergentes), y corregir errores de código baratos.

Diagnóstico (con evidencia):

1. **Mensajes en inglés.** `config/app.php` tenía `locale = 'en'` y no existía `lang/es`: un correo inválido mostraba *"The correo electronico field must be a valid email address."*
2. **Ventanas emergentes del navegador.** Ningún `<form wire:submit>` tenía `novalidate`: un `type="email"` o `type="number"` inválido disparaba la burbuja nativa del navegador (en el idioma del navegador) antes de que Livewire validara.
3. **Nada se limpiaba.** Livewire desactiva `TrimStrings` y `ConvertEmptyStringsToNull` para sus peticiones (`Livewire\Mechanisms\HandleRequests`, líneas 72-76). `" Centro "` o una CURP en minúsculas llegaban tal cual a la base de datos.
4. **Sin validación de formato.** CURP, RFC, código postal y teléfono solo tenían `max:N`; el RFC/CURP se pasaba a mayúsculas **después** de validar.
5. **Desbordes de columna → error 500.** Varias reglas numéricas no tenían tope y la columna sí: `metros_totales NUMERIC(10,2)`, `sanitarios.superficie_m2 NUMERIC(8,2)`, `distancia_valor NUMERIC(6,2)`, `numero_titulos INTEGER`. Un valor grande era un `SQLSTATE[22003]` (la ventana de error de Livewire), no un mensaje en el campo. Verificado con test: `value "40000" is out of range for type smallint`.
6. **Validación solo al enviar.** Las reglas vivían dentro de los métodos `guardar()` y los campos de Paso 2 usaban `wire:model` diferido: el mensaje aparecía hasta el envío.

## Qué se hizo

### Capas (ADR-001 respetado; PHPat en verde)

| Capa | Archivo | Rol |
| --- | --- | --- |
| Domain | `app/Domain/Captura/Formatos.php` | Formato estructural de CURP, RFC (física 13 / moral 12), código postal, teléfono (10 dígitos), correo. Sin Illuminate. |
| Application | `app/Application/Captura/ReglasCaptura.php` | Reglas reutilizables con mensaje en español (`curp()`, `rfcPersonaFisica()`, `codigoPostal()`, `telefono()`, `correo()`, `nombrePersona()`, `fechaPasada()`, `fecha()`, `entero()`, `decimal()`); topes = tipo de la columna del DDL. |
| Application | `NormalizadorEntrada`, `Normalizacion`, `#[Normalizar]` | Limpieza: recortar, colapsar espacios, quitar caracteres invisibles; mayúsculas para identificadores; minúsculas para correo; teléfono sin separadores ni `+52`; `TextoLargo` conserva párrafos. Nunca descarta contenido que la validación deba ver (una letra en un teléfono se conserva para que la regla la rechace). |
| Livewire | `app/Livewire/Hooks/LimpiarYValidarAlCapturar.php` | Hook global: al actualizarse cualquier propiedad, la limpia y valida **solo ese campo** (mensaje al salir del campo). Contraseñas excluidas (mismas exclusiones que `TrimStrings`). |
| Livewire | `app/Livewire/Concerns/ValidaEnConjunto.php` | Muestra todos los errores en una sola ronda (antes: primero los del componente y, al corregirlos, los del Form object). |
| Application (backend) | `IniciarTramiteNuevo`, `RegistrarResponsableLegal`, `RegistrarDatosInmueble`, `RegistrarInfraestructuraNivel` | Invariantes para cualquier adaptador que se salte el formulario (futura API): formato de CP/teléfono/correo/CURP/RFC, nombres NOT NULL por subtipo, topes de columna. Lanzan `DatosInvalidos` con la misma ruta de campo que el formulario. |

### Frontend

- `novalidate` en todos los `<form wire:submit>` (15 formularios, incluidos auth y perfil). Test que lo exige para cualquier formulario futuro.
- `wire:model.blur` en todos los campos de texto del trámite (test que lo exige).
- `maxlength` igual al límite de la regla/columna; `inputmode`/`autocapitalize` en CP, CURP, RFC.
- Mensajes con el nombre visible del campo (`lang/es/validation.php` → `attributes`; los Form objects en su propio `validationAttributes()`).

### Reglas nuevas por formulario (además de formato y topes)

- Fechas de nacimiento, emisión, poder, escritura, contrato y pago: no futuras, no antes de 1900, formato `Y-m-d`.
- Vigencia del contrato no anterior a la fecha del contrato.
- Terna: las tres propuestas deben ser distintas (sin distinguir mayúsculas).
- Latitud y longitud van en par (una sola coordenada no ubica nada).
- Nombres de persona: solo letras (con acentos), espacios, puntos, apóstrofos y guiones.
- Registro/login/restablecer contraseña/perfil: el correo se normaliza a minúsculas (Breeze rechazaba `Juan@Mail.com` con "must be lowercase").

## Errores de código baratos corregidos

1. **Docblocks apilados** en `InfraestructuraNivel::categoriasSanitarios()`: PHP solo toma el último, así que la descripción se perdía.
2. **Etiqueta "Calle y número"** en Paso 1, con un campo "Número exterior" justo abajo: el número se capturaba dos veces.
3. **RFC/CURP en mayúsculas después de validar** (Paso2Responsable): ahora el hook normaliza antes.
4. **`before_or_equal:today`** en `ReciboPagoForm` (WS-5b): en español producía *"…anterior a today."*
5. **Errores en dos rondas** en Paso 2.1, 2.2 y 2.4 (ver `ValidaEnConjunto`).
6. **Fixture de test con CURP de 17 caracteres** (`perj850620mqrrn01` en `Paso2ResponsableTest`), aceptada hasta hoy por falta de validación de formato.
7. **Registro que rechazaba correos con mayúsculas** en vez de normalizarlos.

## PR #1 (ADR-007, motor de validación documental), sin fusionar

Entradas nuevas que agrega: `ineForm` y `constanciaCurpForm` (nombre, CURP), `situacionFiscalForm` (nombre, RFC), `numeroOficialForm` (calle, número exterior, colonia, municipio, CP) y `gestorForm.curp`. Ya traen regex de formato, pero:

- Sus mensajes saldrían en inglés sin esta rama. **Con esta rama**, los genéricos salen en español y los nombres visibles caen al respaldo de `lang/es` (`nombre`, `CURP`, `RFC`, `calle`, …); lo correcto es añadir `validationAttributes()` a cada Form (dos forms tienen un "nombre" que significa cosas distintas).
- El hook global ya limpia sus campos al integrarse; sus `normalizar()` manuales quedan redundantes. Faltaría `#[Normalizar(Normalizacion::Identificador)]` en `curp`/`rfc` y `Digitos` en `codigoPostal`.
- `NumeroOficialForm` valida **antes** de recortar (`trim` se aplica al construir el DTO): con el hook eso deja de importar.
- Deriva a evitar: el patrón de CURP de `IdentificadorCoincide::PATRON_CURP` y el de `Formatos::CURP` deben ser uno solo. `Formatos` es un subconjunto estricto (agrega rango de mes/día), así que al fusionar conviene que `IdentificadorCoincide` y `FormatosIdentificador` apunten a `App\Domain\Captura\Formatos`.
- Conflicto esperado al fusionar: `Paso2Documentos.php` y `GestorForm.php` (ambas ramas los tocan). Mecánico.

No se tocó la rama del PR: los cambios anteriores son para aplicarlos cuando se integre, sobre esta base.

## 3FN

No se agregaron tablas ni columnas: todo es de validación y limpieza, cero migraciones. Observaciones:

- `planteles.municipio`/`localidad` y los domicilios de PR #1 (`certificados_numero_oficial`) son texto libre. Es la mayor fuente restante de datos sucios ("Qro", "Querétaro", "QUERETARO"). Un catálogo de municipios (FK) sería la forma normalizada, pero es cambio al DDL: queda en `docs/decisions/PENDIENTE-catalogo-municipios.md`.
- PR #1 guarda en `certificados_numero_oficial` el domicilio *tal como lo imprime el documento*: no es redundancia con `planteles`, porque es el hecho a comparar, no una copia. Correcto en 3FN.

## Qué NO se hizo

- No se revisó en navegador (CLAUDE.md pide autorización para usarlo); la cobertura es por tests de componente y de vistas.
- No se valida dígito verificador de CURP/RFC ni se consulta RENAPO/SAT: solo estructura.
- No se agregó a `docs/progress.md` (lo escribe el controlador al fusionar).
- Entorno local: `composer install` requirió `--prefer-source` y un `dist` temporal de `phpstan/phpstan` (el proxy del entorno bloquea `codeload.github.com`); `composer.lock` quedó sin cambios.
