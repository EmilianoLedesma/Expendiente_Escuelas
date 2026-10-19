# ADR-017 — Catálogo geográfico nacional importado de una API

**Estado:** resuelto el 2026-10-19 (dueño del proyecto). **Relación:** amplía `ADR-008-catalogo-municipios.md`.

## Decisión

La ubicación de la notaría de la escritura (Paso 2.2) deja de ser texto libre y pasa a dos listas desplegables dependientes: **entidad federativa** y **municipio**. Las opciones salen de dos tablas locales, `entidades_federativas` y `municipios`, que se llenan **importando** el catálogo de la API `api_info_mexico` (https://github.com/angellof18/api_info_mexico). La interfaz **no guarda ni escribe** listas de entidades o municipios.

- **Tablas:** `entidades_federativas` (`clave`, `nombre`, `abreviatura`) y `municipios` (`entidad_federativa_id`, `clave`, `nombre`, único por entidad y clave). Las claves son las del catálogo de origen. Catálogo **nacional** (32 entidades), no solo Querétaro.
- **Importación, no consulta en línea:** `php artisan geografia:importar` trae `/api/estados` y `/api/estados/{id}/municipios` de la URL configurada (`services.api_info_mexico.url`, por omisión `https://api-info-mexico.netlify.app`), descarta las claves no geográficas del origen (0 NO ESPECIFICADO, 88 NO APLICA, 99 SE IGNORA) y hace upsert en una transacción. La aplicación **nunca** llama a la API al atender una solicitud: una caída de la API no afecta a las personas usuarias.
- **Importación segura:** primero trae y revisa todo; si hay menos de 32 entidades o alguna entidad no trae municipios, o la API falla o responde con otra forma, **no escribe nada** y avisa. Repetirla no duplica. No borra lo que el origen deje de listar.
- **Capas:** puerto `FuenteCatalogoGeografico` y caso de uso `ImportarCatalogoGeografico` en Application, cliente `ApiInfoMexico` en Infrastructure, lectura `ListarCatalogoGeografico` para los selectores (ADR-006).
- **Almacenamiento en la acreditación:** `acreditaciones_ocupacion_legal.notario_entidad_federativa_id` y `notario_municipio_id` (FK, nullable). `RegistrarDocumento` exige que el municipio exista y pertenezca a la entidad aunque se evite Livewire. La columna anterior `notario_localidad` (texto) **se conserva** para las filas ya capturadas y no se vuelve a escribir desde la pantalla. El campo sigue siendo opcional.

## Por qué importar y no consultar la API en línea

La API es un proyecto personal sin licencia declarada, sin fuente de datos nombrada y sin URL pública documentada (el README solo explica cómo correrla localmente; la URL de Netlify aparece en la sección "About"). Depender de ella en cada solicitud pondría el formulario a merced de su disponibilidad y de cambios no anunciados. Importarla a tablas propias la convierte en una carga de datos puntual y auditable.

## Riesgos y límites (no resueltos)

- **Procedencia de los datos:** los nombres vienen en mayúsculas y la estructura (claves 0, 88, 99, `EFE_KEY`, `MUN_KEY`) coincide con el catálogo de INEGI, pero la API no lo declara. **No se verificó** contra una fuente oficial; conviene contrastar el conteo de municipios por entidad con INEGI antes de depender de él para validaciones legales.
- **Disponibilidad y licencia del origen:** sin licencia declarada. Si el host desaparece o cambia, la importación falla sin escribir; la URL es configurable (`API_INFO_MEXICO_URL`) y se puede apuntar a una copia propia.
- **Municipios que cambian:** el catálogo no se actualiza solo; se vuelve a importar a mano.
- **Localidades:** la API las ofrece; **no** se importan (fuera de alcance).

## Relación con ADR-008

ADR-008 resolvió que `planteles.municipio` y `certificados_numero_oficial.municipio` pasen a un selector de los 18 municipios de Querétaro, con una tabla `municipios` y FK en ambas tablas (trabajo derivado aún sin hacer, ver ADR-014). Las tablas de este ADR son un **superconjunto**: los 18 municipios de Querétaro son las filas de la entidad 22. Cuando se haga ese trabajo, las FK de `planteles` y del certificado deben apuntar a estas tablas en lugar de crear otra. **Este ADR no toca `planteles` ni el certificado.**

## Verificación

Pruebas con `Http::fake` (sin red): mapeo de campos, filtrado de claves 0/88/99, idempotencia, actualización de nombres, aborto sin escribir (pocas entidades, entidad sin municipios, API caída, respuesta malformada) y el comando. Ver `docs/reports/2026-10-19-geografia-notaria.md`.
