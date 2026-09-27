<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\IcsGenerator;
use Illuminate\Http\Response;

/**
 * Public, unauthenticated equivalent of CalendarExportController — serves
 * the same .ics file, but looks the meeting up by its share_token instead
 * of requiring an authenticated tenant user.
 *
 * Route (add to routes/api.php, OUTSIDE the api.token group):
 *   Route::get('calendar/{token}/calendar.ics', PublicCalendarExportController::class);
 */
class PublicCalendarExportController extends Controller
{
    public function __invoke(string $token, IcsGenerator $ics): Response
    {
        $meeting = Meeting::where('share_token', $token)->firstOrFail();

        return response($ics->build($meeting), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $ics->filename($meeting) . '"',
        ]);
    }
}