<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_inra')->nullable();
            $table->string('type')->nullable();
            $table->string('libelle0')->nullable();
            $table->string('libelle1')->nullable();
            $table->string('libelle2')->nullable();
            $table->string('libelle3')->nullable();
            $table->string('libelle4')->nullable();
            $table->double('prix')->nullable();
            $table->integer('usage_aliment')->nullable();

            // Valeurs nutritionnelles de base
            $table->double('ms')->nullable();
            $table->double('mo')->nullable();
            $table->double('mat')->nullable();
            $table->double('cb')->nullable();
            $table->double('ndf')->nullable();
            $table->double('adf')->nullable();
            $table->double('adl')->nullable();
            $table->double('ee')->nullable();
            $table->double('ag')->nullable();
            $table->double('eb')->nullable();
            $table->double('em')->nullable();
            $table->double('amidon')->nullable();
            $table->double('sucres')->nullable();
            $table->double('pf')->nullable();

            // Digestibilités (2018)
            $table->double('d_mo')->nullable();
            $table->double('d_ma')->nullable();
            $table->double('d_cb')->nullable();
            $table->double('d_ndf')->nullable();
            $table->double('d_adf')->nullable();
            $table->double('d_e')->nullable();
            $table->double('dt_n')->nullable();
            $table->double('dt6_n')->nullable();
            $table->double('dr_n')->nullable();
            $table->double('dt_ami')->nullable();
            $table->double('dt6_ami')->nullable();
            $table->double('dt_ms')->nullable();
            $table->double('dt6_ms')->nullable();

            // Unités fourragères (2018)
            $table->double('ufl')->nullable();
            $table->double('ufv')->nullable();
            $table->double('uem')->nullable();
            $table->double('uel')->nullable();
            $table->double('ueb')->nullable();

            // PDI (2018)
            $table->double('pdia')->nullable();
            $table->double('pdi')->nullable();
            $table->double('bpr')->nullable();
            $table->double('niref')->nullable();

            // Acides aminés digestibles (2018)
            $table->double('lys_di')->nullable();
            $table->double('met_di')->nullable();
            $table->double('his_di')->nullable();
            $table->double('arg_di')->nullable();
            $table->double('thr_di')->nullable();
            $table->double('val_di')->nullable();
            $table->double('ile_di')->nullable();
            $table->double('leu_di')->nullable();
            $table->double('phe_di')->nullable();
            $table->double('asp_di')->nullable();
            $table->double('ser_di')->nullable();
            $table->double('glu_di')->nullable();
            $table->double('pro_di')->nullable();
            $table->double('gly_di')->nullable();
            $table->double('ala_di')->nullable();
            $table->double('tyr_di')->nullable();

            // Acides aminés BP (2018)
            $table->double('lys_bp')->nullable();
            $table->double('his_bp')->nullable();
            $table->double('arg_bp')->nullable();
            $table->double('thr_bp')->nullable();
            $table->double('val_bp')->nullable();
            $table->double('met_bp')->nullable();
            $table->double('ile_bp')->nullable();
            $table->double('leu_bp')->nullable();
            $table->double('phe_bp')->nullable();
            $table->double('asp_bp')->nullable();
            $table->double('ser_bp')->nullable();
            $table->double('glu_bp')->nullable();
            $table->double('pro_bp')->nullable();
            $table->double('gly_bp')->nullable();
            $table->double('ala_bp')->nullable();
            $table->double('tyr_bp')->nullable();
            $table->double('cys_trp_bp')->nullable();

            // Minéraux
            $table->double('ca')->nullable();
            $table->double('caabs')->nullable();
            $table->double('p')->nullable();
            $table->double('pabs')->nullable();
            $table->double('mg')->nullable();
            $table->double('na')->nullable();
            $table->double('k')->nullable();
            $table->double('cl')->nullable();
            $table->double('s')->nullable();
            $table->double('be')->nullable();
            $table->double('baca')->nullable();

            // Oligo-éléments
            $table->double('cu')->nullable();
            $table->double('zn')->nullable();
            $table->double('mn')->nullable();
            $table->double('co')->nullable();
            $table->double('se')->nullable();
            $table->double('i')->nullable();

            // Vitamines
            $table->double('vit_a')->nullable();
            $table->double('vit_d')->nullable();
            $table->double('vit_e')->nullable();

            // Acides gras
            $table->double('c6_10')->nullable();
            $table->double('c12_0')->nullable();
            $table->double('c14_0')->nullable();
            $table->double('c16_0')->nullable();
            $table->double('c16_1')->nullable();
            $table->double('c18_0')->nullable();
            $table->double('c18_1')->nullable();
            $table->double('c18_2')->nullable();
            $table->double('c18_3')->nullable();
            $table->double('c20_0')->nullable();
            $table->double('c20_1')->nullable();
            $table->double('c22_0')->nullable();
            $table->double('c22_1')->nullable();
            $table->double('c24_0')->nullable();
            $table->double('b_vec')->nullable();

            // Valeurs 2007 (anciennes normes INRA)
            $table->double('ufl2007')->nullable();
            $table->double('ufv2007')->nullable();
            $table->double('pdia2007')->nullable();
            $table->double('pdie2007')->nullable();
            $table->double('pdin2007')->nullable();
            $table->double('d_mo2007')->nullable();
            $table->double('d_ma2007')->nullable();
            $table->double('d_cb2007')->nullable();
            $table->double('d_ndf2007')->nullable();
            $table->double('d_adf2007')->nullable();
            $table->double('uem2007')->nullable();
            $table->double('uel2007')->nullable();
            $table->double('ueb2007')->nullable();
            $table->double('eb2007')->nullable();
            $table->double('d_e2007')->nullable();
            $table->double('em2007')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliments');
    }
};
