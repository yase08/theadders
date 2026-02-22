<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabel_product', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('product_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tabel_product', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
