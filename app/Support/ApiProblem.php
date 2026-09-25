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
            $e->is('too_many_active_holds') => 'You already have several slots on hold. Please finish paying for them, or try again in a few minutes.',
            $e->is('guest_checkout_disabled') => 'Online checkout is paused right now. Please call or visit reception.',
            $e->is('order_not_found') => 'We could not find an order with those details.',
            $e->is('order_token_invalid', 'order_token_expired') => 'That link is no longer valid. Use Find my booking with your reference and email.',
            $e->is('ticket_used') => 'This ticket has already been used.',
            $e->is('idempotency_key_reused') => 'That request was already submitted. Please refresh and try again.',
            $e->isUnavailable() => 'This part of the site is temporarily unavailable. Please try again shortly or contact reception.',
            $e->status === 422 && $e->detail !== null => $e->detail,
            $e->status >= 400 && $e->status < 500 && $e->detail !== null => $e->detail,
            default => 'Something went wrong on our side. Please try again.',
        };
    }
}
