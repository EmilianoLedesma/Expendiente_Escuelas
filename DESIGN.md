---
version: 2.0
name: sedeq-design-system
description: Institutional design system for SEDEQ's (Secretaría de Educación del Estado de Querétaro) digital products. White canvas with navy-blue institutional primary CTAs (#242B57), Cal Sans for display typography, Hanken Grotesk for body and UI, generous whitespace, soft-rounded cards (12px), dark footer closing every page. Educational level colors appear exclusively in data visualizations, tables, and legend chips — never on action buttons.

colors:
  primary: "#242B57"
  primary-active: "#1a1f3f"
  primary-disabled: "#e5e7eb"
  ink: "#242B57"
  body: "#374151"
  muted: "#707F8F"
  faint: "#9ca3af"
  hairline: "#e5e7eb"
  hairline-soft: "#f3f4f6"
  canvas: "#ffffff"
  surface-soft: "#f8f9fa"
  surface-card: "#f5f7fa"
  surface-dark: "#242B57"
  surface-dark-elevated: "#1a1f3f"
  on-primary: "#ffffff"
  on-dark: "#ffffff"
  on-dark-soft: "#a1a1aa"
  brand-accent: "#4996C4"
  success: "#28a745"
  warning: "#f59e0b"
  error: "#ef4444"
  badge-blue: "#e8edf8"
  badge-aqua: "#e1f5fa"
  badge-gold: "#fdf4da"
  badge-magenta: "#fde8f2"
  nivel-inicial-esc: "#923792"
  nivel-inicial-no-esc: "#B07CB8"
  nivel-cam: "#00897B"
  nivel-preescolar: "#FF438E"
  nivel-primaria: "#FFBE00"
  nivel-secundaria: "#58BB00"
  nivel-media-superior: "#0080B0"
  nivel-superior: "#021850"

typography:
  display-xl:
    fontFamily: "Cal Sans, Hanken Grotesk, sans-serif"
    fontSize: 52px
    fontWeight: 600
    lineHeight: 1.0
    letterSpacing: -1.5px
  display-lg:
    fontFamily: "Cal Sans, Hanken Grotesk, sans-serif"
    fontSize: 36px
    fontWeight: 600
    lineHeight: 1.1
    letterSpacing: -1px
  display-md:
    fontFamily: "Cal Sans, Hanken Grotesk, sans-serif"
    fontSize: 28px
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: -0.5px
  display-sm:
    fontFamily: "Cal Sans, Hanken Grotesk, sans-serif"
    fontSize: 22px
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: -0.3px
  title-lg:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 22px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: -0.2px
  title-md:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 18px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0
  title-sm:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0
  body-md:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  body-sm:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  caption:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0.3px
  section-eyebrow:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 11px
    fontWeight: 600
    lineHeight: 1.0
    letterSpacing: 2px
  code:
    fontFamily: "JetBrains Mono, ui-monospace, monospace"
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  button:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 13px
    fontWeight: 600
    lineHeight: 1.0
    letterSpacing: 0
  nav-link:
    fontFamily: "Hanken Grotesk, system-ui, sans-serif"
    fontSize: 13px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0

rounded:
  xs: 4px
  sm: 6px
  md: 8px
  lg: 12px
  xl: 16px
  pill: 9999px
  full: 9999px

spacing:
  xxs: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  section: 96px

