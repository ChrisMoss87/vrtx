<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EmailTrackingController extends Controller
{
    /**
     * Track email open (1x1 pixel).
     */
    public function trackOpen(string $trackingId): Response
    {
        $message = DB::table('email_messages')->where('tracking_id', $trackingId)->first();

        if ($message) {
            $message->recordOpen();
        }

        // Return 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Track link click and redirect.
     */
    public function trackClick(string $trackingId, string $url): Response
    {
        $message = DB::table('email_messages')->where('tracking_id', $trackingId)->first();

        if ($message) {
            $message->recordClick();
        }

        // Decode the URL and redirect
        $decodedUrl = base64_decode($url);

        return response('', 302, [
            'Location' => $decodedUrl,
        ]);
    }
}
