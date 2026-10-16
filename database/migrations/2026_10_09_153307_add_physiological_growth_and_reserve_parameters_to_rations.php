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
        Schema::table('rations', function (Blueprint $table) {
            $table->decimal('age_mois', 6, 2)->nullable();
            $table->decimal('variation_nec', 4, 2)->nullable();
            $table->unsignedSmallInteger('duree_variation_nec_jours')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->dropColumn(['age_mois', 'variation_nec', 'duree_variation_nec_jours']);
        });
    }
};
