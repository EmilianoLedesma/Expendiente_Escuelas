# 2026-10-06 — Barra institucional, encabezado y pie (estilo tableros_municipales)

Rama/worktree: `worktree-agent-ace24d116898d9214`. Primera entrega sobre `master` @ `8ee9394`; tras la revisión, rebase sobre `master` @ `71655ae` con un único commit WIP local `6223a30` (sin push). La ronda de correcciones queda **sin commit**.

## Qué se construyó

Solo presentación: sin cambios de esquema, gates, validaciones ni persistencia; sin dependencias npm/composer nuevas.

- **Fuente única de datos** `app-laravel/config/sedeq.php`: institución, enlaces de gobierno (Portal Transparencia, Portal Prensa — se eliminó COVID19), redes + teléfono (`redes`), contacto (dirección, teléfonos, texto de atención) y aviso de privacidad. Ninguna vista copia estos datos a mano.
- **`x-shell.redes`** (nuevo): la lista de redes se pinta desde `config('sedeq.redes')`; la usan la barra institucional (con etiqueta visible en `lg`) y la franja legal del pie.
- **`x-shell.encabezado`** (reescrito): salto «Saltar al contenido» como primer enlace del DOM (ahora fuera del `<header>`), un único `<header>` con la barra institucional `#266fb6` (`<nav aria-label="Enlaces institucionales">` + redes) y el encabezado blanco con sombra, logotipo de 60 px (40 px en móvil) enlazado al inicio y el título «Trámite de Incorporación de Escuelas Particulares». Navegación de cuenta («Mis trámites», nombre, «Cerrar sesión» POST) en mayúsculas con tracking; activo = borde inferior de 3 px + `aria-current="page"` (indicador no cromático). Flujo normal (sin sticky/fixed), sin hamburguesa ni JS.
- **`x-shell.pie`** (reescrito): tres franjas — heráldica (degradado marino→azul, `heraldicas.png` con alt «Gobierno de Querétaro»), contacto `#266fb6` en cuatro columnas (Dirección, Teléfono, Atención ciudadana, Web master; 1 columna en móvil, 2 en `sm`, 4 en `lg`) y legal `#242B57` (Aviso de privacidad, «PODER EJECUTIVO DEL ESTADO DE QUERÉTARO Copyright © {año} Derechos Reservados.», «Sistema de Incorporación · versión MVP», redes). Encabezados: un `h2` sr-only «Información institucional» y cuatro `h3`.
- **`x-ui.icon`** extendido: Heroicons `map-pin`, `phone`, `envelope` y seis marcas de relleno (`marca-*`).
- **Token** `brand-blue: #266fb6` en `tailwind.config.js` y `DESIGN.md`. Regla CSS `.shell-oscuro :focus-visible { outline-color: white }` para el foco sobre las franjas oscuras.
- **`layouts/app.blade.php`** (perfil) migrado al shell: ya no carga Figtree desde `fonts.bunny.net` ni monta `livewire:layout.navigation`. Se **eliminó** `resources/views/livewire/layout/navigation.blade.php` (menú Breeze con enlace «Dashboard», dropdown y hamburguesa). El slot `header` del perfil ahora vive dentro de `<main>`.
- `guest` y `tramite` no cambiaron: ya usaban `x-shell.encabezado` / `x-shell.pie` (la costura única se conserva).

## Cambios deliberados en pruebas

`tests/Feature/View/ShellTest.php` (las 5 pruebas existentes se conservan sin cambios) + nuevas:
1. `test_cada_layout_tiene_barra_encabezado_navegacion_y_pie_unicos` ×5 (login, register, tramite.index, tramite.preregistro, profile): un solo banner y contentinfo (`header`/`footer` fuera de `main`), `main#contenido`, nav «Enlaces institucionales», nav «Cuenta» solo autenticado, primer enlace = `#contenido`, alt del logotipo y del escudo, `src` locales, 1 `h2` + 4 `h3` en el pie.
2. `test_no_hay_recursos_enlazados_desde_servidores_externos` ×5: ningún `src`/`srcset`/`<link href>` hacia queretaro.gob.mx, fonts.googleapis.com, fonts.cdnfonts.com, cdnjs o bunny.net; ningún `url(http…)`.
3. `test_el_css_propio_no_trae_recursos_externos`: `app.css` y `fonts.css` sin `http(s)://`.
4. `test_los_enlaces_externos_abren_seguro_y_todo_enlace_tiene_nombre` ×5: todo enlace del header/pie tiene nombre accesible (texto, aria-label o alt de imagen); todo enlace externo lleva `target="_blank"`, `rel="noopener noreferrer"` y «(abre en una pestaña nueva)».
5. `test_redes_y_contacto_salen_de_una_sola_fuente_y_aparecen_en_barra_y_pie`: 6 redes con URL únicas en config, sin COVID19; cada URL aparece exactamente una vez en la barra y una en el pie; dirección, teléfonos, columnas y copyright salen de config.
6. `test_los_recursos_graficos_estan_alojados_en_el_proyecto`.

