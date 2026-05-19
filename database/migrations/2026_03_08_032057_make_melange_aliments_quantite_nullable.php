<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melange_aliments', function (Blueprint $table) {
            $table->double('quantite')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('melange_aliments', function (Blueprint $table) {
            $table->double('quantite')->nullable(false)->change();
        });
    }
};
