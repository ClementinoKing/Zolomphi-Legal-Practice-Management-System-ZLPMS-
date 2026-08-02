<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\PracticeAlert;
use Illuminate\Support\Collection;

class SendPracticeAlert
{
    /**
     * @param  Collection<int, User>  $recipients
     * @param  array{title: string, body: string, url: string, type: string}  $alert
     */
    public function handle(Collection $recipients, ?User $actor, array $alert): void
    {
        $recipients
            ->filter(fn (User $recipient): bool => $actor === null || ! $recipient->is($actor))
            ->unique('id')
            ->each(function (User $recipient) use ($alert): void {
                $recipient->notify(
                    (new PracticeAlert(
                        title: $alert['title'],
                        body: $alert['body'],
                        url: $alert['url'],
                        type: $alert['type'],
                    ))->afterCommit(),
                );
            });
    }
}
