<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->string('strategie')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->double('strategie')->nullable()->change();
        });
    }
};
