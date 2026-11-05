<?php

namespace App\Filament\Resources\Planteles;

use App\Application\Archivo\ArchivarPlantel;
use App\Application\Archivo\RestaurarPlantel;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Filament\Resources\Planteles\Pages\ListPlanteles;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Plantel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Pantalla de solo lectura de planteles; el único cambio posible es archivar o restaurar. */
class PlantelResource extends Resource
{
    protected static ?string $model = Plantel::class;

    protected static ?string $modelLabel = 'plantel';

    protected static ?string $pluralModelLabel = 'planteles';

    protected static ?string $navigationLabel = 'Planteles';

    protected static ?string $slug = 'planteles';

    public static function canCreate(): bool
    {
        return false;
    }

    /** Conteos directos a la tabla: el scope EscuelaNoArchivada ocultaría justo los archivados. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'planteles.*',
            'tramites_total' => DB::table('escuelas')->selectRaw('count(*)')->whereColumn('escuelas.plantel_id', 'planteles.id'),
            'tramites_activos' => DB::table('escuelas')->selectRaw('count(*)')->whereColumn('escuelas.plantel_id', 'planteles.id')->whereNull('escuelas.archivado_at'),
        ]);
    }

    private static function archivado(Plantel $r): bool
    {
        return (int) $r->tramites_total > 0 && (int) $r->tramites_activos === 0;
    }

    private static function intentar(Closure $accion, string $exito): void
    {
        try {
            $accion();
        } catch (PrecondicionIncumplida $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        Notification::make()->success()->title($exito)->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('calle')->label('Dirección')->searchable()
                    ->state(fn (Plantel $r) => trim("{$r->calle} {$r->numero_ext}, {$r->colonia}")),
                TextColumn::make('municipio')->label('Municipio')->searchable(),
                TextColumn::make('codigo_postal')->label('C.P.'),
                TextColumn::make('tramites')->label('Trámites activos')
                    ->state(fn (Plantel $r) => "{$r->tramites_activos} de {$r->tramites_total}"),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (Plantel $r) => self::archivado($r) ? 'Archivado' : 'Activo')
                    ->color(fn (string $state) => $state === 'Archivado' ? 'gray' : 'success'),
            ])
            ->recordActions([
                Action::make('archivar')->label('Archivar plantel')->color('warning')
                    ->visible(fn (Plantel $r) => (int) $r->tramites_activos > 0)
                    ->requiresConfirmation()
                    ->modalDescription('Se archivan todos los trámites activos de este plantel. Los solicitantes dejan de verlos; se puede deshacer con Restaurar.')
                    ->schema([Textarea::make('motivo')->label('Motivo (opcional)')->helperText(UsuarioResource::AVISO_MOTIVO)])
                    ->action(fn (Plantel $r, array $data) => self::intentar(
                        fn () => app(ArchivarPlantel::class)->ejecutar($r->id, (int) auth()->id(), $data['motivo']),
                        'Plantel archivado.')),
                Action::make('restaurar')->label('Restaurar plantel')
                    ->visible(fn (Plantel $r) => self::archivado($r))
                    ->requiresConfirmation()
                    ->action(fn (Plantel $r) => self::intentar(
                        fn () => app(RestaurarPlantel::class)->ejecutar((int) auth()->id(), $r->id),
                        'Plantel restaurado.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPlanteles::route('/')];
    }
}
