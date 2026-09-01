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
        Schema::create('slot_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('mother_hash')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->string('category_name')->nullable();
            $table->string('category_name_ar')->nullable();
            $table->string('city')->index();
            $table->date('exam_date')->index();
            $table->string('center_name')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->text('center_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('start_time')->default('09:30 AM');
            $table->string('available_seats')->default('Available');
            $table->text('location_link')->nullable();
            $table->timestamp('discovered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['mother_hash', 'exam_date'], 'unique_hash_exam_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_hashes');
    }
};
