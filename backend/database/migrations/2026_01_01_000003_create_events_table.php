<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_type')->default('lainnya');
            $table->string('organizer');
            $table->date('event_date');
            $table->time('event_time');
            $table->string('location');
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->string('banner')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_date', 'event_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};
