<?php

namespace Tests\Unit\Support\Patients;

use App\Models\Patient;
use App\Support\Patients\PatientClinicalHistoryNumber;
use InvalidArgumentException;
use Tests\TestCase;

class PatientClinicalHistoryNumberTest extends TestCase
{
    /**
     * @dataProvider mappedDocuments
     */
    public function test_it_generates_the_confirmed_string_format(string $type, string $number, string $expected): void
    {
        $this->assertSame($expected, (new PatientClinicalHistoryNumber())->generate($type, $number));
    }

    public function test_it_trims_only_accidental_edge_spaces(): void
    {
        $this->assertSame(
            '03-AB 12-X',
            (new PatientClinicalHistoryNumber())->generate(' PASAPORTE ', ' AB 12-X ')
        );
    }

    public function test_ruc_and_undocumented_patients_have_no_invented_number(): void
    {
        $generator = new PatientClinicalHistoryNumber();

        $this->assertNull($generator->generate('RUC', '20123456789'));
        $this->assertNull($generator->generate('SIN DOCUMENTOS', ''));
    }

    public function test_a_supported_type_requires_a_document_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PatientClinicalHistoryNumber())->generate('DNI', '   ');
    }

    public function test_it_never_changes_an_existing_patient_or_backfills_a_null_one(): void
    {
        $generator = new PatientClinicalHistoryNumber();
        $withHce = new Patient([
            'historia_clinica' => '9000',
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
        ]);
        $withHce->exists = true;
        $withoutHce = new Patient([
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378486',
        ]);
        $withoutHce->exists = true;

        $generator->assignToNewPatient($withHce);
        $generator->assignToNewPatient($withoutHce);

        $this->assertSame('9000', $withHce->historia_clinica);
        $this->assertNull($withoutHce->historia_clinica);
    }

    public static function mappedDocuments(): array
    {
        return [
            'DNI' => ['DNI', '73378485', '01-73378485'],
            'CE' => ['CARNET EXTRANJERIA', 'CE-123', '02-CE-123'],
            'passport' => ['PASAPORTE', 'P-123', '03-P-123'],
            'PTP' => ['PTP', 'PTP-123', '04-PTP-123'],
            'TAM' => ['TAM', 'TAM-123', '05-TAM-123'],
            'safe conduct' => ['SALVOCONDUCTO', 'S-123', '06-S-123'],
        ];
    }
}
