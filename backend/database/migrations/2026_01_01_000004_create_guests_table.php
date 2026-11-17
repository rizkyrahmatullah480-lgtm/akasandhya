<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('whatsapp_number', 20);
            $table->string('category')->nullable();
            $table->string('unique_token', 80)->unique();
            $table->timestamps();

            $table->index(['event_id', 'name']);
            $table->index(['event_id', 'category']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('guests');
    }
};