Ciclo rojo observado antes de implementar: 10 fallas por las razones esperadas (sin nav institucional, perfil con bunny.net y `layout.navigation`, redes ausentes en la barra). Dos ajustes de la propia prueba durante el rojo, no del código: (a) contar solo `header`/`footer` fuera de `<main>` (los formularios Breeze del perfil y la vista de login usan `<header>` internos, que no son landmarks); (b) tratar como externos solo los `http` que no son de la app, y contar el `alt` de la imagen como nombre del enlace del logotipo. La prueba de recursos gráficos pasó desde el inicio porque las imágenes se copiaron antes de escribirla (no hubo rojo para esa).

`tests/Feature/Auth/AuthenticationTest.php`:
- `test_navigation_menu_can_be_rendered`: antes `assertSeeVolt('layout.navigation')` en `/profile`; ahora afirma `<nav aria-label="Cuenta"`, el form POST a `logout` y la ausencia de `fonts.bunny.net`.
- `test_users_can_logout` (Volt `layout.navigation` → `logout`): **eliminada** junto con el componente. La salida sigue cubierta por `test_post_logout_route_logs_out_and_redirects_to_login`.

## Evidencia

Primera entrega (sobre `8ee9394`):
- Suite completa: base en `master` **997/997** (2692 aserciones) → **1014/1014** (3093 aserciones). +18 nuevas, −1 eliminada.

Tras la ronda de correcciones y el rebase sobre `master` `71655ae` (ver sección siguiente):
- Suite completa **1037/1037** (3311 aserciones) = 1017 de `master` + 20 netas de esta rama.
- PHPStan `{"tool":"phpstan","result":"passed","errors":0}`; Pint `{"tool":"pint","result":"passed"}`; `npm run build` ✓ (`app-ASgEBP2E.css`, contiene `hover:bg-black/10` y `outline-offset:-3px`).

Valores de la primera entrega:
- PHPStan: `{"tool":"phpstan","result":"passed","errors":0}`.
- Pint: `{"tool":"pint","result":"passed"}`.
- `npm run build`: ✓ (`app-ssifuRXx.css` 63.04 kB); verificado que el CSS compilado contiene `bg-brand-blue`, `sm:h-[60px]`, `border-b-[3px]`, `.shell-oscuro :focus-visible`.

## Ronda de correcciones (revisión Opus: 4 importantes, 5 menores)

Commit WIP local `6223a30` (solo archivos de esta rama, sin push) para poder hacer `git rebase master` (`71655ae`, incluye el merge de eliminar trámite `0af0070`). Conflicto único en `resources/views/components/ui/icon.blade.php`: `master` añadió `trash` y esta rama `map-pin`/`phone`/`envelope` + `$marcas`; se conservaron **ambos**. El resto de la ronda queda sin commit.

