<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->decimal('mois_lactation', 5, 2)->nullable()->change();
            $table->decimal('mois_gestation', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->integer('mois_lactation')->nullable()->change();
            $table->integer('mois_gestation')->nullable()->change();
        });
    }
};
