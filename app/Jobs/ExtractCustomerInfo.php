<?php

namespace App\Jobs;

use App\Ai\Agents\AiCallDataExtractorAgent;
use App\Enums\LeadSource;
use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Meeting;
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

        if ($name === null || $country === null) {
            return null;
        }

        $company = Company::firstOrCreate([
            'name' => $name,
            'country' => $country,
        ]);

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

        if (
            $company === null ||
            $firstName === null ||
            $email === null ||
            $phone === null ||
            $timezone === null ||
            $leadSource === null
        ) {
            return null;
        }

        $customer = Customer::firstOrNew([
            'email' => $email,
        ]);

        $customer->fill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'timezone' => $timezone,
            'lead_source' => $leadSource->value,
        ]);

        $customer->company()->associate($company);
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

        if (
            ! $meeting->exists &&
            ($customer === null || $company === null || $startTime === null || $endTime === null || $timezone === null || $status === null)
        ) {
            return null;
        }

        if ($customer !== null) {
            $meeting->customer()->associate($customer);
        }

        if ($company !== null) {
            $meeting->company()->associate($company);
        }

        $meeting->call()->associate($this->call);

        $meetingAttributes = array_filter([
            'start_time' => $startTime,
            'end_time' => $endTime,
            'timezone' => $timezone,
            'reason' => $reason,
            'source' => $source ?? ($meeting->exists ? null : 'ai_call'),
            'notes' => $notes,
            'status' => $status?->value,
        ], static fn (mixed $value): bool => $value !== null);

        if ($meetingAttributes === [] && $meeting->exists) {
            return $meeting;
        }

        $meeting->fill($meetingAttributes);
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
}
