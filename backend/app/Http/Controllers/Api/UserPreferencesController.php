<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Get all user preferences.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'data' => $user->preferences ?? []
        ]);
    }

    /**
     * Get a specific preference.
     */
    public function show(string $key): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'data' => [
                'key' => $key,
                'value' => $user->getPreference($key)
            ]
        ]);
    }

    /**
     * Update preferences (merge with existing).
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.sidebar_style' => 'sometimes|string|in:zoho,figma',
            'preferences.theme' => 'sometimes|string|in:light,dark,system',
            'preferences.compact_mode' => 'sometimes|boolean',
            'preferences.notifications_enabled' => 'sometimes|boolean',
        ]);

        $currentPreferences = $user->preferences ?? [];
        $newPreferences = array_merge($currentPreferences, $validated['preferences']);

        $user->preferences = $newPreferences;
        $user->save();

        return response()->json([
            'data' => $user->preferences,
            'message' => 'Preferences updated successfully'
        ]);
    }

    /**
     * Set a single preference.
     */
    public function set(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'required',
        ]);

        $user->setPreference($validated['key'], $validated['value']);

        return response()->json([
            'data' => [
                'key' => $validated['key'],
                'value' => $validated['value']
            ],
            'message' => 'Preference saved successfully'
        ]);
    }
}
