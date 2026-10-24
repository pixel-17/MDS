<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

class HorarioActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public static function notificar(int ...$userIds): void
    {
        $ids = array_values(array_unique($userIds));

        DB::afterCommit(function () use ($ids) {
            foreach ($ids as $id) {
                try {
                    event(new self($id));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
