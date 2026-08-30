<?php

namespace App\Console\Commands;

use App\Models\UserInformationReply;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

class RequestsInformationReplies extends Command
{
    protected $signature = 'requests:information-replies
        {--status=pending_review : Exact processing status, or all}
        {--from= : Inclusive timezone-qualified received_at boundary}
        {--to= : Exclusive timezone-qualified received_at boundary}
        {--max-id= : Frozen maximum reply ID}
        {--limit=200 : Maximum rows to display}';

    protected $description = 'List the auditable user-information reply review queue without changing it';

    public function handle(): int
    {
        try {
            $status = strtolower(trim((string) $this->option('status')));
            if ($status !== 'all' && ! in_array($status, UserInformationReply::PROCESSING_STATUSES, true)) {
                throw new InvalidArgumentException('Unknown processing status.');
            }
            $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 1000],
            ]);
            if ($limit === false) {
                throw new InvalidArgumentException('--limit must be between 1 and 1000.');
            }

            $query = UserInformationReply::query()
                ->withCount('attachments')
                ->with(['request:id,status,product_name', 'delivery:id,status'])
                ->orderBy('received_at')
                ->orderBy('id');
            if ($status !== 'all') {
                $query->where('processing_status', $status);
            }
            foreach (['from' => '>=', 'to' => '<'] as $option => $operator) {
                $value = trim((string) $this->option($option));
                if ($value !== '') {
                    $query->where('received_at', $operator, $this->parseBoundary($value));
                }
            }
            $maxId = trim((string) $this->option('max-id'));
            if ($maxId !== '') {
                if (preg_match('/^[1-9]\d*$/D', $maxId) !== 1) {
                    throw new InvalidArgumentException('--max-id must be a positive integer.');
                }
                $query->where('id', '<=', (int) $maxId);
            }

            $rows = $query->limit((int) $limit)->get();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Reply', 'Received', 'Request', 'Barcode', 'Sender', 'Match', 'Delivery', 'Files', 'State'],
            $rows->map(fn (UserInformationReply $reply) => [
                $reply->id,
                $reply->received_at?->toIso8601String(),
                $reply->request_id ?: '-',
                $reply->barcode ?: '-',
                $reply->normalized_from_address,
                $reply->match_method ?: '-',
                $reply->delivery?->status ?: '-',
                $reply->attachments_count,
                $reply->processing_status,
            ])->all(),
        );
        $this->info("Displayed {$rows->count()} reply/replies. No state was changed.");

        return self::SUCCESS;
    }

    private function parseBoundary(string $value): Carbon
    {
        if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $value) !== 1) {
            throw new InvalidArgumentException('Date boundaries must include an explicit timezone.');
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'));
    }
}
