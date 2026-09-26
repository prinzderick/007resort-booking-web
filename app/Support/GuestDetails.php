<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The "Your details" card: name, email, phone (+ optional news tickbox). Not an
 * account: nothing here is a credential. Produces the `guest` object the API
 * expects: {name, email, phone(E.164), marketingConsent, consentVersion}.
 */
final class GuestDetails
{
    public const FIELDS = ['guest_name', 'guest_email', 'guest_phone'];

    /**
     * @return array{name: string, email: string, phone: string, marketingConsent: bool, consentVersion: string}
     */
    public static function validate(Request $request): array
    {
        $validator = self::validator($request->all());
        $validator->validate();

        $v = $validator->validated();

        return [
            'name' => trim(preg_replace('/\s+/', ' ', $v['guest_name']) ?? ''),
            'email' => strtolower(trim($v['guest_email'])),
            'phone' => (string) Phone::normalize($v['guest_phone']),
            'marketingConsent' => (bool) ($v['guest_marketing'] ?? false),
            'consentVersion' => (string) config('r007.checkout.consent_version'),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function validator(array $input): ValidatorContract
    {
        return Validator::make($input, [
            'guest_name' => ['required', 'string', 'min:2', 'max:120', 'regex:/\p{L}/u'],
            'guest_email' => ['required', 'email:rfc', 'max:190'],
            'guest_phone' => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (Phone::normalize((string) $value) === null) {
                    $fail('Enter a Nigerian mobile number like 0803 123 4567 or +234 803 123 4567.');
                }
            }],
            'guest_marketing' => ['nullable', 'boolean'],
        ], [
            'guest_name.required' => 'Please tell us your name.',
            'guest_name.min' => 'Please enter your full name.',
            'guest_name.regex' => 'Please enter your full name.',
            'guest_email.required' => 'Please enter your email so we can send your ticket.',
            'guest_email.email' => 'That email does not look right. Check for typos, for example name@gmail.com.',
            'guest_phone.required' => 'Please enter your phone number.',
        ]);
    }

    /** Profile of a signed-in customer, if it has everything we need (card can be skipped). */
    public static function completeProfile(?array $user): ?array
    {
        if (! $user || blank($user['name'] ?? null) || blank($user['email'] ?? null) || blank($user['phone'] ?? null)) {
            return null;
        }

        return ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']];
    }
}
