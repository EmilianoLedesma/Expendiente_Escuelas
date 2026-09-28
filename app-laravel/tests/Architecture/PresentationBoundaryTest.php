<?php

namespace Tests\Architecture;

use Illuminate\Support\Facades\DB;
use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Mechanical check for ADR-006 (docs/decisions/ADR-006-frontera-lecturas.md):
 * flow/permission/completeness reads live in app/Application, not in
 * presentation. Analyzed by PHPStan/PHPat, not run by PHPUnit — see
 * phpstan.neon's `paths`, same mechanism as DomainBoundaryTest.php.
 *
 * Deliberately narrow: this does NOT ban Eloquent model reads in Livewire/
 * View — ADR-006 keeps "purely display reads may stay in components"
 * (e.g. InfraestructuraNivel::espaciosCapturados() via InstalacionEspacio).
 * It bans two specific things: Application depending back on presentation,
 * and presentation reaching for the raw DB facade (which has no model to
 * express ownership/applicability rules against, unlike Eloquent).
 */
final class PresentationBoundaryTest
{
    public function test_application_does_not_depend_on_livewire(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('App\Livewire'))
            ->because('ADR-006: app/Application is called by presentation, never the other way around.');
    }

    public function test_application_does_not_depend_on_http(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('App\Http'))
            ->because('ADR-006: app/Application is called by controllers, never the other way around.');
    }

    public function test_application_does_not_depend_on_view_components(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('App\View'))
            ->because('ADR-006: app/Application is called by view components, never the other way around.');
    }

    public function test_livewire_does_not_use_raw_db_facade(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Livewire'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname(DB::class))
            ->because('ADR-006: a raw DB::table() read in a Livewire component has no model to carry an ownership/applicability rule — move it to app/Application.');
    }

    public function test_view_components_do_not_use_raw_db_facade(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\View'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname(DB::class))
            ->because('ADR-006: a raw DB::table() read in a view component has no model to carry an ownership/applicability rule — move it to app/Application.');
    }
}
