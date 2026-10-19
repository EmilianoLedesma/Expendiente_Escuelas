# Reporte: ubicación de la notaría con catálogo geográfico (Paso 2.2, escritura)

**Fecha:** 2026-10-19 · **Rama:** `worktree-geografia-notaria` · **Decisión:** `docs/decisions/ADR-017-catalogo-geografico-nacional.md` · **Plan:** `docs/superpowers/plans/2026-10-19-geografia-notaria.md` (local, ignorado por git).

## 1. Qué se pidió y qué se hizo

Pedido del dueño: en la acreditación del inmueble, "municipio y entidad federativa" debe ser un menú desplegable; la interfaz no guarda esas listas; se crean tablas y la información sale de la API `angellof18/api_info_mexico`.

- **Antes:** un solo campo de texto libre (`notario_localidad`, "Municipio y entidad federativa") en el bloque de escritura.
- **Ahora:** dos listas dependientes (Entidad federativa → Municipio), opcionales, alimentadas por tablas locales que se llenan con un comando de importación. La pantalla no contiene ninguna lista fija.

## 2. Cambios

| Tarea | Commit | Resultado |
|---|---|---|
| 1 | `d6ba79a` | Migración `2026_10_19_000400`: `entidades_federativas` y `municipios` (único por entidad y clave, `ON DELETE CASCADE`); modelos `EntidadFederativa` y `Municipio`. |
| 2 | `1c83065` | Importación: puerto `FuenteCatalogoGeografico`, cliente `ApiInfoMexico` (Infrastructure), caso de uso `ImportarCatalogoGeografico`, comando `geografia:importar`, configuración `services.api_info_mexico`. |
| 3–4 | `f2ac0a3` | Consulta `ListarCatalogoGeografico`; migración `2026_10_19_000500` (`notario_entidad_federativa_id`, `notario_municipio_id`, FK nullable `ON DELETE SET NULL`); `RegistrarDocumento` valida y guarda; el formulario deja de usar `notarioLocalidad`. |
| 5 | `e0c998b` | Vista y componente: dos `x-ui.select` dependientes; cambiar la entidad borra el municipio; aviso cuando el catálogo aún no se ha importado. |
| 6 | (este) | ADR-017, nota en ADR-008, este informe. |

## 3. Cómo funciona la importación

`php artisan geografia:importar` (URL por omisión `https://api-info-mexico.netlify.app`, cambiable con `API_INFO_MEXICO_URL`):

1. Trae `/api/estados` y `/api/estados/{id}/municipios` de cada entidad.
2. Descarta las claves no geográficas del origen: 0 NO ESPECIFICADO, 88 NO APLICA, 99 SE IGNORA.
3. Si hay menos de 32 entidades, alguna entidad no trae municipios, la API falla (HTTP, conexión) o responde con otra forma: **no escribe nada** y muestra el motivo.
4. Si todo está completo: upsert en una sola transacción. Se puede repetir sin duplicar; no borra lo que el origen deje de listar.

La aplicación nunca llama a la API al atender a una persona usuaria.

## 4. Verificación (ejecutada)

- Corrida completa en la rama: **1603 de 1603 pruebas pasaron** (5713 aserciones); Pint limpio; PHPStan 0 errores. Referencia anterior en `master`: 1576.
- Las pruebas de importación usan `Http::fake`: **no se llamó a la API real desde las pruebas**. Antes de diseñar, consulté la API real de solo lectura: 35 entradas en `/api/estados` (32 entidades más 0, 88 y 99) y 18 municipios para Querétaro.
- Migraciones comprobadas en la BD de pruebas (la suite las aplica); reversibles (`down()` elimina columnas y tablas en orden).

## 5. Desviaciones del proceso

- La migración y los modelos de la tarea 1 se escribieron antes de ver fallar su prueba (orden TDD invertido en esa tarea); el resto de las tareas sí se vio fallar primero.
- Dos corridas completas fueron detenidas por falta de memoria del sistema y se repitieron con autorización.
- Los 18 municipios de Querétaro y el resto del catálogo **no están cargados** en la BD de desarrollo hasta que se ejecute la importación (ver §8).

## 6. Tabla de decisiones pendientes

