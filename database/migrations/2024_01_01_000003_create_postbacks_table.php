<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postbacks', function (Blueprint $table) {
            $table->id();
            $table->string('click_id');
            $table->string('event');
            $table->decimal('amount', 12, 4);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('partner_response')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['click_id', 'event', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postbacks');
    }
};
