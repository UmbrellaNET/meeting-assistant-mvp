<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Services\IcsGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves a downloadable .ics file for a single meeting, so the meeting can
 * be added to the user's Google/Outlook/Apple calendar without any OAuth
 * or provider-specific integration.
 *
 * Route (add to routes/api.php, inside the authenticated group):
 *   Route::get('meetings/{meeting}/calendar.ics', CalendarExportController::class);
 */
class CalendarExportController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting, IcsGenerator $ics): Response
    {
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);

        return response($ics->build($meeting), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $ics->filename($meeting) . '"',
        ]);
    }
}
