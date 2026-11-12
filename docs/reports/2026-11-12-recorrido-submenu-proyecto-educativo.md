# 2026-11-12 — Recorrido lateral: submenú del Proyecto Educativo y desplazamiento independiente

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD. Sin cambios de base de datos (no hubo migraciones).

## Qué se construyó

- **Submenú plegable** bajo "Presentación del Proyecto Educativo" en el menú lateral del trámite, solo en los niveles que llevan el proyecto (media superior). Lista las 20 secciones con número, marca de completada y enlace; resalta la sección actual (`aria-current="page"`). El resumen dice "Secciones (N de 20)".
- Se **abre solo cuando la persona está dentro del paso** (índice o cualquier sección); en el resto de páginas queda cerrado para no alargar la pantalla. Es un `<details>` nativo, sin JavaScript.
- Si el paso está bloqueado (sin enlace en el recorrido), las secciones salen como texto sin enlace, igual que la fila padre.
- **Desplazamiento independiente** (escritorio): el contenido del menú va en un contenedor `lg:sticky lg:top-md lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto`. El menú se desplaza solo y la página por separado. En móvil se conserva el `<details>` "Secciones del trámite" existente.

## Estructura

- Nueva `Application\ProyectoEducativo\EstadoSeccionesProyecto::completas($escuelaNivelId)`: mapa slug → completa para las 20 secciones (las que aún no tienen formulario salen en `false`). Sustituye al mapa de 12 lectores que armaba `ProyectoEducativoIndice::render`; el índice ahora la usa (mismo resultado).
- `Recorrido` (componente de vista) calcula ese mapa para cada nivel que lleva el proyecto y lo pasa a `recorrido-pasos`; nuevo parámetro `subseccionActual`.
- `ProyectoEducativoSeccion` pasa `subseccionActual` (su slug) por `layoutData`; el layout lo reenvía.

## Evidencia

- Pruebas escritas antes del código y vistas fallar (4 de vista en rojo, 2 de aplicación por clase inexistente; la de "otros niveles" ya pasaba): `EstadoSeccionesProyectoTest` (2) y `RecorridoProyectoEducativoTest` (5: lista y marca, plegable y cerrado fuera del paso, abierto y actual dentro, sin submenú en otros niveles, clases de scroll independiente).
- Un fallo propio durante el desarrollo: `@if (...) open @endif` generaba doble espacio y la prueba no encontraba `data-subsecciones open`; se corrigió el marcado.
- Verde, enfocadas: `tests/Feature/View`, `tests/Feature/Tramite`, `Paso3ProyectoEducativoTest` y las dos nuevas (172 de 177). Pint limpio, PHPStan 0.
- Los 5 fallos restantes son `ShellTest::test_no_hay_recursos_enlazados_desde_servidores_externos`: existe `public/hot` (servidor Vite de desarrollo en `[::1]:5173`) y la prueba ve `@vite/client` como recurso externo. No dependen de este cambio (login, registro, etc.); pasan con el servidor Vite apagado, no se verificó aquí.

## Ajuste posterior: barra mínima e indicador de completada

- Clase `.scroll-minimal` en `resources/css/app.css` (delgada, sin pista, pulgar gris que se oscurece al pasar el cursor; Firefox `scrollbar-width: thin`, WebKit 6 px). Se aplica al contenedor del menú lateral.
- Cada sección completada del submenú lleva un círculo verde con palomita en lugar del número (`data-subseccion-hecha`); las pendientes muestran su número en un círculo con borde. Antes solo había una palomita pequeña a la derecha.
- Las dos aserciones nuevas de la prueba se añadieron **después** del cambio (no se vieron fallar antes); 11 pruebas de `tests/Feature/View/Components/Tramite` en verde y Pint limpio. Sin vista en navegador.

## Qué NO se hizo / límites

- **No se vio en navegador**: apariencia del submenú, altura máxima y sticky con el encabezado, y que las clases nuevas (`lg:max-h-[calc(100vh-2rem)]`, `min-h-9`, `ml-10`) las genere Tailwind.
- El layout se dibuja una vez por carga de página: la marca de "completada" del menú se actualiza al navegar, no en vivo tras guardar en la misma página.
- Cuesta unas consultas más por página (los lectores de las secciones) en niveles de media superior.
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- `docs/progress.md` no se tocó.
