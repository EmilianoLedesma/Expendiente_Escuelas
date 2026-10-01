# SDD ledger — plan: docs/superpowers/plans/2026-09-29-ws5b-documentos-por-nivel.md
Base: cd91ffe (master). Baseline 546 passed.
Task 1: minor (deferred): docblock in DocumentosCompletos/seeder names DocumentosNivelCompletos (lands T2) + long line; seeder test pins [dictamen_uso_suelo] exactly; Paso2Documentos render() direct TipoDocumento query; $titulos/$nombres overlap.
Task 1: complete (commits cd91ffe..e005621, review clean; 3 FKs to tipos_documentos verified in DDL)
Task 2: minor (deferred): DocumentoEscuelaNivelTest:42 tautological assert; aplica_persona filter in DocumentosNivelCompletos untested (all seeded nivel docs are ambas); DocumentosCapturados paraEscuelaNivel last-row-wins if dup (T4 must upsert; DDL unique unchecked); app() fallback in ValidarVigenciaDocumentos brief-mandated.
Task 2: complete (commits e005621..3a6b282, review clean, 569 passed)

Task 3: complete — efe7ec9 (feat) + d28557a (fix round 1: lockForUpdate inside tx, firstOrFail on formato tipo, 4 pinning tests). Suite 587/587, Pint+PHPStan clean. Reviewer sonnet: approved; re-review approved. Opus implementer. Deferred minors: no concurrency test for the lock (needs 2 connections); invalid-input test also rejected by DB CHECK; EstadoPaso24 findOrFail per call (ResumenTramite will call per nivel, T7 watch); eliminar failure only logs, orphans file (same as RegistrarDocumento).

Task 4: complete — commits 780ba7e (feat), c005a85 (fix round 1). Head c005a85. Suite 607/607, Pint + PHPStan clean. Opus impl, sonnet review + scoped re-review: all findings addressed.
Beyond-brief (accepted): EscuelaNivel::lockForUpdate() in RegistrarDocumento nivel path (no UNIQUE on documentos_escuela_nivel); Formato gate re-checked under lock against turno/tipo_alumnado seen at verificarNivel.
Deferred minors (final review to triage): $rutaAnterior read outside transaction (orphan file under race, same as 2.2); assertDirectoryEmpty('escuela_nivel') may pass vacuously; no fecha_vigencia test on nivel path with fechaEmision; monto regex `$` matches before trailing newline (use \z); redundant in_array(null) check at RegistrarDocumento.php:74; I1 both-emptied and 0.004 monto cases not shown red individually; no true two-connection concurrency test.

Task 5: complete — commits 1a37c2c (feat), 1266394 (fix round 1). Head 1266394. Suite 620/620, Pint + PHPStan clean. Sonnet impl, sonnet review + scoped re-review: all findings addressed.
Deferred minors: EstadoPaso24::datosNivelCapturados re-queries the nivel the route binding already loaded (negligible). Implementer note: Bash tool has no php, tests run via PowerShell.

Task 6: complete — commits 53be81a (feat), a34e647 (fix round 1). Head a34e647. Suite 652/652, Pint + PHPStan clean. Sonnet impl, sonnet review + scoped re-review: all findings addressed.
Fix round: #[Locked] on $escuelaNivel (tamper via set('escuelaNivel.id') already rejected by Livewire 3, kept as regression guard); removed beyond-brief refresh() in mount; dead DatosInvalidos catch in guardarDatos deleted; toggleReemplazar restricted to clavesAplicables.
Deferred / for final review: render() direct TipoDocumento::whereIn query (Task 1 minor pattern, repeated); sibling components (Paso3/DatosInmueble, InfraestructuraNivel, MobiliarioNivel, Paso2Documentos) hold unlocked public model properties, only Paso2Responsable uses #[Locked] — Livewire 3 blocks direct model-property sets, so likely low risk, triage in final review; some tests mount with a stale model (fine as nothing depends on turno/tipo there).

Task 7: complete — impl 7bde415 (sonnet), reviewer sonnet: Approved, no fixes. Suite 660/660, Pint + PHPStan clean.
Deferred minors (Task 7): EstadoPaso24::etapaFaltante(int) findOrFail per nivel from ResumenTramite (N+1, small N; Task 8 may add EscuelaNivel overload if touching EstadoPaso24); hub test substr($bloque,0,1500) magic window; PASO3 single-line docblock mixes prose + @var.
Task 8 note: block first Paso 3 sub-step on 2.4 via EstadoPaso3::puedeAcceder, use ResumenTramite::RUTA_DOCUMENTOS_NIVEL.

Task 8: complete — impl c1bc210 (sonnet, single green commit; red run 85 = 83+2 recorded), reviewer sonnet: Approved, no fixes. Suite 670/670, Pint + PHPStan clean.
Deferred minors (Task 8): EstadoPaso24::etapaFaltante findOrFail twice per Paso 3 GET (CompuertaPaso3 + puedeAcceder), repeated in ResumenTramite hub; CompuertaPaso3 docblock long line; Paso2ResponsableTest bare firstOrFail statement (plan-mandated); Paso3DatosInmuebleTest 2 "possibly red" tests covered by helper.
ALL 8 TASKS COMPLETE. Next: final opus whole-branch review (base = master merge-base, head c1bc210), one fix wave, scoped re-review, triage deferred minors (incl. repo-wide unlocked-model pattern, EstadoPaso24 findOrFail overload).

## Final whole-branch review (opus, cd91ffe..c1bc210): Ready to merge — Yes
No Critical/Important. Minor 1 (wrong hub motive when 2.4 blocks Paso 3) fixed in fb8cbba (controller diff check + full suite 671/671). Fix re-review waived: 1-line arm + red/green test, verified by controller.
Deferred (accepted/deferred, go to report): hub status 'pendiente' vs 'en_curso' when only recibo/acervo uploaded; query volume (~150-200 on hub, EscuelaNivel overload/memoisation in DocumentosNivelCompletos::clavesAplicables); $rutaAnterior read outside tx (fix with 2.2 together); repo-wide #[Locked] sweep of sibling Livewire components; \z monto regex; redundant in_array(null); $titulos/$nombres overlap; untested aplica_persona filter; fecha_vigencia nivel test; magic substr window in hub test.
Head: fb8cbba. Remaining: Task 9 close-out (controller), merge (needs owner OK).
