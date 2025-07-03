<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRatingsTable extends Migration
{
    public function up()
    {
        Schema::create('trs_user_rating', function (Blueprint $table) {
            $table->id('user_rating_id');
            $table->unsignedInteger('rated_user_id'); // Changed from unsignedBigInteger
            $table->unsignedInteger('rater_user_id'); // Changed from unsignedBigInteger
            $table->unsignedBigInteger('exchange_id');
            $table->integer('rating');
            $table->timestamp('created')->useCurrent();
            $table->string('author');
            $table->tinyInteger('status')->default(1);

            $table->foreign('rated_user_id')
                  ->references('users_id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('rater_user_id')
                  ->references('users_id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('exchange_id')
                  ->references('exchange_id')
                  ->on('trs_exchange')
                  ->onDelete('cascade');

            $table->unique(['rater_user_id', 'rated_user_id', 'exchange_id'], 'unique_user_rating');
        })->charset('utf8mb4')->collation('utf8mb4_unicode_ci'); // Added charset and collation

    }

    public function down()
    {
        Schema::dropIfExists('trs_user_rating');
    }
}