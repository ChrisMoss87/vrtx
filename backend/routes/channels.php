<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User's private notification channel
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// User's presence channel (for online status, typing indicators, etc.)
Broadcast::channel('presence.{userId}', function ($user, $userId) {
    if ((int) $user->id === (int) $userId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
    return false;
});
