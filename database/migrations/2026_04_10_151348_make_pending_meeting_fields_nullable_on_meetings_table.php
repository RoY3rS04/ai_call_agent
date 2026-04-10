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
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->change();

            $table->foreignId('company_id')
                ->nullable()
                ->change();

            $table->dateTime('start_time')
                ->nullable()
                ->change();

            $table->dateTime('end_time')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable(false)
                ->change();

            $table->foreignId('company_id')
                ->nullable(false)
                ->change();

            $table->dateTime('start_time')
                ->nullable(false)
                ->change();

            $table->dateTime('end_time')
                ->nullable(false)
                ->change();
        });
    }
};
