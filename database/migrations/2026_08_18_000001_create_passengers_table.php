<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Personal & Passport Info (Step 2)
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->boolean('no_last_name')->default(false);
            $table->string('passport_number')->index();
            $table->string('national_id')->nullable();
            $table->string('gender')->default('male'); // male / female
            $table->date('date_of_birth')->nullable();
            $table->date('passport_expiration_date')->nullable();
            $table->string('country_id')->default('1'); // Country ID (e.g. Bangladesh, Saudi, etc.)
            $table->string('country_name')->default('Bangladesh');
            $table->string('nationality_id')->default('1');
            $table->string('nationality_name')->default('Bangladeshi');
            
            // Document Files
            $table->string('passport_file_path')->nullable();
            $table->string('personal_photo_path')->nullable();
            $table->string('national_id_front_path')->nullable();
            $table->string('national_id_back_path')->nullable();
            
            // Other Details (Step 3)
            $table->string('education_level')->default('no_educational_qualification');
            $table->string('experience_level')->default('no_experience');
            $table->string('institute_name')->default('No, I don’t have any certifications or training');
            $table->string('custom_institute_name')->nullable();
            
            // Credentials & Security
            $table->string('email')->index();
            $table->string('password');
            $table->string('country_code')->default('+880');
            $table->string('phone_number')->nullable();
            $table->string('preferable_contact')->default('email'); // email or phone
            
            // Temp Mail / Access Details (For future login OTP)
            $table->string('temp_mail_id')->nullable();
            $table->string('temp_mail_password')->nullable();
            $table->text('temp_mail_token')->nullable();
            
            // Registration Status & Verification
            $table->string('status')->default('completed'); // pending, validating, otp_sent, completed, failed
            $table->string('otp_code')->nullable();
            $table->text('taqamul_response')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('passengers');
    }
};
