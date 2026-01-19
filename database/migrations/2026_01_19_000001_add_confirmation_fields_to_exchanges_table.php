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
        Schema::table('trs_exchange', function (Blueprint $table) {
            $table->boolean('requester_confirmed')->default(false)->after('status');
            $table->boolean('receiver_confirmed')->default(false)->after('requester_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trs_exchange', function (Blueprint $table) {
            $table->dropColumn(['requester_confirmed', 'receiver_confirmed']);
        });
    }
};
