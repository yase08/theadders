<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reported_user_id');
            $table->unsignedBigInteger('reporter_id');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('reported_user_id')->references('users_id')->on('users')->onDelete('cascade');
            $table->foreign('reporter_id')->references('users_id')->on('users')->onDelete('cascade');

            $table->unique(['reported_user_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
