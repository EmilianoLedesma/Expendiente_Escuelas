<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso2Documentos;
use App\Models\CertificadoNumeroOficial;
use App\Models\ConstanciaCurp;
use App\Models\ConstanciaSituacionFiscal;
use App\Models\CredencialIne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * ADR-007: the documents the validation engine reads are captured as
 * "datos estructurados + PDF", and the final validation step can send the
 * applicant back to fix one of them even after Paso 2.2 is complete.
 */
class Paso2DocumentosDatosTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 10, 'application/pdf');
    }

    public function test_la_ine_se_sube_con_nombre_y_curp(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', $this->pdf('ine.pdf'))
            ->set('ineForm.nombre', 'Pérez Gómez Juan')
            ->set('ineForm.curp', 'pegj800101hqtrml09')
            ->call('guardarIne')
            ->assertHasNoErrors();

        $ine = CredencialIne::sole();
        $this->assertSame('Pérez Gómez Juan', $ine->nombre);
        $this->assertSame(self::CURP_TITULAR, $ine->curp);
    }

    public function test_la_ine_exige_sus_datos_y_una_curp_con_formato_valido(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', $this->pdf('ine.pdf'))
            ->set('ineForm.curp', 'NO-ES-CURP')
            ->call('guardarIne')
            ->assertHasErrors(['ineForm.nombre', 'ineForm.curp']);

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_la_ine_ya_no_se_acepta_como_documento_simple(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', $this->pdf('ine.pdf'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertForbidden();
    }

    public function test_constancias_y_certificado_se_suben_con_sus_datos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.constancia_curp', $this->pdf('curp.pdf'))
            ->set('constanciaCurpForm.nombre', 'Juan Pérez Gómez')
            ->set('constanciaCurpForm.curp', self::CURP_TITULAR)
            ->call('guardarConstanciaCurp')
            ->set('archivos.constancia_situacion_fiscal', $this->pdf('csf.pdf'))
            ->set('situacionFiscalForm.nombre', 'Juan Pérez Gómez')
            ->set('situacionFiscalForm.rfc', 'pegj800101ab1')
            ->call('guardarSituacionFiscal')
            ->set('archivos.certificado_numero_oficial', $this->pdf('cert.pdf'))
            ->set('numeroOficialForm.calle', 'Av. Juárez')
            ->set('numeroOficialForm.numeroExt', '12')
            ->set('numeroOficialForm.colonia', 'Centro')
            ->set('numeroOficialForm.municipio', 'Querétaro')
            ->set('numeroOficialForm.codigoPostal', '76000')
            ->call('guardarNumeroOficial')
            ->assertHasNoErrors();

        $this->assertSame(self::CURP_TITULAR, ConstanciaCurp::sole()->curp);
        $this->assertSame(self::RFC_TITULAR, ConstanciaSituacionFiscal::sole()->rfc);
        $this->assertSame('76000', CertificadoNumeroOficial::sole()->codigo_postal);
    }

    public function test_el_certificado_exige_un_codigo_postal_de_cinco_digitos_y_rfc_valido(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.certificado_numero_oficial', $this->pdf('cert.pdf'))
            ->set('numeroOficialForm.calle', 'Av. Juárez')
            ->set('numeroOficialForm.colonia', 'Centro')
            ->set('numeroOficialForm.municipio', 'Querétaro')
            ->set('numeroOficialForm.codigoPostal', '760')
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['numeroOficialForm.codigoPostal'])
            ->set('archivos.constancia_situacion_fiscal', $this->pdf('csf.pdf'))
            ->set('situacionFiscalForm.nombre', 'Juan Pérez')
            ->set('situacionFiscalForm.rfc', 'XYZ')
            ->call('guardarSituacionFiscal')
            ->assertHasErrors(['situacionFiscalForm.rfc']);
    }

    public function test_la_pagina_muestra_los_campos_de_los_documentos_con_datos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Constancia de CURP')
            ->assertSee('Constancia de Situación Fiscal')
            ->assertSee('Nombre como aparece en la credencial')
            ->assertSee('RFC')
            ->assertSee('Código postal');
    }

    public function test_corregir_reabre_un_documento_aunque_el_paso_este_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::withQueryParams(['corregir' => 'ine'])
            ->test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertNoRedirect()
            ->assertSet('reemplazando.ine', true)
            ->set('archivos.ine', $this->pdf('ine-nueva.pdf'))
            ->set('ineForm.nombre', 'PEREZ GOMEZ JUAN')
            ->set('ineForm.curp', self::CURP_TITULAR)
            ->call('guardarIne')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.validacion', ['escuela' => $escuela->id]));
    }

    public function test_sin_corregir_un_paso_completo_sigue_redirigiendo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));

        Livewire::withQueryParams(['corregir' => 'no_existe'])
            ->test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }
}