| Decisión pendiente | Estado previo | Impacto de la implementación | Estado final | Evidencia |
|---|---|---|---|---|
| Municipio y entidad como lista, no como texto | Texto libre `notario_localidad` | Dos listas dependientes desde tablas locales | **Técnicamente resuelta** | `Paso2DocumentosTest::test_los_selectores_de_la_notaria_salen_de_la_base_de_datos_y_no_de_la_vista`, `::test_los_municipios_siguen_a_la_entidad_elegida_y_el_selector_empieza_deshabilitado`, `::test_cambiar_la_entidad_borra_el_municipio_elegido` |
| Catálogo geográfico nacional (ADR-008 lo dejó fuera de alcance) | No existía | Tablas nacionales importadas de la API (ADR-017) | **Decidida y técnicamente resuelta**; procedencia de los datos **sin verificar** | `ImportarCatalogoGeograficoTest::test_imports_states_and_municipios_and_skips_non_geographic_keys`, ADR-017 |
| FK de `planteles` y `certificados_numero_oficial` a municipios (ADR-008/ADR-014) | Pendiente | Sin cambios; ahora existe la tabla a la que deben apuntar | **Pendiente** (fuera de alcance) | ADR-008 (nota), ADR-017 |
| Localidades | Fuera de alcance | La API las ofrece; no se importan | **Pendiente** (no pedido) | ADR-017 |
| Filas anteriores con texto en `notario_localidad` | — | Se conserva sin tocar; la pantalla ya no lo escribe | **Compatibilidad resuelta**; re-capturar la escritura reemplaza la ubicación por la elegida en las listas | `RegistrarDocumentoTest::test_escritura_acepta_la_entidad_sin_municipio_y_sin_ubicacion` |
| Procedencia, licencia y disponibilidad de la API | — | Mitigado: se importa una vez y la app no depende de ella en línea | **Riesgo abierto** (verificar contra INEGI) | ADR-017 |

## 7. Trazabilidad requisito → prueba

| Requisito del dueño | Implementación | Prueba |
|---|---|---|
| Menú desplegable de entidad y municipio | `x-ui.select` dependientes | `Paso2DocumentosTest::test_los_municipios_siguen_a_la_entidad_elegida_y_el_selector_empieza_deshabilitado` |
| No guardar entidades ni municipios en el frontend | Listas desde BD; vista sin datos fijos | `Paso2DocumentosTest::test_los_selectores_de_la_notaria_salen_de_la_base_de_datos_y_no_de_la_vista` |
| Crear tablas | `entidades_federativas`, `municipios` | `CatalogoGeograficoTest::test_entidad_clave_is_unique`, `::test_municipio_clave_is_unique_within_an_entidad_but_repeatable_across_entidades`, `::test_deleting_an_entidad_deletes_its_municipios` |
| Obtener la información de la API | Cliente + importación + comando | `ImportarCatalogoGeograficoTest::test_the_http_client_maps_the_api_fields`, `::test_the_command_imports_from_the_configured_api_and_reports_counts` |
| Importación robusta | Aborta sin escribir; idempotente | `::test_aborts_without_writing_when_the_catalog_has_too_few_states`, `::test_aborts_without_writing_when_a_state_has_no_municipios`, `::test_a_failing_source_writes_nothing`, `::test_the_http_client_fails_on_http_errors_and_unexpected_payloads`, `::test_running_twice_does_not_duplicate_and_updates_changed_names`, `::test_the_command_fails_with_a_message_and_writes_nothing_when_the_api_is_down` |
| Lectura para los selectores | `ListarCatalogoGeografico` | `ListarCatalogoGeograficoTest::test_lists_entidades_ordered_by_name_with_id_and_name_only`, `::test_lists_only_the_municipios_of_the_given_entidad_ordered_by_name`, `::test_no_entidad_or_unknown_entidad_has_no_municipios` |
| Integridad aunque se evite Livewire | `RegistrarDocumento::validarUbicacionNotaria` | `RegistrarDocumentoTest::test_escritura_rechaza_un_municipio_de_otra_entidad_o_sin_entidad_o_inexistente`, `::test_otras_variantes_anulan_la_ubicacion_de_la_notaria_enviada_por_un_caller`, `::test_escritura_persiste_la_entidad_y_el_municipio_de_la_notaria` |
| Reglas del formulario | exists + pertenencia | `ValidacionFormulariosTramiteTest::test_paso2_documentos_el_municipio_de_la_notaria_debe_ser_de_la_entidad_elegida`, `::test_paso2_documentos_un_municipio_sin_entidad_o_ids_inexistentes_se_rechazan`, `::test_paso2_documentos_la_ubicacion_de_la_notaria_es_opcional`, `::test_paso2_documentos_el_payload_de_la_notaria_lleva_ids_enteros_o_nulos` |
| Guardado de punta a punta | FK en la acreditación | `Paso2DocumentosTest::test_escritura_guarda_propietario_y_datos_registrales` |

## 8. Pendientes y verificaciones manuales

- **Ejecutar la importación en la BD de desarrollo** (llamada de red a un tercero y unas 2,400 filas escritas): requiere autorización explícita. Mientras no se ejecute, las listas aparecen vacías con el aviso de importar el catálogo.
- Contrastar el conteo de municipios por entidad con INEGI antes de usar el catálogo para validaciones normativas.
- Recorrido en navegador (teclado, móvil, lector de pantalla, listas largas: hasta 570 municipios en Oaxaca) con servidor propio del worktree y BD propia.
- Posible mejora: preseleccionar Querétaro o buscar dentro de la lista; no se hizo por no estar pedido.
