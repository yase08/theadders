<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reports', function (Blueprint $table) {
            $table->string('title')->nullable()->after('reason');
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->string('title')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('product_reports', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
