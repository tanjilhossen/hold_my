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
        Schema::table('slot_holds', function (Blueprint $table) {
            if (!Schema::hasColumn('slot_holds', 'seat_history')) {
                $table->json('seat_history')->nullable()->after('renew_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_holds', function (Blueprint $table) {
            if (Schema::hasColumn('slot_holds', 'seat_history')) {
                $table->dropColumn('seat_history');
            }
        });
    }
};
