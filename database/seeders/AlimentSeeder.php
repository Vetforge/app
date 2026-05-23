<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Aliment;
use Illuminate\Database\Seeder;

class AlimentSeeder extends Seeder
{
    public function run(): void
    {
        if (Aliment::where('user_id', null)->exists()) {
            $this->command->info('AlimentSeeder: aliments système déjà présents, skip.');

            return;
        }

        $csvDir = __DIR__.'/csv';

        $this->importClasseur1($csvDir.'/Classeur1.csv');
        $this->importClasseur2($csvDir.'/Classeur2.csv');
        $this->importClasseur4($csvDir.'/Classeur4.csv');

        $this->command->info('AlimentSeeder: import terminé.');
    }

    /** Concentrés – colonnes 2007 */
    private function importClasseur1(string $path): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) < 5 || trim($row[1]) === '') {
                continue;
            }

            Aliment::create([
                'code_inra' => trim($row[1]),
                'type' => trim($row[2]),
                'libelle0' => trim($row[3]),
                'libelle1' => trim($row[4]),
                'ms' => $this->f($row[5]),
                'ufl' => $this->f($row[6]),
                'ufv' => $this->f($row[7]),
                'pdia' => $this->f($row[8]),
                'pdi' => $this->f($row[9]),
                'bpr' => $this->f($row[10]),
                'lys_di' => $this->f($row[74] ?? $row[11]),
                'met_di' => $this->f($row[79] ?? $row[12]),
                'his_di' => $this->f($row[75] ?? $row[13]),
                'b_vec' => $this->f($row[14]),
                'mo' => $this->f($row[15]),
                'd_mo' => $this->f($row[16]),
                'mat' => $this->f($row[17]),
                'd_ma' => $this->f($row[18]),
                'cb' => $this->f($row[19]),
                'ndf' => $this->f($row[20]),
                'd_ndf' => $this->f($row[21]),
                'adf' => $this->f($row[22]),
                'adl' => $this->f($row[23]),
                'amidon' => $this->f($row[24]),
                'ag' => $this->f($row[25]),
                'ee' => $this->f($row[26]),
                'p' => $this->f($row[27]),
                'pabs' => $this->f($row[28]),
                'ca' => $this->f($row[29]),
                'caabs' => $this->f($row[30]),
                'mg' => $this->f($row[31]),
                'be' => $this->f($row[32]),
                'eb' => $this->f($row[33]),
                'd_e' => $this->f($row[34]),
                'em' => $this->f($row[35]),
                'dt_n' => $this->f($row[36]),
                'dt6_n' => $this->f($row[37]),
                'dr_n' => $this->f($row[38]),
                'dt_ami' => $this->f($row[39]),
                'dt6_ami' => $this->f($row[40]),
                'dt_ms' => $this->f($row[41]),
                'dt6_ms' => $this->f($row[42]),
                's' => $this->f($row[43]),
                'na' => $this->f($row[44]),
                'k' => $this->f($row[45]),
                'cl' => $this->f($row[46]),
                'baca' => $this->f($row[47]),
                'cu' => $this->f($row[48]),
                'zn' => $this->f($row[49]),
                'mn' => $this->f($row[50]),
                'co' => $this->f($row[51]),
                'se' => $this->f($row[52]),
                'i' => $this->f($row[53]),
                'vit_a' => $this->f($row[54]),
                'vit_d' => $this->f($row[55]),
                'vit_e' => $this->f($row[56]),
                'lys_bp' => $this->f($row[57]),
                'his_bp' => $this->f($row[58]),
                'arg_bp' => $this->f($row[59]),
                'thr_bp' => $this->f($row[60]),
                'val_bp' => $this->f($row[61]),
                'met_bp' => $this->f($row[62]),
                'ile_bp' => $this->f($row[63]),
                'leu_bp' => $this->f($row[64]),
                'phe_bp' => $this->f($row[65]),
                'asp_bp' => $this->f($row[66]),
                'ser_bp' => $this->f($row[67]),
                'glu_bp' => $this->f($row[68]),
                'pro_bp' => $this->f($row[69]),
                'gly_bp' => $this->f($row[70]),
                'ala_bp' => $this->f($row[71]),
                'tyr_bp' => $this->f($row[72]),
                'cys_trp_bp' => $this->f($row[73]),
                'arg_di' => $this->f($row[76]),
                'thr_di' => $this->f($row[77]),
                'val_di' => $this->f($row[78]),
                'ile_di' => $this->f($row[80]),
                'leu_di' => $this->f($row[81]),
                'phe_di' => $this->f($row[82]),
                'asp_di' => $this->f($row[83]),
                'ser_di' => $this->f($row[84]),
                'glu_di' => $this->f($row[85]),
                'pro_di' => $this->f($row[86]),
                'gly_di' => $this->f($row[87]),
                'ala_di' => $this->f($row[88]),
                'tyr_di' => $this->f($row[89]),
                'c6_10' => $this->f($row[90]),
                'c12_0' => $this->f($row[91]),
                'c14_0' => $this->f($row[92]),
                'c16_0' => $this->f($row[93]),
                'c16_1' => $this->f($row[94]),
                'c18_0' => $this->f($row[95]),
                'c18_1' => $this->f($row[96]),
                'c18_2' => $this->f($row[97]),
                'c18_3' => $this->f($row[98]),
                'c20_0' => $this->f($row[99]),
                'c20_1' => $this->f($row[100]),
                'c22_0' => $this->f($row[101]),
                'c22_1' => $this->f($row[102]),
                'c24_0' => $this->f($row[103]),
            ]);
        }

        fclose($handle);
    }

    /** Fourrages – colonnes 2018 */
    private function importClasseur2(string $path): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) < 5 || trim($row[1]) === '') {
                continue;
            }

            Aliment::create([
                'code_inra' => trim($row[1]),
                'type' => trim($row[2]),
                'libelle0' => trim($row[3]),
                'libelle1' => trim($row[4]),
                'ms' => $this->f($row[5]),
                'ufl' => $this->f($row[6]),
                'ufv' => $this->f($row[7]),
                'pdia' => $this->f($row[8]),
                'pdi' => $this->f($row[9]),
                'bpr' => $this->f($row[10]),
                'lys_di' => $this->f($row[73] ?? $row[11]),
                'met_di' => $this->f($row[78] ?? $row[12]),
                'his_di' => $this->f($row[74] ?? $row[13]),
                'niref' => $this->f($row[14]),
                'uem' => $this->f($row[15]),
                'uel' => $this->f($row[16]),
                'ueb' => $this->f($row[17]),
                'mo' => $this->f($row[18]),
                'd_mo' => $this->f($row[19]),
                'mat' => $this->f($row[20]),
                'd_ma' => $this->f($row[21]),
                'cb' => $this->f($row[22]),
                'd_cb' => $this->f($row[23]),
                'ndf' => $this->f($row[24]),
                'd_ndf' => $this->f($row[25]),
                'adf' => $this->f($row[26]),
                'd_adf' => $this->f($row[27]),
                'ag' => $this->f($row[28]),
                'ee' => $this->f($row[29]),
                'p' => $this->f($row[30]),
                'pabs' => $this->f($row[31]),
                'ca' => $this->f($row[32]),
                'caabs' => $this->f($row[33]),
                'mg' => $this->f($row[34]),
                'be' => $this->f($row[35]),
                'eb' => $this->f($row[36]),
                'd_e' => $this->f($row[37]),
                'em' => $this->f($row[38]),
                'dt_n' => $this->f($row[39]),
                'dt6_n' => $this->f($row[40]),
                'dr_n' => $this->f($row[41]),
                's' => $this->f($row[42]),
                'na' => $this->f($row[43]),
                'k' => $this->f($row[44]),
                'cl' => $this->f($row[45]),
                'baca' => $this->f($row[46]),
                'cu' => $this->f($row[47]),
                'zn' => $this->f($row[48]),
                'mn' => $this->f($row[49]),
                'co' => $this->f($row[50]),
                'se' => $this->f($row[51]),
                'i' => $this->f($row[52]),
                'vit_a' => $this->f($row[53]),
                'vit_d' => $this->f($row[54]),
                'vit_e' => $this->f($row[55]),
                'lys_bp' => $this->f($row[56]),
                'his_bp' => $this->f($row[57]),
                'arg_bp' => $this->f($row[58]),
                'thr_bp' => $this->f($row[59]),
                'val_bp' => $this->f($row[60]),
                'met_bp' => $this->f($row[61]),
                'ile_bp' => $this->f($row[62]),
                'leu_bp' => $this->f($row[63]),
                'phe_bp' => $this->f($row[64]),
                'asp_bp' => $this->f($row[65]),
                'ser_bp' => $this->f($row[66]),
                'glu_bp' => $this->f($row[67]),
                'pro_bp' => $this->f($row[68]),
                'gly_bp' => $this->f($row[69]),
                'ala_bp' => $this->f($row[70]),
                'tyr_bp' => $this->f($row[71]),
                'cys_trp_bp' => $this->f($row[72]),
                'arg_di' => $this->f($row[75]),
                'thr_di' => $this->f($row[76]),
                'val_di' => $this->f($row[77]),
                'ile_di' => $this->f($row[79]),
                'leu_di' => $this->f($row[80]),
                'phe_di' => $this->f($row[81]),
                'asp_di' => $this->f($row[82]),
                'ser_di' => $this->f($row[83]),
                'glu_di' => $this->f($row[84]),
                'pro_di' => $this->f($row[85]),
                'gly_di' => $this->f($row[86]),
                'ala_di' => $this->f($row[87]),
                'tyr_di' => $this->f($row[88]),
                'c14_0' => $this->f($row[89]),
                'c16_0' => $this->f($row[90]),
                'c16_1' => $this->f($row[91]),
                'c18_0' => $this->f($row[92]),
                'c18_1' => $this->f($row[93]),
                'c18_2' => $this->f($row[94]),
                'c18_3' => $this->f($row[95]),
            ]);
        }

        fclose($handle);
    }

    /** Complète PDIN / PDIE 2007 sur les aliments déjà importés */
    private function importClasseur4(string $path): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $codeInra = trim($row[0]);
            if ($codeInra === '' || ! isset($row[2])) {
                continue;
            }

            Aliment::where('code_inra', $codeInra)
                ->whereNull('user_id')
                ->update([
                    'pdin2007' => $this->f($row[1]),
                    'pdie2007' => $this->f($row[2]),
                ]);
        }

        fclose($handle);
    }

    private function f(mixed $value): ?float
    {
        $str = trim((string) $value);
        if ($str === '' || $str === 'NULL') {
            return null;
        }
        $str = str_replace(',', '.', $str);

        return (float) $str ?: null;
    }
}
