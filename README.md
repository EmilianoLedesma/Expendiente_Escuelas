<div align="center">

<img width="100%" src="https://capsule-render.vercel.app/api?type=waving&color=0:1E3A5F,100:C9A227&height=200&section=header&text=SEDEQ%20%C2%B7%20Incorporaci%C3%B3n&fontSize=54&fontColor=F5F0E1&fontAlignY=38&desc=Sistema%20de%20Incorporaci%C3%B3n%20de%20Escuelas&descAlignY=58&descSize=18&animation=fadeIn" />

<br/>

<img src="https://readme-typing-svg.demolab.com?font=Fira+Code&size=16&duration=2500&pause=900&color=1E3A5F&center=true&vCenter=true&width=560&lines=Tr%C3%A1mite+de+incorporaci%C3%B3n+100%25+digital;Wizard+del+solicitante+%2B+panel+SEDEQ;Motor+de+validaci%C3%B3n+de+capacidad+instalada;Laravel+13+%2B+Livewire+4+%2B+Filament+4" alt="Typing SVG" />

<br/><br/>

[![PHP](https://img.shields.io/badge/PHP-8.4-1E3A5F?style=for-the-badge&logo=php&logoColor=F5F0E1)](app-laravel/composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-13-C9A227?style=for-the-badge&logo=laravel&logoColor=1E3A5F)](app-laravel/composer.json)
[![Livewire](https://img.shields.io/badge/Livewire-4-1E3A5F?style=for-the-badge&logo=livewire&logoColor=F5F0E1)](app-laravel/composer.json)
[![Filament](https://img.shields.io/badge/Filament-4-C9A227?style=for-the-badge&logo=php&logoColor=1E3A5F)](app-laravel/composer.json)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-1E3A5F?style=for-the-badge&logo=postgresql&logoColor=F5F0E1)](docs/ddl_sistema_incorporacion_v3.sql)

</div>

<br/>

> **Sistema de Incorporación de Escuelas** digitaliza el trámite que una
> escuela particular sigue ante la SEDEQ para obtener su incorporación:
> captura de expediente, validación normativa de instalaciones y personal,
> y revisión administrativa — sin papel, sin visitas repetidas a ventanilla.
>
> MVP cubre Educación Básica (Inicial, Preescolar, Primaria, Secundaria),
> Pasos 1 a 3 del trámite.

<br/>

<div align="center">

### Tabla de contenidos

[El problema](#el-problema) · [Stack](#stack) · [Arquitectura](#arquitectura-de-la-app) · [Estructura](#estructura-del-repositorio) · [Empezar](#empezar) · [Tests](#tests) · [Documentación](#documentación)

</div>

---

## El problema

Incorporar una escuela ante SEDEQ hoy es un proceso de expediente físico:
formatos en papel, validación manual de capacidad instalada (superficies,
personal, mobiliario) contra normativa dispersa en varios acuerdos
secretariales, y ciclos de revisión lentos por ida y vuelta entre
solicitante y ventanilla. Este sistema mueve todo el trámite a un wizard
digital para el solicitante y un panel de revisión para SEDEQ, con un
**motor de validación** que aplica las reglas normativas de forma
consistente en cada expediente.

<br/>

## Stack

<div align="center">

| Capa | Tecnología |
|:--|:--|
| **Backend** | `Laravel 13` (PHP 8.4) |
| **UI reactiva** | `Livewire 4` |
| **Panel admin** | `Filament 4` (`/admin`) |
| **Base de datos** | `PostgreSQL 18` |
| **Roles / permisos** | `spatie/laravel-permission` |
| **Generación de PDF** | `barryvdh/laravel-dompdf` |
| **Autenticación** | `Laravel Breeze` |
| **Build** | `Vite` + `Tailwind` |

</div>

> El PRD original especificaba Laravel 11 / Livewire 3 / Filament 3 /
> PostgreSQL 16; se actualizó a la versión estable vigente al momento del
> setup porque las originales no eran instalables en conjunto. Ver
> `docs/progress.md` (Decisions Log) para el detalle completo.

<br/>

## Arquitectura de la app

Diseño **domain-first** / monolito modular dentro de `app-laravel/app/` —
no el split clásico Controllers/Services/Repositories. La lógica de
negocio vive en `Domain/` e `Infrastructure/`, nunca en controllers,
componentes Livewire, Filament Resources o modelos Eloquent.

```
app-laravel/app/
├── Domain/            # Reglas de negocio, agnósticas del framework
│   └── Validaciones/
│       └── Engine/     # Motor de Validación de Capacidad Instalada
├── Infrastructure/     # PDF, documentos, integraciones concretas
├── Http/
│   ├── Controllers/
│   └── Livewire/       # Componentes — presentación, delgados
│       └── Tramite/     # Wizard: Paso1Preregistro … Paso3/*
├── Filament/
│   └── Resources/      # Panel administrativo SEDEQ (/admin)
└── Models/
```

El **Motor de Validación de Capacidad Instalada** (`Domain/Validaciones/Engine/`)
es la pieza más crítica del sistema: aplica las reglas normativas de
superficie, personal y mobiliario por nivel educativo.

<br/>

## Estructura del repositorio

```
sedeq-incorporacion/
├── app-laravel/    # Aplicación Laravel: solicitante, wizard, panel SEDEQ
└── docs/           # PRD, compendio normativo, bitácora de avance
```

Monorepo, app única — sin split en repos/servicios por diseño (una sola
base de datos como contrato compartido, sin necesidades de despliegue
independiente).

El DDL de base de datos (`ddl_sistema_incorporacion_v3.sql`) y las
instrucciones de entorno (`INSTRUCCIONES_SETUP_ENTORNO.md`) viven
localmente en `docs/` pero están excluidos del repositorio (ver
`.gitignore`) — solicítalos al equipo si los necesitas.

<br/>

## Empezar

```bash
cd app-laravel
composer install
npm install

php artisan serve   # http://127.0.0.1:8000 — panel admin en /admin
npm run dev          # o `composer run dev` para levantar ambos
```

Setup completo de entorno: `docs/INSTRUCCIONES_SETUP_ENTORNO.md` (local,
no versionado — solicítalo al equipo).

<br/>

## Tests

```bash
cd app-laravel
composer test          # limpia config cache, luego `php artisan test`
php artisan test tests/Feature/Auth/AuthenticationTest.php   # un archivo
php artisan test --filter=test_method_name                    # un test
```

<br/>

## Documentación

| Documento | Contenido |
|:--|:--|
| `docs/PRD_Sistema_Incorporacion_MVP.md` | Requerimientos del MVP |
| `docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md` | Reglas normativas / de negocio |
| `docs/progress.md` | Bitácora de avance (append-only) con Decisions Log |

<br/>

<div align="center">

<img width="100%" src="https://capsule-render.vercel.app/api?type=waving&color=0:1E3A5F,100:C9A227&height=100&section=footer" />

</div>
