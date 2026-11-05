<?php

namespace App\Filament\Pages;

use App\Application\Reportes\ConsultarReportes;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes de solo lectura, una pestaña por sección y paginados; el CSV baja el detalle por registro. */
class Reportes extends Page
{
    protected static ?string $navigationLabel = 'Reportes';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Reportes';

    protected static ?string $slug = 'reportes';

    protected string $view = 'filament.pages.reportes';

    public string $seccion = 'por_estado';

    public int $pagina = 1;

    public int $porPagina = 10;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        // ponytail: se calculan todas las secciones y se corta en memoria; pasar a paginar en SQL si crecen a decenas de miles.
        $secciones = app(ConsultarReportes::class)->secciones();
        $actual = $secciones[$this->seccion] ?? $secciones['por_estado'];
        $total = count($actual['filas']);
        $paginas = max(1, (int) ceil($total / $this->porPagina));
        $pagina = min(max(1, $this->pagina), $paginas);

        return [
            'secciones' => $secciones,
            'actual' => $actual,
            'filas' => array_slice($actual['filas'], ($pagina - 1) * $this->porPagina, $this->porPagina),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'desde' => $total === 0 ? 0 : ($pagina - 1) * $this->porPagina + 1,
            'hasta' => min($total, $pagina * $this->porPagina),
        ];
    }

    public function elegir(string $seccion): void
    {
        $this->seccion = $seccion;
        $this->pagina = 1;
    }

    public function siguiente(): void
    {
        $total = count(app(ConsultarReportes::class)->secciones()[$this->seccion]['filas'] ?? []);
        $this->pagina = min($this->pagina + 1, max(1, (int) ceil($total / $this->porPagina)));
    }

    public function anterior(): void
    {
        $this->pagina = max(1, $this->pagina - 1);
    }

    public function updatedPorPagina(): void
    {
        $this->pagina = 1;
    }

    public function descargar(string $seccion): StreamedResponse
    {
        $reportes = app(ConsultarReportes::class);
        $datos = $reportes->secciones()[$seccion] ?? abort(404);

        return response()->streamDownload(
            fn () => print $reportes->csv($datos),
            "reporte-{$seccion}-".now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
