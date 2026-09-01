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
        Schema::create('payment_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('bank_name')->default('IFIC Bank');
            $table->string('card_holder_name');
            $table->string('card_number'); // Plain or encrypted
            $table->string('expiry_month', 2); // MM e.g. 05
            $table->string('expiry_year', 4);  // YYYY e.g. 2028
            $table->string('cvv', 4);          // CVV e.g. 123
            $table->string('card_type')->default('Visa'); // Visa, Mastercard, etc.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_cards');
    }
};
