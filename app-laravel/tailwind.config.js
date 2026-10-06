import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            // DESIGN.md `colors:` — every key here matches a DESIGN.md token
            // name 1:1 so `bg-surface-card` traces back to `{colors.surface-card}`.
            colors: {
                primary: '#242B57',
                'primary-active': '#1a1f3f',
                'primary-disabled': '#e5e7eb',
                ink: '#242B57',
                body: '#374151',
                // DESIGN.md dice #707F8F (<4.5:1 sobre blanco). Rediseño UI: ver reporte.
                muted: '#4B5563',
                faint: '#9ca3af',
                hairline: '#e5e7eb',
                'hairline-soft': '#f3f4f6',
                canvas: '#ffffff',
                'surface-soft': '#f8f9fa',
                'surface-card': '#f5f7fa',
                'surface-dark': '#242B57',
                'surface-dark-elevated': '#1a1f3f',
                'on-primary': '#ffffff',
                'on-dark': '#ffffff',
                'on-dark-soft': '#a1a1aa',
                'brand-accent': '#4996C4',
                // Azul institucional de la barra y el pie (tableros_municipales); blanco encima = 5.2:1.
                'brand-blue': '#266fb6',
                success: '#28a745',
                warning: '#f59e0b',
                error: '#ef4444',
                // Rediseño UI (docs/reports/<fecha>-rediseno-ui.md): pares ink/soft ≥ 4.5:1.
                // success/warning/error de arriba quedan solo para iconos.
                'success-ink': '#1E6B34',
                'success-soft': '#E8F5EC',
                'warning-ink': '#8A4B08',
                'warning-soft': '#FEF3E2',
                'error-ink': '#B42318',
                'error-soft': '#FDECEA',
                'info-ink': '#242B57',
                // Borde de controles de formulario: 4.8:1 (hairline es 1.2:1, no pasa WCAG 1.4.11).
                control: '#6B7280',
                'badge-blue': '#e8edf8',
                'badge-aqua': '#e1f5fa',
                'badge-gold': '#fdf4da',
                'badge-magenta': '#fde8f2',
                // nivel-* — only the entries that map cleanly to this project's
                // 7 niveles_educativos. nivel-cam excluded: CAM is not a nivel
                // in this system's scope. nivel-posgrado deliberately absent —
                // see docs/reports/<date>-paso1-preregistro.md.
                'nivel-inicial-esc': '#923792',
                'nivel-inicial-no-esc': '#B07CB8',
                'nivel-preescolar': '#FF438E',
                'nivel-primaria': '#FFBE00',
                'nivel-secundaria': '#58BB00',
                'nivel-media-superior': '#0080B0',
                'nivel-superior': '#021850',
            },

            // DESIGN.md `spacing:` — 4px base unit.
            spacing: {
                xxs: '4px',
                xs: '8px',
                sm: '12px',
                md: '16px',
                lg: '24px',
                xl: '32px',
                xxl: '48px',
                section: '96px',
            },

            // DESIGN.md `rounded:`.
            borderRadius: {
                xs: '4px',
                sm: '6px',
                md: '8px',
                lg: '12px',
                xl: '16px',
                pill: '9999px',
            },

            // DESIGN.md typography — self-hosted, see resources/css/fonts.css.
            // `sans` is overridden to Hanken Grotesk (DESIGN.md's body/UI face
            // for the whole system, replacing Breeze's default Figtree).
            fontFamily: {
                sans: ['Hanken Grotesk', 'system-ui', 'sans-serif'],
                display: ['Cal Sans', 'Hanken Grotesk', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
            },

            // DESIGN.md `typography:` scale. Font weight is applied via a
            // companion Tailwind font-{weight} utility (not encoded here) —
            // e.g. `class="font-display text-display-lg font-semibold"`.
            fontSize: {
                'display-xl': ['52px', { lineHeight: '1.0', letterSpacing: '-1.5px' }],
                'display-lg': ['36px', { lineHeight: '1.1', letterSpacing: '-1px' }],
                'display-md': ['28px', { lineHeight: '1.15', letterSpacing: '-0.5px' }],
                'display-sm': ['22px', { lineHeight: '1.2', letterSpacing: '-0.3px' }],
                'title-lg': ['22px', { lineHeight: '1.3', letterSpacing: '-0.2px' }],
                'title-md': ['18px', { lineHeight: '1.4', letterSpacing: '0' }],
                'title-sm': ['16px', { lineHeight: '1.4', letterSpacing: '0' }],
                'body-md': ['16px', { lineHeight: '1.5', letterSpacing: '0' }],
                'body-sm': ['14px', { lineHeight: '1.5', letterSpacing: '0' }],
                caption: ['12px', { lineHeight: '1.4', letterSpacing: '0.3px' }],
                'section-eyebrow': ['11px', { lineHeight: '1.0', letterSpacing: '2px' }],
                code: ['13px', { lineHeight: '1.5', letterSpacing: '0' }],
                button: ['13px', { lineHeight: '1.0', letterSpacing: '0' }],
                'nav-link': ['13px', { lineHeight: '1.4', letterSpacing: '0' }],
            },
        },
    },

    plugins: [forms],
};