1. **Hover de la barra** (importante): `hover:bg-white/10` dejaba el texto blanco de 12 px en 4.32:1. Se cambió a `hover:bg-black/10` en `encabezado` y `redes` (6.1:1). Prueba: `test_hover_de_la_barra_no_aclara_el_azul` (roja → verde).
2. **Anillo de foco recortado** (importante): la barra es una fila de enlaces de 44 px sin padding vertical; con offset +2 px el anillo blanco quedaba cortado arriba e invisible (blanco sobre blanco) abajo. `.shell-oscuro :focus-visible` ahora lleva `outline-offset: -3px` (anillo dentro del enlace), conservando el color blanco. Al «Aviso de privacidad» se le dio `px-xs` (con `-mx-xs`) para que el anillo interior no tape las letras. Prueba: `test_el_foco_en_las_franjas_oscuras_es_blanco_y_hacia_dentro` (roja → verde).
3. **DESIGN.md contradictorio** (importante): se reemplazaron `top-nav` (YAML y prosa: sticky, 56 px) por `institutional-bar`, `site-header`, `site-header-nav-link(-active)`; el `footer` YAML (on-dark-soft, 64 px) por `footer-heraldica`, `footer-contact`, `footer-legal`; y se ajustaron description, Surface Dark, On Dark/On Dark Soft, Brand Blue (nuevo), tabla tipográfica, grid del pie, tabla de elevación, Do/Don't, breakpoints (sin hamburguesa), estrategia de colapso, guía de iteración y Known Gaps. `grep` de sticky/hamburger/only dark/dark footer/top-nav/on-dark-soft solo deja referencias históricas tachadas o el uso legítimo en `dark-footer` inline.
4. **Base atrasada** (importante): resuelto con el rebase anterior. `register.blade.php` y `tramite/index.blade.php` de `master` quedan cubiertos por las pruebas parametrizadas (login, register, tramite.index…), en verde.
5. **Reflujo** (menor): la lista «Enlaces institucionales» lleva `flex-wrap`; aserción añadida a la prueba de layouts (roja → verde).
6. **Prueba anti-hotlink** (menor): ya no es una lista negra de 5 hosts; ahora todo `src`, `srcset`, `<link href>` y `<script src>` de las páginas debe ser relativo o de la propia app (`url('/')`), lo que cubre kit.fontawesome.com, jsdelivr, unpkg y URLs con protocolo relativo `//`. Pasó en verde desde el principio (el código ya cumplía): es un endurecimiento, no hubo rojo.
7. **Teléfono duplicado** (menor): `config/sedeq.php` define `$directorio = '4422385000'` una sola vez y deriva la etiqueta de la barra («442 238 5000»), el `tel:` y el texto del pie («Directorio (442) 238 5000»). Prueba: `test_el_telefono_del_directorio_se_define_una_sola_vez` (roja → verde).
8. **Nombre en el encabezado** (menor): **corregido**, sin dependencias nuevas. El `<span>` del nombre lleva `x-data`/`x-text`/`x-on:profile-updated.window` como el menú Breeze eliminado; Alpine llega con Livewire en las páginas que lo cargan (perfil, trámite). Sin Alpine queda el nombre renderizado en el servidor. Aserción añadida a la prueba de layouts (roja en las 3 páginas autenticadas → verde).
9. **Componentes Breeze muertos** (menor): sin ninguna referencia en `resources/`, `app/`, `tests/`, `routes/`, `config/` (búsqueda de `x-…`, `components.…` y `dynamic-component`); **eliminados** `resources/views/components/nav-link.blade.php`, `responsive-nav-link.blade.php`, `dropdown.blade.php`, `dropdown-link.blade.php`, `application-logo.blade.php`. Se conservan los que sí usan las vistas de perfil y acceso (botones, inputs, modal, action-message, auth-session-status).
10. **Perfil sin h1** (menor): fuera de alcance, listado abajo.

## Contraste de los pares nuevos (WCAG 2.2)

| Par | Ratio | Resultado |
|---|---|---|
| Blanco sobre `#266fb6` (barra, franja de contacto) | 5.2:1 | AA texto normal ✓ |
| Blanco sobre hover `#266fb6` + 10 % negro ≈ `#2264a4` (barra, ronda de correcciones) | 6.1:1 | AA ✓ |
| Blanco sobre hover `#266fb6` + 10 % blanco ≈ `#3c7dbd` (primera versión, **descartada**) | 4.3:1 | ✗ para texto de 12 px |
| Blanco sobre `#242B57` (franja legal) | 13.5:1 | AAA ✓ |
| Blanco sobre hover `#242B57` + 10 % negro (redes del pie) | ≈ 15:1 | AAA ✓ |
| Foco blanco sobre `#266fb6` / `#242B57` | 5.2:1 / 13.5:1 | 1.4.11 ✓ |
| Foco `#4996C4` sobre `#266fb6` (lo que habría sin la regla) | 1.6:1 | ✗ — por eso `.shell-oscuro` cambia el foco a blanco |
| Foco `#4996C4` sobre blanco (encabezado) | 3.3:1 | 1.4.11 ✓ |
| Borde activo `#266fb6` sobre blanco (nav de cuenta) | 5.2:1 | 1.4.11 ✓ |

