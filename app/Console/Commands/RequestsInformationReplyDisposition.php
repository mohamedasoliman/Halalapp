<?php

namespace App\Console\Commands;

use App\Models\UserInformationReply;
use App\Services\UserInformationReplyService;
use Illuminate\Console\Command;
use Throwable;

class RequestsInformationReplyDisposition extends Command
{
    protected $signature = 'requests:information-reply-disposition
        {reply : User-information reply ID}
        {outcome : processed, needs_clarification, or no_action}
        {--reason= : Reviewed audit reason for the terminal outcome}';

    protected $description = 'Record an approved terminal outcome for one user-information reply';

    public function handle(UserInformationReplyService $replies): int
    {
        $replyId = filter_var($this->argument('reply'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($replyId === false) {
            $this->error('A valid reply ID is required.');

            return self::FAILURE;
        }

        $reply = UserInformationReply::find($replyId);
        if (! $reply) {
            $this->error("User-information reply #{$replyId} was not found.");

            return self::FAILURE;
        }

        try {
            $reply = $replies->disposition(
                $reply,
                (string) $this->argument('outcome'),
                (string) $this->option('reason'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "Reply #{$reply->id} recorded as {$reply->processing_status}; request #{$reply->request_id} was not reopened or resolved."
        );
        $this->line('No product verdict, manufacturer communication, user email, or mailbox flag was changed.');

        return self::SUCCESS;
    }
}
