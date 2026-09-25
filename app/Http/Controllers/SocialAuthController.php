<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Online\CustomerService;
use App\Services\R007Api\R007ApiException;
use App\Services\Social\SocialAuthApi;
use App\Services\Social\SocialAuthException;
use App\Services\Social\SocialFlow;
use App\Services\Social\SocialGateway;
use App\Services\Social\SocialIdentity;
use App\Services\Social\SocialProviders;
use App\Support\ApiProblem;
use App\Support\SafeReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sign up / sign in with a social provider.
 *
 *   /auth/{provider}/redirect  -> provider (state [+PKCE]; return_to must be a same-site relative path)
 *   /auth/{provider}/callback  -> profile read once -> API social login -> (consent | link code | complete profile | done)
 *
 * Logging is limited to provider + a stable outcome code. Never emails, names, provider ids or tokens.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialProviders $providers,
        private readonly SocialGateway $gateway,
        private readonly SocialAuthApi $api,
        private readonly CustomerService $customers,
        private readonly SocialFlow $flow,
        private readonly CmsClient $cms,
    ) {}

    public function redirect(Request $request, string $provider)
    {
        abort_unless($this->providers->known($provider), 404);

        $origin = $request->query('from') === 'register' ? 'register' : 'login';
        $intent = $request->query('intent') === 'connect' && $this->customers->check() ? 'connect' : 'signin';

        if (! $this->providers->isAvailable($provider)) {
            return $this->fail(['origin' => $intent === 'connect' ? 'account' : $origin], SocialAuthException::unavailable()->getMessage());
        }

        $returnTo = SafeReturn::path($request->query('return_to'))
            ?? SafeReturn::fromIntended($request->session()->get('url.intended'), $request->getHost());

        $this->flow->put(SocialFlow::FLOW, ['intent' => $intent, 'origin' => $intent === 'connect' ? 'account' : $origin, 'return_to' => $returnTo]);

        try {
            return $this->gateway->redirect($provider, $request);
        } catch (\Throwable) {
            Log::warning('social redirect failed', ['provider' => $provider]);

            return $this->fail(['origin' => $origin], SocialAuthException::providerError()->getMessage());
        }
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless($this->providers->known($provider), 404);
        $flow = $this->flow->pullFlow();
        $flow = ($flow['expires'] ?? 0) >= time() ? $flow : [];

        if (! $this->providers->isAvailable($provider)) {
            return $this->fail($flow, SocialAuthException::unavailable()->getMessage());
        }

        try {
            $identity = $this->gateway->identity($provider, $request);
        } catch (SocialAuthException $e) {
            Log::info('social sign-in not completed', ['provider' => $provider, 'reason' => $e->reason]);

            return $this->fail($flow, $e->getMessage());
        }

        if (($flow['intent'] ?? 'signin') === 'connect') {
            return $this->connect($identity, $flow);
        }
        if ($flow === []) {
            // A valid callback we never started (or the flow expired): refuse rather than guess where to send the person.
            Log::info('social sign-in not completed', ['provider' => $provider, 'reason' => 'no_flow']);

            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }

        return $this->attempt($request, $identity, $flow, terms: false, marketing: false);
    }

    // ---- consent (first-time sign-up) ----------------------------------------------------------------------

    public function consent()
    {
        $p = $this->flow->get(SocialFlow::PENDING);
        if (! $p) {
            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }
        $i = SocialIdentity::fromArray($p['identity']);

        return view('auth.social.consent', ['provider' => $this->providers->label($i->provider), 'identity' => $i]);
    }

    public function consentStore(Request $request)
    {
        $p = $this->flow->get(SocialFlow::PENDING);
        if (! $p) {
            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }
        $data = $request->validate(['terms' => ['accepted'], 'marketing' => ['nullable', 'boolean']], ['terms.accepted' => 'Please accept the terms and privacy policy to create your account.']);

        return $this->attempt($request, SocialIdentity::fromArray($p['identity']), (array) ($p['flow'] ?? []), terms: true, marketing: (bool) ($data['marketing'] ?? false));
    }

    public function cancel()
    {
        $flow = (array) ($this->flow->get(SocialFlow::PENDING)['flow'] ?? $this->flow->get(SocialFlow::LINK)['flow'] ?? []);
        $this->flow->forget(SocialFlow::PENDING, SocialFlow::LINK);

        return redirect()->route(($flow['origin'] ?? 'login') === 'register' ? 'register' : 'login')->with('notice', 'No problem, nothing was created. You can sign in another way any time.');
    }

    // ---- account link confirmation (email code) ---------------------------------------------------------------

    public function link()
    {
        $l = $this->flow->get(SocialFlow::LINK);
        if (! $l) {
            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }

        return view('auth.social.link', ['provider' => $this->providers->label($l['identity']['provider']), 'masked' => $l['masked'], 'minutes' => $l['minutes']]);
    }

    public function linkStore(Request $request)
    {
        $l = $this->flow->get(SocialFlow::LINK);
        if (! $l) {
            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[A-Za-z0-9 ]{4,12}$/']]);

        try {
            $res = $this->api->confirmLink((string) $l['email'], str_replace(' ', '', $data['code']));
        } catch (R007ApiException $e) {
            Log::info('social link confirmation failed', ['provider' => $l['identity']['provider'], 'status' => $e->status, 'code' => $e->code()]);

            return back()->withErrors(['code' => ApiProblem::message($e)]);
        }

        return $this->finish($res, SocialIdentity::fromArray($l['identity']), (array) $l['flow'], marketing: false, linked: true);
    }

    public function linkResend(Request $request)
    {
        $l = $this->flow->get(SocialFlow::LINK);
        if (! $l) {
            return $this->fail([], SocialAuthException::noIdentity()->getMessage());
        }

        // Re-running the same login makes the API email a fresh code (it answers 409 again).
        $r = $this->attempt($request, SocialIdentity::fromArray($l['identity']), (array) $l['flow'], terms: (bool) ($l['terms'] ?? false), marketing: false);

        return $r->getTargetUrl() === route('social.link') ? $r->with('status', 'We sent a new code.') : $r;
    }

    // ---- internals --------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $flow
     */
    private function attempt(Request $request, SocialIdentity $identity, array $flow, bool $terms, bool $marketing): RedirectResponse
    {
        try {
            $res = $this->api->login($identity, [
                'termsAccepted' => $terms,
                'marketingConsent' => $marketing,
                'clientIp' => $request->ip(),
                'userAgent' => $request->userAgent(),
            ]);
        } catch (R007ApiException $e) {
            Log::info('social login refused', ['provider' => $identity->provider, 'status' => $e->status, 'code' => $e->code()]);

            if ($e->is('terms_not_accepted')) {
                $this->flow->put(SocialFlow::PENDING, ['identity' => $identity->toArray(), 'flow' => $flow]);

                return redirect()->route('social.consent');
            }
            if ($e->is('account_link_requires_confirmation')) {
                $c = (array) (($e->extensions['meta']['confirmation'] ?? null) ?: []);
                $this->flow->put(SocialFlow::LINK, [
                    'identity' => $identity->toArray(), 'flow' => $flow, 'email' => $identity->email, 'terms' => $terms,
                    'masked' => (string) ($c['maskedEmail'] ?? SocialFlow::maskEmail($identity->email)),
                    'minutes' => max(1, (int) round(((int) ($c['expiresInSeconds'] ?? 1800)) / 60)),
                ]);

                return redirect()->route('social.link');
            }
            if ($e->isUnavailable() || $e->status === 0) {
                return $this->fail($flow, SocialAuthException::unavailable()->getMessage());
            }

            return $this->fail($flow, ApiProblem::message($e));
        }

        return $this->finish($res, $identity, $flow, $marketing, linked: false);
    }

    /**
     * @param  array<string, mixed>  $res  API session payload
     * @param  array<string, mixed>  $flow
     */
    private function finish(array $res, SocialIdentity $identity, array $flow, bool $marketing, bool $linked): RedirectResponse
    {
        if (! is_string($res['accessToken'] ?? null) || $res['accessToken'] === '') {
            return $this->fail($flow, SocialAuthException::providerError()->getMessage());
        }

        // Same storage + session-id rotation as a password login (anti session-fixation).
        $this->customers->signInFromApi($res);
        $this->flow->forget(SocialFlow::PENDING, SocialFlow::LINK);

        $customer = (array) ($res['customer'] ?? []);
        $isNew = (bool) ($res['isNewCustomer'] ?? false);
        $missing = array_values((array) ($res['missing'] ?? []));
        $recommended = array_values((array) ($res['recommended'] ?? []));
        $returnTo = SafeReturn::path($flow['return_to'] ?? null);
        $request = request();
        $request->session()->forget('url.intended');

        $name = $customer['name'] ?? $identity->name;
        if ($marketing && ! empty($customer['email']) && ($customer['emailVerified'] ?? false) === true) {
            $this->subscribe((string) $customer['email'], $name, $request->ip());
            $marketing = false;
        }

        Log::info('social sign-in ok', ['provider' => $identity->provider, 'new' => $isNew, 'linked' => $linked, 'missing' => $missing]);

        $needsPhone = $isNew && empty($customer['phone']) && in_array('phone', $recommended, true);
        if ($missing !== [] || $needsPhone) {
            $this->flow->put(SocialFlow::COMPLETE, [
                'missing' => $missing, 'phone' => $needsPhone, 'emailSuggestion' => $res['emailSuggestion'] ?? null, 'provider' => $identity->provider,
                'return_to' => $returnTo, 'marketing' => $marketing, 'stage' => 'details', 'pendingEmail' => null,
            ]);

            return redirect()->route('social.complete');
        }

        $msg = $linked ? 'Connected. Your '.$this->providers->label($identity->provider).' account is now linked to your 007 Resort & Spa account.'
            : ($isNew ? 'Welcome to 007 Resort & Spa!' : 'Welcome back!');

        return redirect()->to($returnTo ?? route('account'))->with('status', $msg);
    }

    /** @param array<string, mixed> $flow */
    private function connect(SocialIdentity $identity, array $flow): RedirectResponse
    {
        $back = redirect()->route('account')->withFragment('sign-in');
        $token = request()->session()->get(config('r007.api.session_token_key'));
        if (! $this->customers->check() || ! is_string($token)) {
            return $this->fail(['origin' => 'login'], 'Please sign in first, then connect the provider from your account.');
        }
        try {
            $this->api->link($identity, $token);
        } catch (R007ApiException $e) {
            Log::info('social connect refused', ['provider' => $identity->provider, 'status' => $e->status, 'code' => $e->code()]);
            if ($e->status === 401) {
                throw $e;
            }

            return $back->with('error', ApiProblem::message($e));
        }

        return $back->with('status', $this->providers->label($identity->provider).' is now connected. You can use it to sign in.');
    }

    /** @param array<string, mixed> $flow */
    private function fail(array $flow, string $message): RedirectResponse
    {
        $target = match ($flow['origin'] ?? 'login') {
            'register' => redirect()->route('register'),
            'account' => $this->customers->check() ? redirect()->route('account')->withFragment('sign-in') : redirect()->route('login'),
            default => redirect()->route('login'),
        };

        return $target->with('error', $message);
    }

    private function subscribe(string $email, ?string $name, ?string $ip): void
    {
        self::subscribeLater($email, $name, $ip);
    }

    /** Used by the profile-completion controller once an email has been verified. */
    public static function subscribeLater(string $email, ?string $name, ?string $ip): void
    {
        try {
            app(CmsClient::class)->subscribe($email, $name, 'social-signup', true, 'I agree to receive emails from 007 Resort & Spa.', '', $ip);
        } catch (\Throwable) {
            Log::info('social newsletter subscribe failed');
        }
    }
}
