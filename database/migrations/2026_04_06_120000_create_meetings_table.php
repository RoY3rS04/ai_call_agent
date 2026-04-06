<?php

use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Customer;
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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Customer::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Call::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('timezone')->default('UTC');
            $table->string('google_calendar_event_id')->nullable();
            $table->enum('status', array_column(MeetingStatus::cases(), 'value'))
                ->default(MeetingStatus::PENDING->value);
            $table->timestamp('confirmed_at')->nullable();
            $table->string('source')->default('ai_call');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['start_time', 'end_time']);
            $table->index(['marketing_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
