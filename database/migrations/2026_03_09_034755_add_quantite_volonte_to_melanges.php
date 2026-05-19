<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('melanges', function (Blueprint $table) {
            $table->double('quantite')->nullable()->after('nom');
            $table->boolean('is_volonte')->default(false)->after('quantite');
            $table->boolean('is_mb')->default(false)->after('is_volonte');
        });
    }

    public function down(): void
    {
        Schema::table('melanges', function (Blueprint $table) {
            $table->dropColumn(['quantite', 'is_volonte', 'is_mb']);
        });
    }
};
