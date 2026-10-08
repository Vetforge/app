<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('aliments')->select('id', 'type')->chunkById(500, function ($aliments): void {
            foreach ($aliments as $aliment) {
                $normalized = Str::of((string) $aliment->type)->lower()->ascii()->squish()->value();
                $canonical = match ($normalized) {
                    'fourrage', 'fourrages' => 'fourrage',
                    'conc', 'concentre', 'concentres' => 'concentre',
                    'mineral', 'mineraux' => 'mineral',
                    default => null,
                };

                if ($canonical !== null && $canonical !== $aliment->type) {
                    DB::table('aliments')->where('id', $aliment->id)->update(['type' => $canonical]);
                }
            }
        });
    }

    public function down(): void
    {
        // Les variantes historiques ne peuvent pas être reconstruites après normalisation.
    }
};
