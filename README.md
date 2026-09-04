# Sistema de Incorporación de Escuelas (SEDEQ)

Digitalización del trámite de incorporación de escuelas ante SEDEQ. MVP cubre
Educación Básica (Inicial, Preescolar, Primaria, Secundaria), Pasos 1-3 del
trámite.

## Estructura

- `app-laravel/` — aplicación Laravel 11 + Livewire + Filament (backend,
  frontend del solicitante y panel administrativo SEDEQ, una sola app).
- `docs/` — documentación de referencia: compendio normativo, PRD. El DDL de
  base de datos y las instrucciones de entorno viven localmente en `docs/`
  pero están excluidos del repositorio (ver `.gitignore`); solicítalos al
  equipo si los necesitas.

## Stack

Laravel 11 · Livewire 4 · Filament 4 · PostgreSQL 18 · `spatie/laravel-permission`
· `barryvdh/laravel-dompdf` · Laravel Breeze.

## Setup

Ver `docs/INSTRUCCIONES_SETUP_ENTORNO.md` (local, no versionado — solicítalo al equipo).
