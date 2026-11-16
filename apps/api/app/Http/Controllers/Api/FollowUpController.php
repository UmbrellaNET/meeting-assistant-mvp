<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\FollowUpDraft;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

// Put in: apps/api/app/Http/Controllers/Api/FollowUpController.php
class FollowUpController extends Controller
{
    /** The latest follow-up email for a meeting (draft or already sent), or null. */
    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting), 403);

        $draft = FollowUpDraft::where('meeting_id', $meeting->id)->latest()->first();

        return response()->json(['draft' => $draft, 'mail_enabled' => $this->mailEnabled()]);
    }

    /** Build (or rebuild) an editable draft from the meeting notes and action items. Nothing is invented: it is a template. */
    public function generate(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting), 403);

        $summary = MeetingSummary::where('meeting_id', $meeting->id)->where('status', 'completed')->first();
        abort_unless($summary, 422, 'Meeting notes are not ready yet.');

        $user = $request->user();
        $meeting->load('participants.user');

        $items = ActionItem::where('meeting_id', $meeting->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get();

        // Default recipients: everyone who accepted and has an account, except the sender.
        $recipients = $meeting->participants
            ->filter(fn ($participant) => $participant->status === 'accepted'
                && $participant->user?->email
                && $participant->user_id !== $user->id)
            ->map(fn ($participant) => strtolower($participant->user->email))
            ->unique()
            ->values()
            ->all();

        $attributes = [
            'subject' => $this->composeSubject($meeting),
            'body' => $this->composeBody($meeting, $summary, $items, $user->name),
            'recipients' => $recipients,
        ];

        // Regenerating replaces an unsent draft; a sent email is kept as history and a new draft is started.
        $draft = FollowUpDraft::where('meeting_id', $meeting->id)->where('status', 'draft')->latest()->first();
        if ($draft) {
            $draft->fill($attributes)->save();
        } else {
            $draft = FollowUpDraft::create($attributes + [
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'created_by_user_id' => $user->id,
                'status' => 'draft',
            ]);
        }

        return response()->json(['draft' => $draft->fresh()]);
    }

    public function update(Request $request, FollowUpDraft $draft): JsonResponse
    {
        $meeting = Meeting::findOrFail($draft->meeting_id);
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting), 403);
        abort_unless($draft->status === 'draft', 422, 'This email has already been sent.');

        $data = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:20000'],
            'recipients' => ['sometimes', 'array', 'max:50'],
            'recipients.*' => ['email:rfc', 'max:255'],
        ]);

        if (array_key_exists('recipients', $data)) {
            $data['recipients'] = array_values(array_unique(array_map('strtolower', $data['recipients'])));
        }

        $draft->fill($data)->save();

        return response()->json(['draft' => $draft->fresh()]);
    }

    /** Send the draft to each recipient separately, so nobody sees the other addresses. */
    public function send(Request $request, FollowUpDraft $draft): JsonResponse
    {
        $meeting = Meeting::findOrFail($draft->meeting_id);
        $this->authorizeView($request, $meeting);
        abort_unless($this->canEdit($request, $meeting), 403);
        abort_unless($draft->status === 'draft', 422, 'This email has already been sent.');
        abort_unless($this->mailEnabled(), 503, 'Email sending is not set up yet. Copy the draft or open it in Outlook instead.');

        $recipients = array_values(array_filter((array) $draft->recipients));
        abort_unless($recipients !== [], 422, 'Choose at least one recipient.');

        $user = $request->user();
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#222">'
            .nl2br(e($draft->body))
            .'</div>';

        $sent = [];
        $failed = [];
        foreach ($recipients as $email) {
            try {
                Mail::html($html, function ($message) use ($email, $draft, $user): void {
                    $message->to($email)->subject($draft->subject)->replyTo($user->email, $user->name);
                });
                $sent[] = $email;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $email;
            }
        }

        abort_if($sent === [], 502, 'The email could not be sent. Check the mail settings on the server.');

        $draft->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by_user_id' => $user->id,
            'sent_to' => $sent,
        ])->save();

        return response()->json([
            'draft' => $draft->fresh(),
            'failed' => $failed,
            'mailer' => config('mail.default'),
        ]);
    }

    /** False while the mailer is 'log' or 'array': those only write to a log file and send nothing. */
    private function mailEnabled(): bool
    {
        return !in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function composeSubject(Meeting $meeting): string
    {
        $subject = 'Recap: '.$meeting->title;
        if ($meeting->scheduled_start_at) {
            $subject .= ' ('.Carbon::parse($meeting->scheduled_start_at)->format('j M Y').')';
        }

        return mb_substr($subject, 0, 255);
    }

    private function composeBody(Meeting $meeting, MeetingSummary $summary, Collection $items, string $senderName): string
    {
        $when = $meeting->scheduled_start_at
            ? ' on '.Carbon::parse($meeting->scheduled_start_at)->format('j F Y')
            : '';

        $lines = ['Hi all,', '', 'Thanks for joining "'.$meeting->title.'"'.$when.'. Here is a short recap.', ''];

        $executive = trim((string) $summary->executive_summary);
        if ($executive !== '') {
            $lines[] = 'Summary';
            $lines[] = $executive;
            $lines[] = '';
        }

        $decisions = is_array($summary->decisions) ? array_filter($summary->decisions) : [];
        if ($decisions) {
            $lines[] = 'Decisions';
            foreach ($decisions as $decision) {
                $lines[] = '- '.trim((string) $decision);
            }
            $lines[] = '';
        }

        if ($items->isNotEmpty()) {
            $lines[] = 'Action items';
            foreach ($items as $item) {
                $owner = $item->owner_name ?: 'Unassigned';
                $due = $item->due_date
                    ? ' (due '.$item->due_date->format('j M Y').')'
                    : ($item->due_text ? ' ('.$item->due_text.')' : '');
                $lines[] = '- '.$owner.': '.$item->title.$due;
            }
            $lines[] = '';
        }

        $lines[] = 'Let me know if I have missed anything.';
        $lines[] = '';
        $lines[] = 'Best,';
        $lines[] = $senderName;

        return implode("\n", $lines);
    }

    private function authorizeView(Request $request, Meeting $meeting): void
    {
        abort_unless($request->user(), 401);
        abort_unless($meeting->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($request->user()->can('view', $meeting), 403);
    }

    /** Convenors and super admins can draft and send recaps. */
    private function canEdit(Request $request, Meeting $meeting): bool
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $meeting->participants()
            ->where('user_id', $user->id)
            ->where('participation_role', 'convenor')
            ->exists();
    }
}
