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
            if (!Schema::hasColumn('slot_holds', 'next_candidate_email')) {
                $table->string('next_candidate_email', 255)->nullable()->after('held_with_email');
            }
            if (!Schema::hasColumn('slot_holds', 'next_candidate_token')) {
                $table->text('next_candidate_token')->nullable()->after('next_candidate_email');
            }
            if (!Schema::hasColumn('slot_holds', 'prewarm_status')) {
                $table->string('prewarm_status', 50)->nullable()->default('idle')->after('next_candidate_token');
            }
            if (!Schema::hasColumn('slot_holds', 'prewarmed_at')) {
                $table->timestamp('prewarmed_at')->nullable()->after('prewarm_status');
            }
            if (!Schema::hasColumn('slot_holds', 'last_failure_reason')) {
                $table->text('last_failure_reason')->nullable()->after('seat_history');
            }
            if (!Schema::hasColumn('slot_holds', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable()->after('last_failure_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_holds', function (Blueprint $table) {
            $cols = [
                'next_candidate_email',
                'next_candidate_token',
                'prewarm_status',
                'prewarmed_at',
                'last_failure_reason',
                'last_failure_at',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('slot_holds', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
