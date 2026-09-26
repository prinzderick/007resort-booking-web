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
            $e->is('profile_incomplete') => 'Please add and verify your email address in your account before paying.',
            $e->is('terms_not_accepted') => 'Please accept the terms to create your account.',
            $e->is('identity_conflict') => 'You already have a different account connected for that provider. Disconnect it first, or use it to sign in.',
            $e->is('identity_already_linked') => 'That account is already connected to a different 007 Resort & Spa account.',
            $e->is('last_login_method') => 'You need at least one way to sign in. Add a password or connect another provider first.',
            $e->is('invalid_link_confirmation') => 'That code is wrong or has expired. Check the latest email we sent, or request a new code.',
            $e->is('invalid_verification') => 'That code is wrong or has expired.',
            $e->is('email_in_use') => 'That email already belongs to another account. Sign in to that account and connect the provider there instead.',
            $e->is('email_not_verified') => 'Verify your email address first, then you can set a password.',
            $e->is('invalid_current_password') => 'Your current password is not right.',
            $e->is('provider_disabled') => 'Sign-in with that provider is not available right now.',
            $e->is('profile_insufficient') => 'That provider did not share enough information (a name or an email) to create your account.',
            $e->status === 423 => 'This account is temporarily locked. Please try again later.',
            $e->is('ticket_used') => 'This ticket has already been used.',
            $e->is('idempotency_key_reused') => 'That request was already submitted. Please refresh and try again.',
            $e->isUnavailable() => 'This part of the site is temporarily unavailable. Please try again shortly or contact reception.',
            $e->status === 422 && $e->detail !== null => $e->detail,
            $e->status >= 400 && $e->status < 500 && $e->detail !== null => $e->detail,
            default => 'Something went wrong on our side. Please try again.',
        };
    }
}
