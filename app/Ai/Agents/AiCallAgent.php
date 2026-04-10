<?php

namespace App\Ai\Agents;

use App\Ai\Tools\BookMarketingMeeting;
use App\Ai\Tools\CheckMarketingCalendar;
use App\Models\Call;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class AiCallAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public Call $call) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return file_get_contents(resource_path('prompts/ai-call-agent.md'));
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new CheckMarketingCalendar,
            new BookMarketingMeeting($this->call),
        ];
    }
}
