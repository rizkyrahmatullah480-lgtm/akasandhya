<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('invitation_url');
            $table->string('qr_code_path')->nullable();
            $table->enum('delivery_status', ['not_sent', 'queued', 'sent', 'failed'])->default('not_sent');
            $table->timestamp('sent_at')->nullable();
            $table->text('delivery_error')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'delivery_status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('invitations');
    }
};
