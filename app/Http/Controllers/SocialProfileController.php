<?php

namespace App\Http\Controllers;

use App\Services\Online\CustomerService;
use App\Services\R007Api\R007ApiException;
use App\Services\Social\SocialAuthApi;
use App\Services\Social\SocialFlow;
use App\Support\ApiProblem;
use App\Support\NigerianPhone;
use App\Support\SafeReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * After a social sign-up: fill what the provider did not give us (email, name; phone is a soft ask), and verify a
 * newly added email with the 6-digit code the API sends. Everything is skippable except that a customer without
 * a verified email cannot pay (the API refuses with profile_incomplete).
 */
class SocialProfileController extends Controller
{
    public function __construct(
        private readonly SocialAuthApi $api,
        private readonly CustomerService $customers,
        private readonly SocialFlow $flow,
    ) {}

    public function show(Request $request)
    {
        $c = $this->state();
        if (! $c) {
            return redirect()->route('account');
        }
        if ($request->boolean('change') && $c['stage'] === 'code') {
            $c['stage'] = 'details';
            $this->flow->put(SocialFlow::COMPLETE, $c);
        }
        $user = (array) $this->customers->user();

        return view('auth.social.complete', [
            'c' => $c,
            'user' => $user,
            'needName' => in_array('name', $c['missing'], true),
            'needEmail' => in_array('email', $c['missing'], true) || empty($user['email']),
            'stage' => $c['stage'],
        ]);
    }

    public function store(Request $request)
    {
        $c = $this->state();
        if (! $c) {
            return redirect()->route('account');
        }
        $needName = in_array('name', $c['missing'], true);
        $needEmail = in_array('email', $c['missing'], true) || empty(((array) $this->customers->user())['email']);

        $rules = [
            'name' => [$needName ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$needEmail ? 'required' : 'nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', function ($attr, $value, $fail) {
                if (filled($value) && NigerianPhone::normalize($value) === null) {
                    $fail('Enter a Nigerian mobile number, for example 0803 123 4567 or +234 803 123 4567.');
                }
            }],
        ];
        $data = $request->validate($rules);

        $fields = array_filter([
            'name' => $needName ? trim((string) ($data['name'] ?? '')) : null,
            'phone' => filled($data['phone'] ?? null) ? NigerianPhone::normalize($data['phone']) : null,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            if ($fields !== []) {
                $this->api->updateProfile($fields);
            }
            if (filled($data['email'] ?? null)) {
                $this->api->requestEmail((string) $data['email']);
            }
        } catch (R007ApiException $e) {
            return back()->withInput()->withErrors($this->errorsFor($e, $fields));
        }
        $this->customers->refresh();

        if (filled($data['email'] ?? null)) {
            $c['stage'] = 'code';
            $c['pendingEmail'] = (string) $data['email'];
            $c['missing'] = array_values(array_diff($c['missing'], ['name']));
            $this->flow->put(SocialFlow::COMPLETE, $c);

            return redirect()->route('social.complete')->with('status', 'We sent a 6-digit code to '.SocialFlow::maskEmail($data['email']).'.');
        }

        return $this->done($c, 'Thanks, your profile is up to date.');
    }

    public function verify(Request $request)
    {
        $c = $this->state();
        if (! $c || $c['stage'] !== 'code') {
            return redirect()->route('account');
        }
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[A-Za-z0-9 ]{4,12}$/']]);

        try {
            $user = $this->api->verifyEmail(str_replace(' ', '', $data['code']));
        } catch (R007ApiException $e) {
            return back()->withErrors(['code' => ApiProblem::message($e)]);
        }
        $this->customers->refresh();

        if ($c['marketing'] && ! empty($user['email'])) {
            SocialAuthController::subscribeLater((string) $user['email'], $user['name'] ?? null, $request->ip());
        }

        return $this->done($c, 'Your email is verified. You are all set!');
    }

    public function resend()
    {
        $c = $this->state();
        if (! $c || $c['stage'] !== 'code' || empty($c['pendingEmail'])) {
            return redirect()->route('account');
        }
        try {
            $this->api->requestEmail((string) $c['pendingEmail']);
        } catch (R007ApiException $e) {
            return back()->withErrors(['code' => $e->status === 429 ? 'Please wait a minute before asking for another code.' : ApiProblem::message($e)]);
        }

        return back()->with('status', 'We sent a new code.');
    }

    public function skip()
    {
        $c = $this->state();
        $this->flow->forget(SocialFlow::COMPLETE);

        return redirect()->to(SafeReturn::path($c['return_to'] ?? null) ?? route('account'))
            ->with('notice', 'You can finish your profile any time from your account. You need a verified email before you can pay.');
    }

    /** @return array<string, mixed>|null */
    private function state(): ?array
    {
        $c = $this->flow->get(SocialFlow::COMPLETE);
        if ($c === null && empty(((array) $this->customers->user())['email'])) {
            // Signed in without an email (e.g. skipped earlier): let them come back from the account page.
            $c = ['missing' => ['email'], 'phone' => false, 'emailSuggestion' => null, 'provider' => null, 'return_to' => null, 'marketing' => false, 'stage' => 'details', 'pendingEmail' => null];
            $this->flow->put(SocialFlow::COMPLETE, $c);
        }

        return $c;
    }

    /** @param array<string, mixed> $c */
    private function done(array $c, string $message)
    {
        $this->flow->forget(SocialFlow::COMPLETE);

        return redirect()->to(SafeReturn::path($c['return_to'] ?? null) ?? route('account'))->with('status', $message);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function errorsFor(R007ApiException $e, array $fields): array
    {
        Log::info('social profile update refused', ['status' => $e->status, 'code' => $e->code()]);
        $out = [];
        foreach ($e->errors() as $field => $messages) {
            $out[$field] = is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages;
        }
        if ($out === []) {
            $out[$e->is('email_in_use') ? 'email' : 'form'] = ApiProblem::message($e);
        }

        return $out;
    }
}
