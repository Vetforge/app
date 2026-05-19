<?php

namespace App\Services;

use App\Models\Breeder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class BreederImporter
{
    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(User $user, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Impossible de lire le fichier CSV.']];
        }

        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $headers = null;
        $rowNumber = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($row === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->headers($row);

                continue;
            }

            $data = $this->rowData($headers, $row);
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                $skipped++;
                $errors[] = "Ligne $rowNumber ignoree : nom manquant.";

                continue;
            }

            if (($data['email'] ?? null) !== null && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
                $skipped++;
                $errors[] = "Ligne $rowNumber ignoree : email invalide.";

                continue;
            }

            $payload = [
                'address' => $data['address'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city' => $data['city'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'herd_number' => $data['herd_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            $breeder = Breeder::query()->where('user_id', $user->id)->where('name', $name)->first();

            if ($breeder) {
                $breeder->update($payload);
                $updated++;
            } else {
                Breeder::create(['user_id' => $user->id, 'name' => $name, ...$payload]);
                $created++;
            }
        }

        fclose($handle);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<int, string>
     */
    private function headers(array $row): array
    {
        return collect($row)
            ->map(fn (?string $value): string => $this->normalizeHeader((string) $value))
            ->all();
    }

    private function normalizeHeader(string $value): string
    {
        return match (Str::of($value)->lower()->ascii()->trim()->replace([' ', '-'], '_')->toString()) {
            'nom', 'raison_sociale', 'societe', 'name' => 'name',
            'adresse', 'address' => 'address',
            'code_postal', 'cp', 'postal_code' => 'postal_code',
            'ville', 'city' => 'city',
            'telephone', 'tel', 'phone' => 'phone',
            'email', 'mail' => 'email',
            'numero_cheptel', 'n_cheptel', 'ede', 'herd_number' => 'herd_number',
            'notes', 'note' => 'notes',
            default => Str::of($value)->lower()->ascii()->trim()->replace([' ', '-'], '_')->toString(),
        };
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function rowData(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = trim((string) ($row[$index] ?? ''));
            $data[$header] = $value === '' ? null : $value;
        }

        return $data;
    }
}
