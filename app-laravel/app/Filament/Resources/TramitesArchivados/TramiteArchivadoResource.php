<?php

namespace App\Filament\Resources\TramitesArchivados;

use App\Application\Archivo\PurgarTramite;
use App\Application\Archivo\RestaurarTramite;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Filament\Resources\TramitesArchivados\Pages\ListTramitesArchivados;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TramiteArchivadoResource extends Resource
{
    protected static ?string $model = Escuela::class;

    protected static ?string $modelLabel = 'trámite archivado';

    protected static ?string $pluralModelLabel = 'trámites archivados';

    protected static ?string $slug = 'tramites-archivados';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(EscuelaNoArchivada::class)
            ->whereNotNull('escuelas.archivado_at')
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
            ->columns([
                TextColumn::make('nombre_aprobado')->label('Escuela')->placeholder('Sin nombre aprobado')
                    ->description(fn (Escuela $r) => $r->plantel
                        ? "{$r->plantel->calle} {$r->plantel->numero_ext}, {$r->plantel->colonia}, {$r->plantel->municipio}" : null),
                TextColumn::make('solicitante.user.name')->label('Solicitante'),
                TextColumn::make('archivado_at')->label('Archivado')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('archivado_por_nombre')->label('Archivado por'),
                TextColumn::make('archivado_motivo')->label('Motivo')->wrap(),
            ])
            ->recordActions([
                Action::make('restaurar')->label('Restaurar')->requiresConfirmation()
                    ->action(fn (Escuela $r) => self::intentar(
                        fn () => app(RestaurarTramite::class)->ejecutar((int) auth()->id(), $r->id),
                        'Trámite restaurado.')),
                Action::make('purgar')->label('Purgar')->color('danger')
                    ->modalDescription('Borrado definitivo e irreversible: se eliminan el trámite, sus documentos y reportes, y los datos personales que contienen. Solo queda el registro de auditoría con identificadores.')
                    ->schema([
                        Textarea::make('motivo')->label('Motivo')->required(),
                        TextInput::make('confirmacion')->label('Escribe PURGAR para confirmar')->required(),
                    ])
                    ->action(fn (Escuela $r, array $data) => self::intentar(
                        fn () => app(PurgarTramite::class)->ejecutar((int) auth()->id(), $r->id, $data['motivo'], $data['confirmacion']),
                        'Trámite purgado.')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTramitesArchivados::route('/')];
    }
}
