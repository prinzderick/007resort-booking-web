<?php

namespace App\Support;

use App\Services\R007Api\R007ApiException;

/** Customer-friendly wording for API problem codes. */
final class ApiProblem
{
    public static function message(R007ApiException $e): string
    {
        return match (true) {
            $e->is('slot_unavailable') => 'Sorry, that slot has just been taken by someone else. Please pick another time.',
            $e->is('hold_expired') => 'Your hold on this slot has expired. Please choose a slot again.',
            $e->is('amount_mismatch', 'balance_changed') => 'The price has changed since you started. Please review and try again.',
            $e->is('rate_limited') || $e->status === 429 => 'You are doing that too quickly. Please wait a moment and try again.',
            $e->is('invalid_credentials') => 'Those details do not match an account.',
            $e->is('account_locked') => 'This account is temporarily locked. Please try again later.',
            $e->is('ticket_used') => 'This ticket has already been used.',
            $e->is('idempotency_key_reused') => 'That request was already submitted. Please refresh and try again.',
            $e->isUnavailable() => 'This part of the site is temporarily unavailable. Please try again shortly or contact reception.',
            $e->status === 422 && $e->detail !== null => $e->detail,
            $e->status >= 400 && $e->status < 500 && $e->detail !== null => $e->detail,
            default => 'Something went wrong on our side. Please try again.',
        };
    }
}
