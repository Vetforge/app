<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreAlimentRequest;
use App\Models\Aliment;
use Illuminate\Support\Facades\Validator;

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
                $validator = Validator::make($mapped, StoreAlimentRequest::sharedRules());
                $validator->after(
                    fn ($validator) => StoreAlimentRequest::appendContractErrors($validator, $mapped)
                );
                if ($validator->fails()) {
                    $errors++;

                    continue;
                }

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
        $textFields = [
            'type', 'famille_botanique', 'procede_technologique', 'libelle0',
            'libelle1', 'libelle2', 'libelle3', 'libelle4', 'usage_aliment',
        ];
        $mapped = ['code_inra' => trim($data['code_inra'] ?? '')];

        foreach (array_keys(StoreAlimentRequest::sharedRules()) as $field) {
            $raw = trim((string) ($data[$field] ?? ''));
            if ($raw === '') {
                continue;
            }

            if (in_array($field, $textFields, true)) {
                $mapped[$field] = $field === 'type' ? Aliment::canonicalType($raw) : $raw;

                continue;
            }

            $normalized = str_replace(',', '.', $raw);
            $mapped[$field] = is_numeric($normalized) ? (float) $normalized : $raw;
        }

        return $mapped;
    }
}
