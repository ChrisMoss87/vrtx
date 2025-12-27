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

// Collaborative Document presence channel (for real-time editing)
Broadcast::channel('document.{documentId}', function ($user, $documentId) {
    // Check if user has access to the document (owner or collaborator)
    $documentRepository = app(\App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface::class);
    $collaboratorRepository = app(\App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface::class);

    $document = $documentRepository->findById((int) $documentId);

    if (!$document) {
        return false;
    }

    // Owner always has access
    if ($document->getOwnerId() === (int) $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'color' => generateUserColor($user->id),
            'permission' => 'owner',
        ];
    }

    // Check if user is a collaborator
    $collaborator = $collaboratorRepository->findByDocumentAndUser((int) $documentId, (int) $user->id);

    if ($collaborator) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'color' => generateUserColor($user->id),
            'permission' => $collaborator->getPermission()->value,
        ];
    }

    return false;
});

/**
 * Generate a consistent color for a user based on their ID.
 */
function generateUserColor(int $userId): string
{
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
        '#F8B500', '#00CED1', '#FF69B4', '#32CD32', '#FF7F50',
    ];

    return $colors[$userId % count($colors)];
}
