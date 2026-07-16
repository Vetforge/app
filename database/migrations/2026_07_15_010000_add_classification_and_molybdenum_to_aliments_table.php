<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliments', function (Blueprint $table): void {
            $table->string('famille_botanique')->nullable()->after('type');
            $table->string('procede_technologique')->nullable()->after('famille_botanique');
            $table->double('molybdene')->nullable()->after('i');
        });

        DB::table('aliments')
            ->select(['id', 'type', 'libelle0', 'libelle1', 'libelle4'])
            ->orderBy('id')
            ->chunkById(500, function ($aliments): void {
                foreach ($aliments as $aliment) {
                    $detail = Str::of(implode(' ', [
                        $aliment->libelle0,
                        $aliment->libelle1,
                        $aliment->libelle4,
                    ]))->lower()->ascii()->squish()->value();
                    $famille = match (true) {
                        str_contains($detail, 'mais') => 'mais',
                        str_contains($detail, 'luzerne') => 'luzerne',
                        str_contains($detail, 'trefle'), str_contains($detail, 'legumineuse') => 'legumineuse',
                        str_contains($detail, 'prairie'), str_contains($detail, 'graminee') => 'graminee',
                        default => 'autre',
                    };
                    $procede = match (true) {
                        $aliment->type === 'Mineral' => 'mineral',
                        str_contains($detail, 'deshydrat') => 'deshydrate',
                        str_contains($detail, 'ensilage') => 'ensile',
                        str_contains($detail, 'foin') => 'foin',
                        str_contains($detail, 'paille') => 'paille',
                        str_contains($detail, 'fourrages verts') => 'vert',
                        $aliment->type === 'Conc' => 'concentre',
                        default => 'autre',
                    };

                    DB::table('aliments')->where('id', $aliment->id)->update([
                        'famille_botanique' => $famille,
                        'procede_technologique' => $procede,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('aliments', function (Blueprint $table): void {
            $table->dropColumn(['famille_botanique', 'procede_technologique', 'molybdene']);
        });
    }
};
