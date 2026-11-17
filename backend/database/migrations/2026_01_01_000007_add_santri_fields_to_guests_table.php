<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('santri_code', 20)->nullable()->after('unique_token');
            $table->string('wali_angkatan')->nullable()->after('category');

            $table->unique(['event_id', 'santri_code']);
        });
    }

    public function down()
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'santri_code']);
            $table->dropColumn(['santri_code', 'wali_angkatan']);
        });
    }
};
