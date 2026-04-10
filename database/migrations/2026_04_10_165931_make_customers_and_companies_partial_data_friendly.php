<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('companies', function (Blueprint $table) {
            $table->string('country')
                ->nullable()
                ->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')
                ->nullable()
                ->change();

            $table->string('email')
                ->nullable()
                ->change();

            $table->string('phone')
                ->nullable()
                ->change();

            $table->string('timezone')
                ->nullable()
                ->change();
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN lead_source DROP NOT NULL');

            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('lead_source')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('companies', function (Blueprint $table) {
            $table->string('country')
                ->nullable(false)
                ->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')
                ->nullable(false)
                ->change();

            $table->string('email')
                ->nullable(false)
                ->change();

            $table->string('phone')
                ->nullable(false)
                ->change();

            $table->string('timezone')
                ->default('UTC')
                ->nullable(false)
                ->change();
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE customers ALTER COLUMN lead_source SET NOT NULL');

            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->string('lead_source')
                ->nullable(false)
                ->change();
        });
    }
};