components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: "9px 18px"
    height: 38px
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.md}"
  button-primary-disabled:
    backgroundColor: "{colors.primary-disabled}"
    textColor: "{colors.muted}"
    rounded: "{rounded.md}"
  button-secondary:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    border: "0.5px solid {colors.hairline}"
    padding: "9px 18px"
    height: 38px
  button-ghost:
    backgroundColor: transparent
    textColor: "{colors.brand-accent}"
    border: "0.5px solid {colors.brand-accent}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: "9px 18px"
  button-danger:
    backgroundColor: "{colors.error}"
    textColor: "#ffffff"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: "9px 18px"
  button-sm:
    fontSize: 12px
    padding: "6px 12px"
    rounded: "{rounded.sm}"
  button-lg:
    fontSize: 15px
    padding: "12px 24px"
    rounded: 10px
  button-icon:
    padding: 9px
    rounded: "{rounded.md}"
  top-nav:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.nav-link}"
    height: 56px
  nav-pill-group:
    backgroundColor: "{colors.surface-soft}"
    textColor: "{colors.ink}"
    typography: "{typography.nav-link}"
    rounded: "{rounded.pill}"
    padding: 4px
  hero-band:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    padding: "4.5rem 2rem 3.5rem"
  hero-app-mockup-card:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xl}"
    border: "0.5px solid {colors.hairline}"
    padding: "1.25rem"
  metric-mini-card:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
  stat-card:
    backgroundColor: "{colors.surface-soft}"
    textColor: "{colors.ink}"
    typography: "{typography.display-sm}"
    rounded: "{rounded.md}"
    padding: "1rem"
  feature-card:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.ink}"
    typography: "{typography.title-md}"
    rounded: "{rounded.lg}"
    padding: "1.25rem"
  card-raised:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    border: "0.5px solid {colors.hairline}"
    padding: "1.25rem"
  text-input:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    border: "0.5px solid {colors.hairline}"
    padding: "9px 13px"
    height: 38px
  text-input-focused:
    borderColor: "{colors.primary}"
  text-input-error:
    borderColor: "{colors.error}"
  text-input-success:
    borderColor: "{colors.success}"
  text-input-disabled:
    backgroundColor: "{colors.surface-soft}"
    textColor: "{colors.muted}"
  badge-pill:
    typography: "{typography.caption}"
    rounded: "{rounded.pill}"
    padding: "3px 9px"
  nivel-chip:
    backgroundColor: "#e8eaf6"
    textColor: "#242E57"
    rounded: "{rounded.sm}"
    border: "0.5px solid #c5cae9"
    padding: "4px 10px"
    fontSize: 11px
  avatar-circle:
    rounded: "{rounded.full}"
    size: 36px
  alert:
    rounded: "{rounded.md}"
    padding: "12px 14px"
    typography: "{typography.body-sm}"
  alert-info:
    backgroundColor: "#e8edf8"
    textColor: "#1a2a5e"
  alert-success:
    backgroundColor: "#d1fae5"
    textColor: "#065f46"
  alert-warning:
    backgroundColor: "#fef3c7"
    textColor: "#92400e"
  alert-error:
    backgroundColor: "#fee2e2"
    textColor: "#991b1b"
  data-table:
    headerBackground: "{colors.primary}"
    headerTextColor: "#ffffff"
    headerFontSize: 12px
    headerFontWeight: 600
    rowFontSize: 13px
    rowTextColor: "{colors.body}"
    altRowBackground: "{colors.surface-soft}"
    totalRowBackground: "{colors.surface-card}"
    totalRowFontWeight: 700
    rounded: "{rounded.lg}"
    cellPadding: "10px 12px"
  dark-footer:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark-soft}"
    rounded: "{rounded.lg}"
    padding: "2rem"
  footer:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark-soft}"
    typography: "{typography.body-sm}"
    padding: 64px
---

## Overview

SEDEQ's digital product surface is a clean, institutional-modern interface — white canvas (`{colors.canvas}` — #ffffff) with navy-blue primary CTAs (`{colors.primary}` — #242B57), custom **Cal Sans** display typography, and `{colors.surface-card}` (#f5f7fa) light-gray cards holding statistical data panels and product UI fragments. The system reads as precisely engineered and trustworthy — every section has clear hierarchy, generous whitespace, and a single primary action per view.

Type voice splits cleanly into two roles: **Cal Sans** (the display face — used for h1, h2, h3, and hero headlines) and **Hanken Grotesk** (used for everything else — body, buttons, nav, captions). Cal Sans uses weight 600 with negative letter-spacing (-0.3px to -1.5px depending on size) — it feels modern, slightly condensed, distinctly institutional without being bureaucratic.

Component voltage comes from **real data shown directly inside cards** — enrollment statistics, school count metrics, level-by-level breakdowns. The system doesn't illustrate data; it shows it. Educational level colors (`{colors.nivel-*}`) encode category in charts and tables and are never repurposed as interface action colors.

