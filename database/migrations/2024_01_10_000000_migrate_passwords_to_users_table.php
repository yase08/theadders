<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigratePasswordsToUsersTable extends Migration
{
    public function up()
    {
        // Add password column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable();
        });

        // Migrate existing passwords
        DB::statement('UPDATE users u 
            INNER JOIN pw_users pw ON u.users_id = pw.id 
            SET u.password = pw.password');

        // Drop pw_users table
        Schema::dropIfExists('pw_users');
    }

    public function down()
    {
        // Create pw_users table
        Schema::create('pw_users', function (Blueprint $table) {
            $table->unsignedBigInteger('id');
            $table->string('username');
            $table->string('nama_lengkap');
            $table->string('password');
            $table->string('tipe');
            $table->string('akses');
            $table->string('kodeacak');
            $table->string('updater');
            $table->string('status');
            $table->primary('id');
        });

        // Migrate passwords back
        DB::statement('INSERT INTO pw_users (id, username, nama_lengkap, password, tipe, akses, kodeacak, updater, status)
            SELECT users_id, fullname, fullname, password, "system", "system", "system", "system", status
            FROM users');

        // Remove password column from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
}