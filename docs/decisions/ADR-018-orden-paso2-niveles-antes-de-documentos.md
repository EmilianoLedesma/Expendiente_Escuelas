# ADR-018 — Paso 2: los niveles educativos se eligen antes que los documentos generales

**Estado:** resuelto el 2026-10-20 (encargo del dueño del proyecto, «Refactor integral del Paso 2»). **Relación:** modifica el orden fijado en WS-1.2 (`docs/reports/2026-09-23-seguridad-propiedad-y-compuertas.md`); no cambia ADR-001 ni ADR-006.

## Decisión

El Paso 2 sigue el orden **responsable legal → niveles educativos → documentos generales → documentos por nivel (2.4)**. Primero se fija el alcance educativo del trámite y después se calculan los documentos exigibles (la aplicabilidad del Certificado de Número Oficial depende de los niveles).

`App\Application\Tramite\EstadoPaso2` expone un contrato por pregunta y ningún consumidor reutiliza uno cuyo nombre no describa lo que necesita:

| Método | Verdadero cuando |
|---|---|
| `puedeSeleccionarNiveles` | hay responsable legal |
| `puedeCapturarDocumentosGenerales` | hay responsable y al menos un nivel |
| `generalesSatisfechos` | responsable, niveles y documentos generales completos y vigentes |
| `etapaFaltante` | primera etapa pendiente en el orden nuevo (`responsable`, `niveles`, `documentos`) o `null` |

- `RegistrarNivelesSeleccionados` ya no exige documentos; `RegistrarDocumento` rechaza documentos generales sin niveles (`EstadoPaso2::NIVELES`).
- 2.4 (`Paso24DocumentosNivel`) y Paso 3 (`CompuertaPaso3`, `CompuertaSubPaso3` y los casos de uso de Paso 3) exigen `generalesSatisfechos` / `etapaFaltante() === null`.
- `ResumenTramite` lista Plantel, Responsable, Niveles y Documentos generales en ese orden; la numeración «Paso X de N» se deriva de ese orden.
- Las rutas no cambian.

## Por qué

- Elegir niveles antes evita pedir documentos cuya exigibilidad aún no se puede saber.
- Reutilizar `puedeSeleccionarNiveles()` con el sentido de «Paso 2 completo» (como hacían `CompuertaPaso3` y `Paso24DocumentosNivel`) habría abierto un bypass a 2.4 y Paso 3 en cuanto el método cambiara de significado. Contratos con nombre propio lo impiden.

## Consecuencias

- Expedientes históricos con documentos y sin niveles: el siguiente paso es elegir niveles; los documentos se conservan y vuelven a contar. Ver la tabla de compatibilidad en `docs/specs/2026-10-20-paso2-integracion-end-to-end.md` §9.
- Revertir esta decisión obliga a tocar `EstadoPaso2`, sus consumidores, `ResumenTramite`, `Paso2Responsable`, `Paso2Documentos` y los fixtures de prueba (`CompletaPaso2::elegirNivel`).

## Verificación

`tests/Feature/Application/Tramite/EstadoPaso2Test.php`, `tests/Feature/Tramite/OrdenPaso2Test.php`, `tests/Feature/Application/Tramite/ResumenTramiteTest.php`, `tests/Feature/Sedeq/ExpedienteEnviadoARevisionTest.php`.
