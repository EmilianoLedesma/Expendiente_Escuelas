<?php

namespace App\Filament\Resources\Usuarios;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Archivo\PurgarCuenta;
use App\Application\Archivo\RestaurarCuenta;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\CambiarRolSedeq;
use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\Usuarios\Pages\CrearUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsuarioResource extends Resource
{
    public const AVISO_MOTIVO = 'No escribas datos personales (nombres, correos, CURP) en el motivo: el motivo se conserva en la bitácora.';

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $slug = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nombre')->label('Nombre')->required()->maxLength(255),
            TextInput::make('correo')->label('Correo')->email()->required()->maxLength(255),
            Select::make('rol')->label('Rol')->required()
                ->options([RolesSedeq::ADMIN => 'admin', RolesSedeq::SUPERVISOR => 'supervisor']),
        ]);
    }

    /** Subconsulta: plantel de menor id entre las escuelas del solicitante, archivadas incluidas. */
    private static function plantelPrincipal(): Builder
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)
            ->join('solicitantes', 'solicitantes.id', '=', 'escuelas.solicitante_id')
            ->whereColumn('solicitantes.user_id', 'users.id')
            ->selectRaw('min(escuelas.plantel_id)');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('users.*')
            ->addSelect(['plantel_principal_id' => self::plantelPrincipal()])
            ->with([
                'roles',
                'solicitante.escuelas' => fn ($query) => $query->withoutGlobalScope(EscuelaNoArchivada::class),
                'solicitante.escuelas.plantel',
            ]);
    }

    private static function etiquetaPlantel(?Plantel $p): string
    {
        return $p ? "{$p->calle} {$p->numero_ext}, {$p->colonia}, {$p->municipio}" : 'Sin plantel';
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
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('roles.name')->label('Rol')->badge(),
                TextColumn::make('planteles')->label('Planteles')
                    ->state(fn (User $r) => $r->solicitante?->escuelas
                        ->map(fn (Escuela $e) => self::etiquetaPlantel($e->plantel))->unique()->values()->all() ?: null)
                    ->listWithLineBreaks(),
                TextColumn::make('estado')->label('Estado')
                    ->state(fn (User $r) => $r->archivado_at ? 'Archivada' : 'Activa')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Activa' ? 'success' : 'gray'),
            ])
            ->groups([
                Group::make('plantel_principal_id')
                    ->label('Plantel')
                    // Usa el plantel ya cargado (with solicitante.escuelas.plantel): cero consultas por registro.
                    ->getTitleFromRecordUsing(fn (User $r) => self::etiquetaPlantel(
                        $r->solicitante?->escuelas->firstWhere('plantel_id', $r->plantel_principal_id)?->plantel))
                    ->getKeyFromRecordUsing(fn (User $r) => (string) ($r->plantel_principal_id ?? ''))
                    ->scopeQueryByKeyUsing(fn (Builder $query, ?string $key) => filled($key)
                        ? $query->whereRaw('('.self::plantelPrincipal()->toSql().') = ?', [...self::plantelPrincipal()->getBindings(), $key])
                        : $query->whereRaw('('.self::plantelPrincipal()->toSql().') is null', self::plantelPrincipal()->getBindings())),
            ])
            ->defaultGroup('plantel_principal_id')
            ->filters([
                SelectFilter::make('estado')->label('Estado')
                    ->options(['activa' => 'Activa', 'archivada' => 'Archivada'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'activa' => $query->whereNull('users.archivado_at'),
                        'archivada' => $query->whereNotNull('users.archivado_at'),
                        default => $query,
                    }),
                SelectFilter::make('rol')->label('Rol')
                    ->options(array_combine(RolesSedeq::TODOS, RolesSedeq::TODOS))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('users.id', User::role($data['value'])->select('users.id')) : $query),
                SelectFilter::make('plantel')->label('Plantel')
                    ->options(fn () => Plantel::orderBy('id')->get()->mapWithKeys(fn (Plantel $p) => [$p->id => self::etiquetaPlantel($p)])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('users.id', Solicitante::query()->select('user_id')->whereIn(
                            'id',
                            Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->where('plantel_id', $data['value'])->select('solicitante_id'),
                        )) : $query),
            ])
            ->recordActions([
                Action::make('cambiar_rol')->label('Cambiar rol')
                    ->visible(fn (User $r) => $r->archivado_at === null && $r->id !== auth()->id())
                    ->schema([
                        Select::make('rol')->label('Rol de SEDEQ')->required()
                            ->options(array_combine(RolesSedeq::TODOS, RolesSedeq::TODOS) + ['ninguno' => 'Sin rol de SEDEQ']),
                    ])
                    ->action(fn (User $r, array $data) => self::intentar(
                        fn () => app(CambiarRolSedeq::class)->ejecutar(
                            (int) auth()->id(), $r->id, $data['rol'] === 'ninguno' ? null : $data['rol']),
                        'Rol actualizado.')),
                Action::make('archivar')->label('Archivar')->color('warning')
                    ->visible(fn (User $r) => $r->archivado_at === null && $r->id !== auth()->id())
                    ->schema([Textarea::make('motivo')->label('Motivo')->required()->helperText(self::AVISO_MOTIVO)])
                    ->action(fn (User $r, array $data) => self::intentar(
                        fn () => app(ArchivarCuenta::class)->ejecutar($r->id, (int) auth()->id(), $data['motivo']),
                        'Cuenta archivada.')),
                Action::make('restaurar')->label('Restaurar')->requiresConfirmation()
                    ->visible(fn (User $r) => $r->archivado_at !== null)
                    ->action(fn (User $r) => self::intentar(
                        fn () => app(RestaurarCuenta::class)->ejecutar((int) auth()->id(), $r->id),
                        'Cuenta restaurada.')),
                Action::make('purgar')->label('Purgar')->color('danger')
                    ->visible(fn (User $r) => $r->archivado_at !== null)
                    ->modalDescription('Borrado definitivo e irreversible: se eliminan la cuenta y sus datos personales. En la bitácora de auditoría se conservan los identificadores y el motivo.')
                    ->schema([
                        Textarea::make('motivo')->label('Motivo')->required()->helperText(self::AVISO_MOTIVO),
                        TextInput::make('confirmacion')->label('Escribe PURGAR para confirmar')->required(),
                    ])
                    ->action(fn (User $r, array $data) => self::intentar(
                        fn () => app(PurgarCuenta::class)->ejecutar((int) auth()->id(), $r->id, $data['motivo'], $data['confirmacion']),
                        'Cuenta purgada.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuarios::route('/'),
            'create' => CrearUsuario::route('/create'),
        ];
    }
}