## Decisión registrada: desviaciones de la Dirección B / DESIGN.md

- «El pie es la única superficie oscura» queda **revisado** por decisión del dueño: hay barra institucional azul arriba y pie de tres franjas. Actualizado en `DESIGN.md` (token `brand-blue`, Overview y Key Characteristics).
- Texto del pie en blanco pleno (no `on-dark-soft`) y enlaces subrayados.
- Tipografía: solo Hanken Grotesk (no Raleway/Novecento del original).
- Objetivos táctiles de 44 px: la barra mide ~44 px en lugar de 40 px.

## Procedencia de recursos

- `app-laravel/public/img/layout_set_logo.png` y `heraldicas.png`: copiados de `sedeqgit/tableros_municipales/img/` (repositorio hermano de SEDEQ, lectura autorizada por el dueño, solo lectura). Blob SHA idénticos: `5cca5bfa…` y `d1aa5ecf…`. Se sirven desde `public/img` igual que las fuentes (`public/fonts`).
- Trazos de iconos de marca: Font Awesome Free 7 (iconos CC BY 4.0), copiados del `footer.php` del mismo repositorio e insertados como SVG en línea con atribución en `x-ui.icon`. No se instala ni se enlaza la librería Font Awesome.
- `bg-footer.png` **no** se copió: vive en `queretaro.gob.mx`, no en el repositorio hermano, y no hay base para considerarlo de libre copia. Se sustituyó por un degradado marino→azul.

## Desviaciones de contenido respecto al original

- Las redes del pie del original apuntaban a cuentas de **Gobierno** (GobQro) y a `tel:4422117070`; por el principio de fuente única se usa la lista de la barra (cuentas de **Educación**, `tel:4422385000`) en ambos lugares. Confirmar con SEDEQ si el pie debe llevar las de Gobierno.
- Se quitó el parámetro de rastreo `fbclid` de la URL de Instagram.
- El enlace del chatbot no abría en pestaña nueva en el original; aquí sí (es externo).
- El logotipo enlaza a `route('dashboard')` (redirige por rol) si hay sesión y a `login` si no: `/` es la bienvenida por defecto de Laravel.

## No hecho deliberadamente

- `welcome.blade.php` (ruta `/`) sigue siendo la página por defecto de Laravel con fuentes de bunny.net e imagen de laravel.com: fuera del alcance pedido.
- Las vistas internas del perfil (formularios Breeze en inglés, estilos grises) no se rediseñaron; solo cambió su layout. La página de perfil no tiene `h1` (su encabezado es un `h2` «Profile» en inglés, previo a este cambio).
- Atención ciudadana / Web master no tienen correo ni enlace: el original tampoco los tiene.
- Sin botón «volver arriba», sin menú hamburguesa, sin animación de expansión al pasar el cursor.

## Sin verificar (requiere navegador; no se lanzó, necesita aprobación del dueño)

- Aspecto visual real frente al original, ajuste del logotipo (2359×444) a 375 px, envoltura de la barra en dos filas y ausencia de desplazamiento horizontal.
- Recorrido de foco y visibilidad del anillo blanco en las franjas.
- Lectura con lector de pantalla de los nombres de las redes (etiqueta visible con `aria-hidden` + texto sr-only).

## Inquietudes abiertas

- El logotipo dice «Poder Ejecutivo del Estado de Querétaro / Querétaro Gobierno del Estado», pero el alt pedido es «SEDEQ - Secretaría de Educación del Estado de Querétaro». Se usó el alt indicado (describe el destino del enlace); revisar si SEDEQ tiene un logotipo propio.
- Los trazos Font Awesome son CC BY 4.0: la atribución está en el código; confirmar si basta o si se prefiere otro set (p. ej. Simple Icons, CC0).
