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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->timestamp('timestamp');
            $table->string('status')->nullable();
            $table->string('verif')->nullable();
            $table->smallInteger('state')->nullable();
            $table->unsignedTinyInteger('type')->nullable(); // in/out indicator
            $table->string('device_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'timestamp']); // prevent duplicates
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
