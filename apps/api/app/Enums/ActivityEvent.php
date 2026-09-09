<?php

namespace App\Enums;

enum ActivityEvent: string
{
    case AuthRegistered = 'auth.registered';
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLogout = 'auth.logout';
    case AuthPasswordChanged = 'auth.password_changed';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationStopped = 'impersonation.stopped';
    case InvitationSent = 'invitation.sent';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationDeclined = 'invitation.declined';
    case WebhookReceived = 'webhook.received';

    public function logName(): string
    {
        return match ($this) {
            self::AuthRegistered,
            self::AuthLogin,
            self::AuthLoginFailed,
            self::AuthLogout,
            self::AuthPasswordChanged => 'auth',
            self::ImpersonationStarted,
            self::ImpersonationStopped => 'users',
            self::InvitationSent,
            self::InvitationAccepted,
            self::InvitationDeclined => 'meetings',
            self::WebhookReceived => 'integrations',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AuthRegistered => 'Registered an account',
            self::AuthLogin => 'Signed in',
            self::AuthLoginFailed => 'Failed sign-in',
            self::AuthLogout => 'Signed out',
            self::AuthPasswordChanged => 'Changed password',
            self::ImpersonationStarted => 'Started impersonation',
            self::ImpersonationStopped => 'Stopped impersonation',
            self::InvitationSent => 'Sent a meeting invitation',
            self::InvitationAccepted => 'Accepted a meeting invitation',
            self::InvitationDeclined => 'Declined a meeting invitation',
            self::WebhookReceived => 'Received a provider webhook',
        };
    }
}
