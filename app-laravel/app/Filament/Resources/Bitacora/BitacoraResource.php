<?php

namespace App\Filament\Resources\Bitacora;

use App\Application\Auditoria\EtiquetasBitacora;
use App\Filament\Resources\Bitacora\Pages\ListBitacora;
use App\Models\BitacoraAccion;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Bitácora global de acciones del admin y del sistema. Solo lectura: la tabla es append-only. */
class BitacoraResource extends Resource
{
    protected static ?string $model = BitacoraAccion::class;

    protected static ?string $modelLabel = 'acción';

    protected static ?string $pluralModelLabel = 'bitácora';

    protected static ?string $navigationLabel = 'Bitácora';

    protected static ?string $slug = 'bitacora';

    protected static ?int $navigationSort = 98;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor', 'escuelaObjeto.ternasNombres', 'usuarioObjeto', 'plantelObjeto']);
    }

    /** Nombre legible del objeto afectado; "Ya no existe" si se purgó. */
    public static function nombreObjeto(BitacoraAccion $r): string
    {
        $nombre = match ($r->objeto_tipo) {
            'escuela' => $r->escuelaObjeto === null ? null : ($r->escuelaObjeto->nombre_aprobado
                ?? $r->escuelaObjeto->ternasNombres->sortBy('numero_propuesta')->first()->nombre_propuesto
                ?? 'Trámite sin nombre'),
            'user' => $r->usuarioObjeto?->name,
            'plantel' => $r->plantelObjeto === null ? null : trim("{$r->plantelObjeto->calle} {$r->plantelObjeto->numero_ext}, {$r->plantelObjeto->colonia}"),
            default => 'Sistema',
        };

        return $nombre ?? 'Ya no existe';
    }

    private static function colorAccion(string $accion): string
    {
        return match (true) {
            str_starts_with($accion, 'archivar_') => 'warning',
            str_starts_with($accion, 'restaurar_') => 'success',
            str_starts_with($accion, 'purgar_'), $accion === 'descartar_fallidos' => 'danger',
            $accion === 'cambiar_rol', $accion === 'crear_cuenta_sedeq' => 'info',
            default => 'gray',
        };
    }

    private static function iconoAccion(string $accion): string
    {
        return match (true) {
            str_starts_with($accion, 'archivar_') => 'heroicon-m-archive-box',
            str_starts_with($accion, 'restaurar_') => 'heroicon-m-arrow-uturn-left',
            str_starts_with($accion, 'purgar_'), $accion === 'descartar_fallidos' => 'heroicon-m-trash',
            $accion === 'cambiar_rol', $accion === 'crear_cuenta_sedeq' => 'heroicon-m-user-circle',
            default => 'heroicon-m-cog-6-tooth',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')->label('Cuándo')->sortable()
                    ->date('d/m/Y')->weight('medium')
                    ->description(fn (BitacoraAccion $r) => $r->created_at->format('H:i').' h'),
                TextColumn::make('actor.name')->label('Quién')->icon('heroicon-m-user')->iconColor('gray'),
                TextColumn::make('accion')->label('Acción')->badge()
                    ->formatStateUsing(fn (string $state) => EtiquetasBitacora::accion($state))
                    ->color(fn (string $state) => self::colorAccion($state))
                    ->icon(fn (string $state) => self::iconoAccion($state)),
                TextColumn::make('objeto_id')->label('Sobre qué')->searchable()
                    ->state(fn (BitacoraAccion $r) => self::nombreObjeto($r))
                    ->weight('medium')->wrap()
                    ->description(fn (BitacoraAccion $r) => $r->objeto_tipo === 'sistema'
                        ? null
                        : (EtiquetasBitacora::OBJETOS[$r->objeto_tipo] ?? $r->objeto_tipo).' · #'.$r->objeto_id),
                TextColumn::make('motivo')->label('Motivo')->wrap()->placeholder('—')->color('gray'),
                TextColumn::make('detalle')->label('Detalle')->color('gray')->size('sm')->placeholder('—')
                    ->state(fn (BitacoraAccion $r) => EtiquetasBitacora::resumen($r->detalle) ?: null),
            ])
            ->filters([
                SelectFilter::make('accion')->label('Acción')->options(EtiquetasBitacora::ACCIONES),
                SelectFilter::make('actor_id')->label('Quién')
                    ->options(fn () => User::whereIn('id', BitacoraAccion::select('actor_id'))->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('objeto_tipo')->label('Objeto')->options(EtiquetasBitacora::OBJETOS),
                Filter::make('fecha')->label('Fecha')
                    ->schema([DatePicker::make('desde')->label('Desde'), DatePicker::make('hasta')->label('Hasta')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<=', $d.' 23:59:59'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBitacora::route('/')];
    }
}
