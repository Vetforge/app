<?php

namespace App\Services;

use App\Models\Aliment;

class AlimentImporter
{
    /**
     * Importer des aliments depuis un fichier CSV.
     *
     * @return array{created: int, updated: int, errors: int}
     */
    public static function import(string $filePath): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;

        if (! file_exists($filePath)) {
            return compact('created', 'updated', 'errors');
        }

        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle, 0, ';', '"', '\\');

        if (! $headers) {
            fclose($handle);

            return compact('created', 'updated', 'errors');
        }

        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            try {
                $data = array_combine($headers, $row);
                $codeInra = trim($data['code_inra'] ?? '');

                if (empty($codeInra)) {
                    $errors++;

                    continue;
                }

                $mapped = self::mapRow($data);

                $aliment = Aliment::query()->where('code_inra', $codeInra)->first();

                if ($aliment) {
                    $aliment->update($mapped);
                    $updated++;
                } else {
                    Aliment::create($mapped);
                    $created++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }

        fclose($handle);

        return compact('created', 'updated', 'errors');
    }

    /** @param  array<string, string>  $data */
    private static function mapRow(array $data): array
    {
        $numeric = fn ($v) => is_numeric($v) ? (float) $v : null;

        return array_filter([
            'code_inra' => trim($data['code_inra'] ?? '') ?: null,
            'type' => trim($data['type'] ?? '') ?: null,
            'libelle0' => trim($data['libelle0'] ?? '') ?: null,
            'libelle1' => trim($data['libelle1'] ?? '') ?: null,
            'libelle2' => trim($data['libelle2'] ?? '') ?: null,
            'libelle3' => trim($data['libelle3'] ?? '') ?: null,
            'libelle4' => trim($data['libelle4'] ?? '') ?: null,
            'prix' => $numeric($data['prix'] ?? null),
            'ms' => $numeric($data['ms'] ?? null),
            'ufl' => $numeric($data['ufl'] ?? null),
            'ufv' => $numeric($data['ufv'] ?? null),
            'pdia' => $numeric($data['pdia'] ?? null),
            'pdi' => $numeric($data['pdi'] ?? null),
            'mat' => $numeric($data['mat'] ?? null),
            'cb' => $numeric($data['cb'] ?? null),
            'ndf' => $numeric($data['ndf'] ?? null),
            'adf' => $numeric($data['adf'] ?? null),
            'ca' => $numeric($data['ca'] ?? null),
            'p' => $numeric($data['p'] ?? null),
            'mg' => $numeric($data['mg'] ?? null),
            'na' => $numeric($data['na'] ?? null),
            'k' => $numeric($data['k'] ?? null),
            'ufl2007' => $numeric($data['ufl2007'] ?? null),
            'ufv2007' => $numeric($data['ufv2007'] ?? null),
            'pdia2007' => $numeric($data['pdia2007'] ?? null),
            'pdie2007' => $numeric($data['pdie2007'] ?? null),
            'pdin2007' => $numeric($data['pdin2007'] ?? null),
        ], fn ($v) => $v !== null);
    }
}
