<?php

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Mechanical check for ADR-001 (docs/decisions/ADR-001-frontera-de-capas.md):
 * app/Domain must stay framework-agnostic. Analyzed by PHPStan/PHPat, not
 * run by PHPUnit — see phpstan.neon's `paths`. Deliberately ONE rule; the
 * full layer-boundary model (Domain vs. Infrastructure vs. Application) is
 * out of scope here, see docs/reports/2026-09-08-ci-github-actions.md.
 */
final class DomainBoundaryTest
{
    public function test_domain_does_not_depend_on_illuminate(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Domain'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Illuminate'))
            ->because('ADR-001: app/Domain must be framework-agnostic — Illuminate/Laravel code belongs in app/Infrastructure or app/Application.');
    }
}
