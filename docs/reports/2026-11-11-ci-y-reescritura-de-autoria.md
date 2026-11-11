# 2026-11-11 — Arreglo de CI, reescritura de autoría y limpieza

Trabajo del controlador directamente sobre `master` (sin rama). Estado final: `master` en `4acca44`, CI en verde en sus cinco trabajos.

## 1. CI en rojo tras los merges del Proyecto Educativo

- **Síntoma.** Las dos ejecuciones de CI posteriores a los merges de las secciones 1-3 (`67568ad` y `1bfaa32`, SHAs ya remapeados) fallaron por una sola prueba: `SinVentanasDelNavegadorTest::test_los_campos_de_texto_se_validan_al_salir_de_ellos`. El trabajo de cobertura falló por la misma causa.
- **Causa.** La prueba exige `wire:model.blur` en todo `<x-ui.input>` y `<x-ui.textarea>` dentro de `resources/views/livewire/tramite`, para que el mensaje de validación salga al salir del campo y no solo al enviar. El formulario de la sección 1 (`paso3/proyecto-educativo-seccion.blade.php`) tenía 11 campos con `wire:model` simple.
- **Arreglo.** Se cambió a `wire:model.blur` en esos 11 campos (commit `4acca44`, subido por el dueño).
- **Evidencia.** Localmente: la prueba fallida y `Paso3ProyectoEducativoTest` en verde, Pint limpio. En CI: Pint, PHPStan, pruebas, cobertura y auditoría de dependencias en verde.
- **Por qué no se vio antes.** Las pruebas enfocadas de las secciones no incluyen `tests/Feature/Validacion`, donde vive la prueba guardiana. Al añadir vistas Livewire nuevas conviene correr también ese directorio.

## 2. Contribuidor `claude` en GitHub

- **Hallazgo.** Ocho commits (20 y 21 de octubre de 2026) tenían autor y committer `Claude <noreply@anthropic.com>`; por eso GitHub listaba a `claude` con 8 contribuciones. No eran trailers `Co-Authored-By`: eran autoría directa. Los únicos trailers `Co-authored-by` restantes son de Copilot (un commit) y dependabot, y no se tocaron.
- **Decisión del dueño.** Reescribir el historial de `master` y forzar el push, aceptando que cambian los SHAs.
- **Método.**
  1. Respaldo local en la rama `backup-pre-rewrite`.
  2. `git filter-branch --env-filter` solo sobre `master`, cambiando autor y committer de esos commits a la identidad del dueño.
  3. Verificación: ya no queda ningún autor ni committer Claude, `git diff` contra el respaldo vacío (árbol idéntico), 603 commits antes y después, 288 con SHA nuevo.
  4. `git push --force-with-lease` contra el SHA anterior esperado.
  5. CI sobre el resultado en verde; luego se borró el respaldo.
- **Resultado parcial.** Justo después del push, la API de GitHub seguía mostrando `claude` con 8. Las referencias `refs/pull/*` de los PR ya fusionados mantienen alcanzables los commits viejos y las estadísticas de contribuidores se cachean. Si no desaparece en uno o dos días, hay que pedir a GitHub Support que purgue las vistas en caché de esos commits.

## 3. Remapeo de SHAs en la documentación

- Se construyó el mapa SHA viejo → nuevo emparejando los 603 commits por posición en orden topológico, verificando que el asunto de cada par coincida.
- Se actualizaron 197 referencias en `docs/progress.md`, `CLAUDE.md` y `docs/**` (29 archivos). Un SHA solo se reemplaza si su prefijo identifica un único commit cuyo SHA cambió.
- No se remapearon los SHAs de archivos locales ignorados por git (planes, specs, ledgers) ni los enlaces a ejecuciones viejas de CI.
- Se corrigió en `progress.md` el texto desactualizado de la entrada del 2026-11-10 (decía que `dc80128` estaba sin fusionar; ya está en `master`).

## 4. Limpieza (con aprobación del dueño)

- Eliminados los worktrees `presentacion-seccion1` y `proyecto-educativo` y sus ramas locales. En Windows falló el borrado normal por rutas largas; se resolvió con `rmdir` sobre ruta `\\?\`.
- Eliminado el esquema `seccion1` de la base de desarrollo (`DROP SCHEMA ... CASCADE`); solo queda `public`.
- Borrados los volcados `dev-public.sql` y `dev-seccion1.sql` del scratchpad.

## Pendiente

- Revisar en unos días que GitHub ya no liste a `claude`; si sigue, abrir ticket a Soporte.
- Recorrido en navegador de las secciones 1-3 del Proyecto Educativo.
- Decisión del dueño: input de fecha nativo vs máscara dd/mm/aaaa, y contador en rojo desde cero palabras.
- Sección 4 (Enfoque Pedagógico): el dueño aporta los campos.