The footer flips to `{colors.surface-dark}` (#242B57) — the same navy as the primary CTA, visually closing every page. The footer is the only dark surface in the system; everything above stays white-with-light-gray-cards.

**Key Characteristics:**
- White canvas with navy-blue primary CTA (`{colors.primary}` — #242B57). Buttons are `{rounded.md}` (8px) with weight-600 Hanken Grotesk labels. Institutional without being stiff.
- Custom **Cal Sans** display typeface for headlines. Negative letter-spacing on display sizes — precise, slightly condensed, distinctly on-brand. Hanken Grotesk handles all body and UI type.
- Light-gray card surfaces (`{colors.surface-card}` — #f5f7fa) for feature cards, stat cards, and data swatches. White `{component.card-raised}` with hairline border for elevated UI objects.
- Educational level colors — a dedicated eight-color palette for data visualization only. Each level has a fixed color; the palette is never used on buttons, nav, or structural UI elements.
- `nav-pill-group` (`{component.nav-pill-group}`) — pill-radius wrapper around 2-3 sub-nav segments (e.g. Municipal / Estatal / Nacional; Público / Privado). The active segment renders as a white-canvas pill with subtle inner shadow. Signature interactive component of the system.
- Avatars are circular (`{rounded.full}`), 36px diameter, with tonal fills.
- Footer is navy-dark (`{colors.surface-dark}` — #242B57) with muted-white text (`{colors.on-dark-soft}` — #a1a1aa). The dark footer closes every page even though the body above is white.
- Spacing rhythm is `{spacing.section}` (96px) between major bands — tight enough to feel modern but generous enough to breathe.
- Border radius is hierarchical: `{rounded.md}` (8px) for buttons + inputs, `{rounded.lg}` (12px) for content cards and tables, `{rounded.xl}` (16px) for the hero app-mockup card, `{rounded.pill}` for nav-pill-group + badges, `{rounded.full}` for avatars.

## Colors

### Brand & Accent
- **Primary** (`{colors.primary}` — #242B57): The dominant action color — all primary CTAs, h1/h2 display type, text ink. Press state shifts to `{colors.primary-active}` (#1a1f3f). Ink and primary share the same hex — every headline reinforces the CTA color.
- **Brand Accent** (`{colors.brand-accent}` — #4996C4): Used sparingly on inline links, section eyebrow numbers, and info-state highlights. The system is near-monochrome at the action layer — the accent blue appears rarely and never on primary CTAs.
- **Badge Colors** — Four interface badge fills for thematic data tagging: `{colors.badge-blue}` (#e8edf8 — public data / counts), `{colors.badge-aqua}` (#e1f5fa — private / avatar fills), `{colors.badge-gold}` (#fdf4da — federalized / featured), `{colors.badge-magenta}` (#fde8f2 — special education / categories). Text on these badges uses the darkest shade from the same color family — never generic black or gray. These appear on tag pills and small accent moments — never on hero CTAs.

### Surface
- **Canvas** (`{colors.canvas}` — #ffffff): The default page floor.
- **Surface Soft** (`{colors.surface-soft}` — #f8f9fa): Nav-pill-group background, disabled input fill, very-soft section separators.
- **Surface Card** (`{colors.surface-card}` — #f5f7fa): Feature cards, stat cards, badge pill fills, color swatches. Slightly cool-tinted to read as data-neutral.
- **Surface Dark** (`{colors.surface-dark}` — #242B57): The footer background and featured pricing tier — the only dark surface on every page. Shares the same hex as `{colors.primary}`, creating full-system coherence.
- **Surface Dark Elevated** (`{colors.surface-dark-elevated}` — #1a1f3f): Nested cards inside the dark footer or featured tier.
- **Hairline** (`{colors.hairline}` — #e5e7eb): 0.5px border tone on light surfaces — inputs, table row dividers, raised card outlines.
- **Hairline Soft** (`{colors.hairline-soft}` — #f3f4f6): Nearly invisible divider between bands that share the white canvas.

### Text
- **Ink** (`{colors.ink}` — #242B57): All headlines and primary text. Same hex as primary — every heading is a color-consistent extension of the brand.
- **Body** (`{colors.body}` — #374151): Default running-text color.
- **Muted** (`{colors.muted}` — #707F8F): Secondary text — sub-headings, breadcrumbs, chart axis labels, footer body.
- **Faint** (`{colors.faint}` — #9ca3af): Tertiary text — captions, fine-print, copyright lines.
- **On Primary / On Dark** (`{colors.on-primary}` / `{colors.on-dark}` — #ffffff): Text on primary buttons and dark footer.
- **On Dark Soft** (`{colors.on-dark-soft}` — #a1a1aa): Footer link-row text — slightly muted white.

### Semantic
- **Success** (`{colors.success}` — #28a745): Complete data, verified CURP, successful export.
- **Warning** (`{colors.warning}` — #f59e0b): Partial data, pending municipality report, attention needed.
- **Error** (`{colors.error}` — #ef4444): Validation errors, no access, destructive actions.
- **Info** — Shares `{colors.brand-accent}` (#4996C4) for neutral-positive informational states.

### Educational Level Colors
A fixed eight-color palette used exclusively in charts, tables, legend dots, and level chips. Never repurposed for UI elements. All `nivel-chip` wrappers use a unified `#e8eaf6` background with `#242E57` text — only the inner dot changes to the level's color, ensuring visual consistency across mixed-level displays.

| Token | Hex | Level |
|---|---|---|
| `{colors.nivel-inicial-esc}` | #923792 | Inicial Escolarizado |
| `{colors.nivel-inicial-no-esc}` | #B07CB8 | Inicial No Escolarizado |
| `{colors.nivel-cam}` | #00897B | CAM (Centros de Atención Múltiple) |
| `{colors.nivel-preescolar}` | #FF438E | Preescolar |
| `{colors.nivel-primaria}` | #FFBE00 | Primaria |
| `{colors.nivel-secundaria}` | #58BB00 | Secundaria |
| `{colors.nivel-media-superior}` | #0080B0 | Media Superior |
| `{colors.nivel-superior}` | #021850 | Superior |

USAER has no dedicated color token. It inherits `{colors.nivel-cam}` as a proxy for special education services, or adopts the color of the level it supports depending on the product context.

## Typography

### Font Family
The system runs **Cal Sans** for display headlines and **Hanken Grotesk** for all UI and body type. JetBrains Mono handles code, SQL queries, and token references. Cal Sans is loaded from `cdn.jsdelivr.net/npm/cal-sans@1.0.1`. If unavailable, Hanken Grotesk at weight 600 with `-0.04em` letter-spacing is the closest substitute.

The split is functional:
- Cal Sans (display, 600 weight, -0.3 to -1.5px tracking) — h1, h2, h3, hero, stat values
- Hanken Grotesk (body + UI, 400–600 weight, 0 letter-spacing) — paragraphs, labels, buttons, nav, captions
- JetBrains Mono (400) — code blocks, SQL, technical values

### Hierarchy

| Token | Size | Weight | Line Height | Letter Spacing | Use |
|---|---|---|---|---|---|
| `{typography.display-xl}` | 52px | 600 | 1.0 | -1.5px | Hero h1 ("Design System v2.0") — Cal Sans |
| `{typography.display-lg}` | 36px | 600 | 1.1 | -1px | Section heads ("Tableros Municipales") — Cal Sans |
| `{typography.display-md}` | 28px | 600 | 1.15 | -0.5px | Sub-section heads, large card titles — Cal Sans |
| `{typography.display-sm}` | 22px | 600 | 1.2 | -0.3px | CTA-band heads, stat-card values — Cal Sans |
| `{typography.title-lg}` | 22px | 600 | 1.3 | -0.2px | Plan names, section titles — Hanken Grotesk |
| `{typography.title-md}` | 18px | 600 | 1.4 | 0 | Feature card titles, intro paragraphs |
| `{typography.title-sm}` | 16px | 600 | 1.4 | 0 | Small card titles, list labels |
| `{typography.body-md}` | 16px | 400 | 1.5 | 0 | Default running-text |
| `{typography.body-sm}` | 14px | 400 | 1.5 | 0 | Footer body, fine-print, secondary descriptions |
| `{typography.caption}` | 12px | 500 | 1.4 | 0.3px | Metric labels, data captions, source lines |
| `{typography.section-eyebrow}` | 11px | 600 | 1.0 | 2px | Section number + name in uppercase — `{colors.brand-accent}` |
| `{typography.code}` | 13px | 400 | 1.5 | 0 | Code, SQL queries, token refs — JetBrains Mono |
| `{typography.button}` | 13px | 600 | 1.0 | 0 | Button labels |
| `{typography.nav-link}` | 13px | 500 | 1.4 | 0 | Top-nav items |

### Principles
Cal Sans is the brand voice — every display headline uses it. Hanken Grotesk handles the supporting type. The boundary is strict: never put body copy in Cal Sans, never put a display headline in Hanken Grotesk. Cal Sans without negative letter-spacing reads as off-brand — the -0.3 to -1.5px tracking is part of the voice.

Display weight stays at 600 across all sizes — never 700, never 500. The middle weight is what makes Cal Sans read as institutional-precise without becoming heavy.

The `{typography.section-eyebrow}` style in `{colors.brand-accent}` — uppercase, 11px, 2px tracking — is the editorial organizer of every documentation and design page. It provides section numbering without requiring additional borders or dividers.

### Note on Font Substitutes
If Cal Sans is unavailable, **Hanken Grotesk** at weight 600 with `-0.04em` letter-spacing is the closest approximation available in this stack. The geometric character of Cal Sans differs from Hanken's humanist forms, but the weight and tracking signature are preserved. **Manrope** at weight 700 is another viable alternative.

## Layout

### Spacing System
- **Base unit:** 4px.
- **Tokens:** `{spacing.xxs}` 4px · `{spacing.xs}` 8px · `{spacing.sm}` 12px · `{spacing.md}` 16px · `{spacing.lg}` 24px · `{spacing.xl}` 32px · `{spacing.xxl}` 48px · `{spacing.section}` 96px.
- **Section padding:** `{spacing.section}` (96px) — the universal vertical rhythm between editorial bands.
- **Card internal padding:** `{spacing.xl}` (32px) for feature cards and stat cards; `{spacing.lg}` (24px) for data and product-mockup cards.
- **Gutters:** `{spacing.lg}` (24px) between cards in 3-up grids; `{spacing.md}` (16px) in 4-up grids; `{spacing.sm}` (12px) for chips and inline badges.

### Grid & Container
- **Max content width:** 1200px centered.
- **Editorial body:** Hero band uses a 5fr/4fr grid split — text content left, app-mockup card right.
- **Feature card grids:** 3-up at desktop, 2-up at tablet, 1-up at mobile.
- **Stat cards:** 4-up at desktop, 2-up at tablet, 1-up at mobile.
- **Color swatches:** 4-up grid (`repeat(4, 1fr)`).
- **Footer:** 3-column link grid inline; 4-column at full-page footer.

### Whitespace Philosophy
SEDEQ's system uses generous but calibrated whitespace — 96px between sections, 24–32px internal card padding. The rhythm is designed for fast scanning: every section has a single headline + description + supporting components, never densely-packed lists. The result reads as institutional-trustworthy, not bureaucratic-dense.

## Elevation & Depth

| Level | Treatment | Use |
|---|---|---|
| Flat | No shadow, no border | Body sections, top nav, hero bands |
| Soft hairline | 0.5px `{colors.hairline}` border | Inputs, table row dividers, `{component.card-raised}` |
| Card surface | `{colors.surface-card}` background — no shadow | Feature cards, stat cards, color swatches |
| Subtle drop shadow | `0 2px 12px rgba(36,43,87,0.07)` | Hero app-mockup card |
| Featured tier | `{colors.surface-dark}` background, no extra shadow | Footer, featured pricing tier — color contrast does the elevation work |

The elevation philosophy is soft and precise — the system uses a single tonal shadow at low alpha, colored with the system's own navy blue (`rgba(36,43,87,...)`), never generic black. No heavy shadows, no neumorphism, no glassmorphism.

### Decorative Depth
- Avatar circles use tonal fills derived from badge colors — e.g. aqua fill (#e1f5fa) with dark aqua initials (#0f6779). This adds chromatic warmth without breaking the near-monochrome brand voice.
- Level dots (`nivel-dot`) are the only purely categorical color elements in the UI — 9px circles inside table rows and chart legends. Their color comes exclusively from the `{colors.nivel-*}` palette.

## Shapes

### Border Radius Scale

| Token | Value | Use |
|---|---|---|
| `{rounded.xs}` | 4px | Bar chart fill corners, minimal accents |
| `{rounded.sm}` | 6px | Small buttons, dropdown items, `nivel-chip` |
| `{rounded.md}` | 8px | Standard CTA buttons, text inputs, category tabs |
| `{rounded.lg}` | 12px | Content cards, feature cards, data table wrapper |
| `{rounded.xl}` | 16px | Hero app-mockup card — the marquee component |
| `{rounded.pill}` | 9999px | Nav-pill-group, badge pills, nivel-chips |
| `{rounded.full}` | 9999px / 50% | Avatars, icon buttons |

## Components

### Top Navigation

**`top-nav`** — White nav bar pinned to the top of every page. 56px tall, `{colors.canvas}` background, 0.5px bottom border in `{colors.hairline}`, `position: sticky`. Carries the brand name in Cal Sans at left, section links in `{typography.nav-link}` center, and a small `{component.button-primary}` at right with a download or action icon.

**`nav-pill-group`** — A pill-radius wrapper around 2–3 sub-nav segments (e.g. Municipal / Estatal / Nacional; Público / Privado). Background `{colors.surface-soft}` with 4px internal padding, rounded `{rounded.pill}`. Active segment renders as a white-canvas pill with a subtle drop shadow inside the wrapper. The pill-in-pill treatment is one of the system's signature interactive components.

### Buttons

**`button-primary`** — The institutional primary CTA. Background `{colors.primary}` (#242B57), text `{colors.on-primary}`, type `{typography.button}` (Hanken Grotesk 13px / 600), padding 9px × 18px, height 38px, rounded `{rounded.md}` (8px). Active state `button-primary-active` shifts to `{colors.primary-active}` (#1a1f3f).

**`button-secondary`** — White button with hairline outline. Background `{colors.canvas}`, text `{colors.ink}`, 0.5px hairline border, same padding + height + radius as primary. Hover shifts background to `{colors.surface-soft}`.

**`button-ghost`** — Outline button in accent color. Transparent background, text and border in `{colors.brand-accent}`. Used for lower-weight actions like "Ver fuente" or "Changelog".

**`button-danger`** — Destructive action. Background `{colors.error}` (#ef4444), white text. Used only for delete, revoke access, or irreversible actions.

**`button-primary-disabled`** — Background `{colors.primary-disabled}` (#e5e7eb), text `{colors.muted}`, `pointer-events: none`. The flat tone communicates inaction without relying on opacity — intentional.

**`button-sm`** / **`button-lg`** — Size variants. sm: 12px font, 6px × 12px padding, `{rounded.sm}`. lg: 15px font, 12px × 24px padding, 10px radius.

**`button-icon`** — Square icon-only button. 9px padding all sides, `{rounded.md}`. Uses Tabler Icons outline style.

### Cards & Containers

**`hero-band`** — White-canvas hero with a 5fr/4fr grid: h1 + sub-headline + button row at left, `{component.hero-app-mockup-card}` at right. Padding `4.5rem 2rem 3.5rem`.

**`hero-app-mockup-card`** — Shows live system metrics or product data directly — enrollment counts, school stats, active badges. Background `{colors.canvas}`, 0.5px hairline border, rounded `{rounded.xl}` (16px), shadow `0 2px 12px rgba(36,43,87,0.07)`. The system shows real data, not marketing illustrations of data.

**`metric-mini-card`** — Compact metric tile used in 3-up grids inside the hero card. Background `{colors.surface-card}`, rounded `{rounded.md}`, padding 10px 12px. Value in `{typography.display-sm}` (Cal Sans), label in `{typography.caption}` uppercase.

**`stat-card`** — Primary KPI card used in 4-up grids. Background `{colors.surface-soft}`, rounded `{rounded.md}`, padding 1rem. 12px muted label above; Cal Sans 26px value below; color-semantic trend delta with Tabler trending icon.

**`feature-card`** — Used in 3-up grids to present product capabilities. Background `{colors.surface-card}` (#f5f7fa), rounded `{rounded.lg}` (12px), padding 1.25rem. Carries a 36px feat-icon with tonal background, title in `{typography.feat-title}`, description in `{typography.feat-desc}`.

**`card-raised`** — White card with hairline border used for elevated data objects, UI mockups, and color swatches. Background `{colors.canvas}`, 0.5px `{colors.hairline}` border, rounded `{rounded.lg}`.

**`dark-footer`** — Inline dark surface used inside content sections for featured tiers and multi-column link lists. Background `{colors.surface-dark}` (#242B57), rounded `{rounded.lg}`, 3-column grid. Column headings at 10px / 700 in `rgba(255,255,255,0.35)` uppercase. Link text in `rgba(255,255,255,0.55)`, hovering to `rgba(255,255,255,0.85)`.

**`footer`** — Full-page dark footer closing every page. Background `{colors.surface-dark}`, padding 64px, 4-column link grid. Brand name in Cal Sans white top-left. The footer is the only dark surface on every page — the deliberate inversion visually closes the scroll.

### Inputs & Forms

**`text-input`** — Standard text input. Background `{colors.canvas}`, text `{colors.ink}`, type `{typography.body-sm}`, rounded `{rounded.md}` (8px), padding 9px × 13px, height 38px. 0.5px `{colors.hairline}` border.

**`text-input-focused`** — Focus state. Border shifts to `{colors.primary}` with a soft focus ring `0 0 0 3px rgba(36,43,87,0.08)`.

**`text-input-error`** — Border shifts to `{colors.error}`. Accompanied by an `input-err-msg` in 11px error color with a `ti-alert-circle` Tabler icon prefix.

**`text-input-success`** — Border shifts to `{colors.success}`. Accompanied by an `input-hint` in 11px success color with a `ti-circle-check` icon.

**`text-input-disabled`** — Background `{colors.surface-soft}`, text `{colors.muted}`, `cursor: not-allowed`. Non-interactive field with a hint note explaining the reason.

### Tags / Badges

**`badge-pill`** — Small pill label for data category tagging and status states. Four interface variants (blue, aqua, gold, magenta) for thematic classification; four semantic variants (success, warn, error, muted) for system states. Type `{typography.caption}` (12px / 600), rounded `{rounded.pill}`, padding 3px × 9px. May carry a 12px Tabler icon prefix.

**`nivel-chip`** — Level legend chip for charts and tables. Always uses unified `#e8eaf6` background with `#242E57` text — only the inner 8px dot changes to the level's `{colors.nivel-*}` color. This ensures visual consistency across mixed-level displays. Rounded `{rounded.sm}` (6px), 0.5px border `#c5cae9`, padding 4px 10px, font 11px / 600.

**`nivel-dot`** — 9px diameter circle used inline in table cells to identify the educational level of a record. Color comes exclusively from the `{colors.nivel-*}` palette.

**`avatar-circle`** — 36px diameter, `{rounded.full}`. Tonal fill derived from badge colors — fill and text share the same color family (light fill, dark text). Independent from the level color palette.

### Alerts

Four variants — info, success, warn, error — each with a tonal background, dark same-family text, a 16px Tabler icon at left, a 13px / 700 title, and a 12px / 400 description. Rounded `{rounded.md}`, padding 12px × 14px.

### Data Table

**`data-table`** — Standard statistical data table. Header row in `{colors.primary}` (#242B57), white text, 12px / 600, 10px 12px padding. Data cells in `{colors.body}`, 13px / 400, equal padding. Alternating rows in `{colors.surface-soft}`. Row dividers at 0.5px `{colors.hairline-soft}`. Total row: `{colors.surface-card}` background, font-weight 700, 0.5px `{colors.hairline}` top border. Level dots (`nivel-dot`) inline in the first column. Table wrapper uses `{rounded.lg}` with `overflow: hidden` so header corners align with the card edge.

### Charts & Data Visualization

Charts follow these principles:

1. **Color encodes category, not sequence.** Never cycle colors like a rainbow — in level data, each bar or line uses its exact `{colors.nivel-*}` token.
2. **Custom HTML legend** — never the default Chart.js legend. Custom legends use 10×10px squares with `{rounded.xs}` and inline value or percentage.
3. **Multi-series lines** — differentiate with `borderDash` per series in addition to color, for accessibility.
4. **Native HTML bar charts** (CSS-only) are preferred over Chart.js for simple comparisons — the `.bar-track` / `.bar-fill` pattern keeps the system consistent with no external dependency.
5. **Axis ticks and grid** — tick color `{colors.muted}`, grid color `rgba(0,0,0,0.06)`. Font family Hanken Grotesk in all Chart.js configurations.
6. **Canvas accessibility** — every `<canvas>` carries `role="img"`, a descriptive `aria-label`, and fallback text between the tags.

### CTA / Footer

**`dark-footer` (inline)** — Used inside page sections for featured tiers and multi-column navigation blocks. Background `{colors.surface-dark}` in a 3-column grid. See Cards section above.

**`footer` (full-page)** — Closes every page. Background `{colors.surface-dark}`, 4-column link grid, 64px padding. The Cal Sans brand name anchors the top-left in white. The footer is the only dark surface on every page — the deliberate inversion is part of the editorial rhythm.

## Do's and Don'ts

### Do
- Reserve `{colors.primary}` (#242B57) for primary CTAs and h1/h2 type. The button is institutional navy — not black, not blue-accent.
- Use Cal Sans for every display headline. Pair with Hanken Grotesk body. Never blur the boundary.
- Apply negative letter-spacing on display sizes (-0.3 to -1.5px). Cal Sans without it reads as off-brand.
- Use `{colors.nivel-*}` colors exclusively for data representation — charts, table dots, legend chips. Never on buttons, nav, or UI structure.
- Keep `nivel-chip` wrappers unified (`#e8eaf6` bg / `#242E57` text) with only the inner dot changing color. Consistency of the chip matters more than per-level text color variation.
- Use `{component.feature-card}` (`{colors.surface-card}`) and `{component.card-raised}` (white + hairline) deliberately — card surface signals "data display", raised card signals "interactive UI object".
- End every page with the dark footer. The light-to-dark transition is part of the editorial rhythm.
- Use `{component.nav-pill-group}` for sub-view selectors. The pill-in-pill treatment is the system's signature interactive component.

### Don't
- Don't use the educational level color palette (`{colors.nivel-*}`) on buttons, navigation, or any interface action element.
- Don't bold display weight beyond 600. Cal Sans at 700 loses the institutional-precise voice and reads as heavy.
- Don't use rounded radius beyond `{rounded.xl}` (16px) on cards. Larger radii read as consumer-app, not government data platform.
- Don't put dark surface cards anywhere except the footer and the featured pricing tier. The dark surface is a deliberate, scarce signal.
- Don't repeat the same surface mode in consecutive bands. The system alternates: white → surface-card → white → dark-footer.
- Don't use generic black box-shadows — all shadows in this system carry the navy tint `rgba(36,43,87,...)` for tonal coherence.
- Don't mix `{colors.brand-accent}` (#4996C4) with the level color palette. The accent is for links and eyebrows; level colors are for data.

## Responsive Behavior

### Breakpoints

| Name | Width | Key Changes |
|---|---|---|
| Mobile | < 768px | Hamburger nav; hero h1 52→28px; hero grid to single column; feature/stat cards 1-up; footer 4 cols → 1 |
| Tablet | 768–1024px | Nav stays horizontal but tightens; nav-pill-group wraps; feature cards 2-up; stat cards 2-up |
| Desktop | 1024–1440px | Full nav; hero 5fr/4fr grid; 3-up feature cards; 4-up stat cards |
| Wide | > 1440px | Same as desktop with more outer breathing room; max-width 1200px |

### Touch Targets
- `{component.button-primary}` height 38px — functional for touch.
- `{component.button-icon}` 9px padding with an 18px icon — effective tap area ~36px; acceptable given full-circle visual silhouette.
- `{component.text-input}` height 38px.
- `{component.nav-pill-group}` tabs have sufficient vertical padding for 44px+ effective tap area inside the wrapper.

### Collapsing Strategy
- Top nav collapses to hamburger at < 768px; menu opens as a full-screen sheet.
- Hero 5fr/4fr grid collapses to single-column on mobile — text and buttons first, app-mockup card below.
- Feature and stat grids reduce columns rather than scaling cards down.
- The dark footer maintains its visual contrast signal at every breakpoint.
- Nav-pill-group wraps to multi-row on tablet if segments don't fit horizontally.
- Data tables on mobile get a wrapper with `overflow-x: auto` — the table structure is preserved, not collapsed.

### Image Behavior
- Product data fragments and charts inside cards retain native aspect ratios; the cards resize.
- Avatar circles crop to perfect circles at every breakpoint.
- Hero app-mockup card scales proportionally on mobile — metric values stay legible.

## Iteration Guide

1. Focus on ONE component at a time. Reference its YAML key directly (`{component.feature-card}`, `{component.data-table}`).
2. Variants of an existing component (`-active`, `-disabled`, `-focused`, `-error`) live as separate entries in `components:`.
3. Use `{token.refs}` everywhere — never inline hex in production code.
4. Never document hover. Default and Active/Pressed states only.
5. Display headlines stay Cal Sans 600 with negative letter-spacing. Body stays Hanken Grotesk 400. The boundary does not blur.
6. The dark footer is the only dark surface on most pages. Don't add other dark cards casually.
7. Educational level colors are a data palette, not a UI palette. Keep them out of the interface layer.
8. When a new educational service has no assigned level color, use `{colors.nivel-cam}` as a temporary proxy until the token is formally defined.
9. When in doubt about emphasis: bigger Cal Sans before bolder Cal Sans.

## Known Gaps

- USAER has no dedicated color token. It inherits `{colors.nivel-cam}` or the color of the level it supports depending on product context. A formal token should be defined in the next iteration.
- The data table in section 06 of the preview HTML still uses some original hex values for level dots that differ from the updated `{colors.nivel-*}` palette. These need to be unified in the next HTML iteration.
- Hover states are intentionally undocumented per the iteration guide policy.
- Animation and transition timings (chart load, pill-group active transition, input focus ring) are not in scope for this version.
- A donut/pie chart component for público vs. privado enrollment distribution has been identified as the next component to specify for the Tableros Municipales module.
- Full dark mode (interface-level) is out of scope — only `{colors.surface-dark}` footer and inline dark-footer surfaces are specified.
- Form validation states beyond the four documented input variants would require sign-up and data-entry flow screenshots to confirm edge cases.