<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerfilesProfesionalesSeeder extends Seeder
{
    /**
     * Perfiles profesionales aceptados por cargo — COMPENDIO_MAESTRO §5.1
     * (Profesiogramas Inicial/Preescolar/Primaria) y Director Técnico
     * Secundaria §5.2. El catálogo de carreras por asignatura de docentes
     * en Secundaria queda excluido (vive en el PDF fuente, no reproducido
     * en el compendio) — ver §7 pendientes.
     */
    public function run(): void
    {
        $cargoId = DB::table('cargos_puestos')
            ->join('niveles_educativos', 'cargos_puestos.nivel_educativo_id', '=', 'niveles_educativos.id')
            ->select('cargos_puestos.id', 'niveles_educativos.clave as nivel', 'cargos_puestos.nombre')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->nivel}:{$row->nombre}" => $row->id]);

        $tituloCedula = 'titulo_cedula';
        $certificado = 'certificado';

        $rows = [];

        $add = function (string $cargoKey, array $carreras, string $documento) use (&$rows, $cargoId) {
            foreach ($carreras as $carrera) {
                $rows[] = [
                    'cargo_puesto_id' => $cargoId[$cargoKey],
                    'asignatura_id' => null,
                    'carrera_aceptada' => $carrera,
                    'documento_acreditacion' => $documento,
                ];
            }
        };

        // Inicial
        $add('inicial:Director Técnico', ['Lic. en Educación Preescolar', 'Puericultura y Educación Infantil', 'Intervención Educativa', 'Trabajo Social', 'Psicología', 'Médico'], $tituloCedula);
        $add('inicial:Responsable de Sala', ['TSU o Lic. en Puericultura', 'Educación Preescolar', 'Enfermería'], $tituloCedula);
        $add('inicial:Asistente Educativo', ['Puericultista', 'Técnico en Enfermería', 'Asistente Educativo'], $certificado);
        $add('inicial:Responsable de Filtro y Fomento a la Salud', ['Médico General o Pediatra', 'Enfermera Pediatra'], $tituloCedula);

        // Preescolar
        $add('preescolar:Director Técnico', ['Normalista', 'Lic. en Educación', 'Lic. en Preescolar', 'Lic. en Inicial', 'Pedagogía', 'Intervención Educativa'], $tituloCedula);
        $add('preescolar:Docente Titular de Grupo', ['Normalista', 'Lic. en Educación', 'Lic. en Preescolar', 'Lic. en Primaria', 'Lic. en Especial', 'TSU en Puericultura', 'Pedagogía', 'Psicopedagogía', 'Psicología Educativa', 'Intervención Educativa', 'Ciencias de la Educación'], $tituloCedula);
        $add('preescolar:Asistente de Grupo', ['Asistente Educativo'], $certificado);
        $add('preescolar:Asistente de Grupo', ['TSU en Puericultura y Educación Infantil'], $tituloCedula);
        $add('preescolar:Docente de Educación Física', ['Lic. en Educación Física', 'Ciencias del Deporte', 'Salud Física y Deporte', 'Entrenamiento Deportivo'], $tituloCedula);
        $add('preescolar:Docente de Educación Física', ['Entrenador Deportivo certificado por CONADE'], $certificado);
        $add('preescolar:Docente de Inglés', ['Educación/Idiomas/Lenguas Modernas/Letras con especialidad en Inglés'], $tituloCedula);
        $add('preescolar:Docente de Inglés', ['Certificación TKT', 'Certificación CENNI', 'Certificación KET', 'Certificación PET', 'Certificación FCE', 'Certificación CAE', 'Certificación CPE', 'Certificación MCER'], $certificado);

        // Primaria
        $add('primaria:Director Técnico', ['Normalista', 'Lic. en Educación', 'Lic. en Educación Básica', 'Lic. en Primaria', 'Pedagogía', 'Intervención Educativa'], $tituloCedula);
        $add('primaria:Docente Titular de Grupo', ['Normalista', 'Lic. en Educación', 'Lic. en Primaria', 'Lic. en Preescolar', 'Lic. en Especial', 'Psicología Educativa', 'Pedagogía', 'Ciencias de la Educación', 'Intervención Educativa'], $tituloCedula);
        $add('primaria:Docente de Educación Física', ['Lic. en Educación Física', 'Ciencias del Deporte', 'Salud Física y Deporte', 'Entrenamiento Deportivo'], $tituloCedula);
        $add('primaria:Docente de Educación Física', ['Entrenador Deportivo certificado por CONADE'], $certificado);
        $add('primaria:Docente de Inglés', ['Educación/Idiomas/Lenguas Modernas/Letras con especialidad en Inglés'], $tituloCedula);
        $add('primaria:Docente de Inglés', ['Certificación TKT', 'Certificación CENNI', 'Certificación KET', 'Certificación PET', 'Certificación FCE', 'Certificación CAE', 'Certificación CPE', 'Certificación MCER'], $certificado);
        $add('primaria:Docente de Computación', ['Informática', 'Sistemas Computacionales Administrativos', 'Tecnologías de la Información', 'Computación'], $tituloCedula);

        // Secundaria
        $add('secundaria:Director Técnico', ['Educación Básica', 'Educación Media', 'Intervención Educativa', 'Pedagogía', 'Psicología Educativa', 'Ciencias de la Educación', 'Maestría en Educación'], $tituloCedula);

        // Delete existing rows for these cargos (idempotent via delete-then-insert)
        // Scope only to cargo ids this seeder actually inserts for — derived from $rows,
        // not from the full $cargoId lookup map which includes Secundaria
        DB::table('perfiles_profesionales')
            ->whereIn('cargo_puesto_id', array_unique(array_column($rows, 'cargo_puesto_id')))
            ->delete();

        // Insert all rows
        DB::table('perfiles_profesionales')->insert($rows);
    }
}
