<?php

namespace App\Filament\Resources\TramitesArchivados;

use App\Application\Archivo\ArchivarTramite;
use App\Application\Archivo\PurgarTramite;
use App\Application\Archivo\RestaurarTramite;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Filament\Resources\TramitesArchivados\Pages\ListTramitesArchivados;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Escuela;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TramiteArchivadoResource extends Resource
{
    protected static ?string $model = Escuela::class;

    protected static ?string $modelLabel = 'trámite';

    protected static ?string $pluralModelLabel = 'trámites';

    protected static ?string $navigationLabel = 'Trámites';

    protected static ?string $slug = 'tramites';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(EscuelaNoArchivada::class)
            ->addSelect(['escuelas.*', 'archivado_por_nombre' => User::select('name')->whereColumn('users.id', 'escuelas.archivado_por')])
            ->with(['plantel', 'solicitante.user']);
    }

    /** Llama a la acción de Application y convierte sus errores en notificación. */
    private static function intentar(Closure $accion, string $exito): void
    {
        try {
            $accion();
        } catch (DatosInvalidos $e) {
            Notification::make()->danger()->title(array_values($e->errores)[0] ?? $e->getMessage())->send();

            return;
        } catch (PrecondicionIncumplida $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        Notification::make()->success()->title($exito)->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('archivado_at', 'desc')
            ->filters([
                TernaryFilter::make('archivado')->label('Estado')->placeholder('Todos')
                    ->trueLabel('Archivados')->falseLabel('Activos')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('escuelas.archivado_at'),
                        false: fn (Builder $query) => $query->whereNull('escuelas.archivado_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->columns([
                TextColumn::make('nombre_aprobado')->label('Escuela')->placeholder('Sin nombre aprobado')
                    ->description(fn (Escuela $r) => $r->plantel
                        ? "{$r->plantel->calle} {$r->plantel->numero_ext}, {$r->plantel->colonia}, {$r->plantel->municipio}" : null),
                TextColumn::make('solicitante.user.name')->label('Solicitante'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (Escuela $r) => $r->archivado_at ? 'Archivado' : 'Activo')
                    ->color(fn (string $state) => $state === 'Archivado' ? 'gray' : 'success'),
                TextColumn::make('archivado_at')->label('Archivado')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('archivado_por_nombre')->label('Archivado por'),
                TextColumn::make('archivado_motivo')->label('Motivo')->wrap(),
            ])
            ->recordActions([
                Action::make('archivar')->label('Archivar')->color('warning')
                    ->visible(fn (Escuela $r) => $r->archivado_at === null)
                    ->schema([Textarea::make('motivo')->label('Motivo')->required()->helperText(UsuarioResource::AVISO_MOTIVO)])
                    ->action(fn (Escuela $r, array $data) => self::intentar(
                        fn () => app(ArchivarTramite::class)->ejecutar($r->id, (int) auth()->id(), $data['motivo']),
                        'Trámite archivado.')),
                Action::make('restaurar')->label('Restaurar')->requiresConfirmation()
                    ->visible(fn (Escuela $r) => $r->archivado_at !== null)
                    ->action(fn (Escuela $r) => self::intentar(
                        fn () => app(RestaurarTramite::class)->ejecutar((int) auth()->id(), $r->id),
                        'Trámite restaurado.')),
                Action::make('purgar')->label('Eliminar')->color('danger')
                    ->visible(fn (Escuela $r) => $r->archivado_at !== null)
                    ->modalDescription('Borrado definitivo e irreversible: se eliminan el trámite, sus documentos y reportes, y los datos personales que contienen. En la bitácora de auditoría se conservan los identificadores y el motivo.')
                    ->schema([
                        Textarea::make('motivo')->label('Motivo')->required()->helperText(UsuarioResource::AVISO_MOTIVO),
                        TextInput::make('confirmacion')->label('Escribe ELIMINAR para confirmar')->required(),
                    ])
                    ->action(fn (Escuela $r, array $data) => self::intentar(
                        fn () => app(PurgarTramite::class)->ejecutar((int) auth()->id(), $r->id, $data['motivo'], $data['confirmacion']),
                        'Trámite eliminado.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTramitesArchivados::route('/')];
    }
}
