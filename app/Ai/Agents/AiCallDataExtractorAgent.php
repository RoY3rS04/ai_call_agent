<?php

namespace App\Ai\Agents;

use App\Enums\CallRoles;
use App\Enums\LeadSource;
use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Promptable;
use Stringable;

class AiCallDataExtractorAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function __construct(protected Call $call) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return file_get_contents(resource_path('prompts/ai-call-extractor-agent.md'));
    }

    public function messages(): iterable
    {
        return $this->call->callMessages()
            ->orderBy('id')
            ->whereNotNull('content')
            ->get()
            ->map(function (CallMessage $message) {
                return new Message(
                    $message->role === CallRoles::ASSISTANT
                        ? MessageRole::Assistant
                        : MessageRole::User,
                    $message->content
                );
            })
            ->all();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'customer' => $schema->object(fn ($schema) => [
                'first_name' => $schema->string()->nullable(),
                'last_name' => $schema->string()->nullable(),
                'email' => $schema->string()->nullable(),
                'phone' => $schema->string()->nullable(),
                'timezone' => $schema->string()->nullable(),
                'lead_source' => $schema->string()->enum(LeadSource::class)->nullable(),
            ])->withoutAdditionalProperties()->required(),
            'company' => $schema->object(fn ($schema) => [
                'name' => $schema->string()->nullable(),
                'country' => $schema->string()->nullable(),
            ])->withoutAdditionalProperties()->required(),
            'meeting' => $schema->object(fn ($schema) => [
                'start_time' => $schema->string()->nullable(),
                'end_time' => $schema->string()->nullable(),
                'timezone' => $schema->string()->nullable(),
                'reason' => $schema->string()->nullable(),
                'source' => $schema->string()->nullable(),
                'notes' => $schema->string()->nullable(),
                'status' => $schema->string()->enum(MeetingStatus::class)->nullable(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
