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
        Schema::create('slot_holds', function (Blueprint $table) {
            $table->id();
            $table->string('mother_hash', 255)->index();
            $table->string('center_name', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('category_id')->default(159);
            $table->string('category_name', 150)->nullable();
            $table->date('exam_date')->nullable();
            $table->string('temp_seat_id', 255)->nullable();
            $table->string('held_with_email', 255)->nullable();
            $table->string('status', 50)->default('active')->index(); // active, released, expired
            $table->integer('renew_count')->default(0);
            $table->integer('target_duration_minutes')->default(120); // 0 = indefinite
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('auto_renew_until')->nullable();
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_holds');
    }
};
