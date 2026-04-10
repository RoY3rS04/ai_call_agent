<?php

namespace App\Jobs;

use App\Ai\Agents\AiCallDataExtractorAgent;
use App\Enums\LeadSource;
use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ExtractCustomerInfo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Call $call
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $resp = (new AiCallDataExtractorAgent($this->call))
            ->prompt('Extract the relevant data from this call transcript');

        DB::transaction(function () use ($resp): void {
            $company = $this->upsertCompany($resp['company'] ?? []);
            $customer = $this->upsertCustomer($resp['customer'] ?? [], $company);

            if ($customer !== null && ! $this->call->customer?->is($customer)) {
                $this->call->customer()->associate($customer);
                $this->call->save();
            }

            $this->upsertMeeting(
                $resp['meeting'] ?? [],
                $customer,
                $company,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $companyData
     */
    protected function upsertCompany(array $companyData): ?Company
    {
        $name = $this->cleanString($companyData['name'] ?? null);
        $country = $this->cleanString($companyData['country'] ?? null);

        if ($name === null) {
            return null;
        }

        $company = Company::query()
            ->where('name', $name)
            ->when($country !== null, fn ($query) => $query->where('country', $country))
            ->first() ?? Company::firstOrNew([
                'name' => $name,
            ]);

        if ($country !== null && blank($company->country)) {
            $company->country = $country;
        }

        if (! $company->exists || $company->isDirty()) {
            $company->save();
        }

        return $company;
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    protected function upsertCustomer(array $customerData, ?Company $company): ?Customer
    {
        $firstName = $this->cleanString($customerData['first_name'] ?? null);
        $lastName = $this->cleanString($customerData['last_name'] ?? null);
        $email = $this->cleanString($customerData['email'] ?? null);
        $phone = $this->cleanString($customerData['phone'] ?? null);
        $timezone = $this->cleanString($customerData['timezone'] ?? null);
        $leadSource = LeadSource::tryFrom((string) ($customerData['lead_source'] ?? ''));

        if ($email === null && $phone === null) {
            return null;
        }

        $customer = null;

        if ($email !== null) {
            $customer = Customer::query()
                ->where('email', $email)
                ->first();
        }

        if ($customer === null && $phone !== null) {
            $customer = Customer::query()
                ->where('phone', $phone)
                ->first();
        }

        $customer ??= new Customer;

        $customer->fill(array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'timezone' => $timezone,
            'lead_source' => $leadSource?->value,
        ], static fn (mixed $value): bool => $value !== null));

        if ($company !== null) {
            $customer->company()->associate($company);
        }

        $customer->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $meetingData
     */
    protected function upsertMeeting(array $meetingData, ?Customer $customer, ?Company $company): ?Meeting
    {
        $startTime = $this->cleanString($meetingData['start_time'] ?? null);
        $endTime = $this->cleanString($meetingData['end_time'] ?? null);
        $timezone = $this->cleanString($meetingData['timezone'] ?? null);
        $reason = $this->cleanString($meetingData['reason'] ?? null);
        $source = $this->cleanString($meetingData['source'] ?? null);
        $notes = $this->cleanString($meetingData['notes'] ?? null);
        $status = MeetingStatus::tryFrom((string) ($meetingData['status'] ?? ''));

        $hasMeetingData = $startTime !== null
            || $endTime !== null
            || $timezone !== null
            || $reason !== null
            || $source !== null
            || $notes !== null
            || $status !== null;

        if (! $hasMeetingData) {
            return null;
        }

        $meeting = Meeting::firstOrNew([
            'call_id' => $this->call->getKey(),
        ]);

        if ($customer !== null) {
            $meeting->customer()->associate($customer);
        }

        if ($company !== null) {
            $meeting->company()->associate($company);
        }

        $meeting->call()->associate($this->call);

        $meetingTimezone = $timezone
            ?? $customer?->timezone
            ?? $this->call->customer?->timezone;
        $startDateTime = $this->normalizeMeetingDateTime($startTime, $meetingTimezone);
        $endDateTime = $this->normalizeMeetingDateTime($endTime, $meetingTimezone);

        $meetingAttributes = [];

        if (! $this->meetingHasProtectedBookingFields($meeting)) {
            $meetingAttributes = array_filter([
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'timezone' => $meetingTimezone,
                'status' => $status?->value ?? ($meeting->exists ? null : MeetingStatus::PENDING->value),
            ], static fn (mixed $value): bool => $value !== null);
        }

        if ($reason !== null && $this->shouldBackfillMeetingText($meeting->reason, $meeting->exists)) {
            $meetingAttributes['reason'] = $reason;
        }

        if ($notes !== null && $this->shouldBackfillMeetingText($meeting->notes, $meeting->exists)) {
            $meetingAttributes['notes'] = $notes;
        }

        if ($source !== null && $this->shouldBackfillMeetingText($meeting->source, $meeting->exists)) {
            $meetingAttributes['source'] = $source;
        } elseif (! $meeting->exists) {
            $meetingAttributes['source'] = 'ai_call';
        }

        $meeting->fill($meetingAttributes);

        if ($meeting->exists && ! $meeting->isDirty()) {
            return $meeting;
        }

        $meeting->save();

        return $meeting;
    }

    protected function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function meetingHasProtectedBookingFields(Meeting $meeting): bool
    {
        if (! $meeting->exists) {
            return false;
        }

        return filled($meeting->google_calendar_event_id)
            || $meeting->confirmed_at !== null
            || in_array($meeting->status, [
                MeetingStatus::CONFIRMED,
                MeetingStatus::COMPLETED,
                MeetingStatus::CANCELLED,
                MeetingStatus::NO_SHOW,
            ], true);
    }

    protected function shouldBackfillMeetingText(?string $currentValue, bool $meetingExists): bool
    {
        return ! $meetingExists || blank($currentValue);
    }

    protected function normalizeMeetingDateTime(?string $value, ?string $timezone): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $timezone !== null
            ? Carbon::parse($value, $timezone)
            : Carbon::parse($value);

        return $dateTime->setTimezone(config('app.timezone', 'UTC'));
    }
}
