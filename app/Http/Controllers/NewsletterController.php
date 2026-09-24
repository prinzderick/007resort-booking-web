<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsRequestException;
use App\Services\Cms\CmsUnavailableException;
use Illuminate\Http\Request;

/**
 * Newsletter with double opt-in. The visitor's email is only ever forwarded to the CMS API; nothing is stored here.
 * The API always answers CHECK_EMAIL for a valid request (existence is never leaked), so the copy here does not either.
 */
class NewsletterController extends Controller
{
    public function __construct(private readonly CmsClient $cms) {}

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'source' => ['nullable', 'in:footer,blog,event,popup,checkout'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);
        $ok = 'Almost done: check your inbox and tap the link to confirm.';

        try {
            $this->cms->subscribe($data['email'], null, $data['source'] ?? 'footer', true, 'I agree to receive emails from 007 Resort & Spa.', (string) ($data['website'] ?? ''), $request->ip());
        } catch (CmsRequestException $e) {
            $msg = $e->status === 429 ? 'Too many attempts. Please try again a little later.' : 'Please enter a valid email address.';

            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $msg], 422) : back()->with('subscribe_status', $msg);
        } catch (CmsUnavailableException) {
            $msg = 'We could not sign you up right now. Please try again in a few minutes.';

            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $msg], 503) : back()->with('subscribe_status', $msg);
        }

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $ok]) : back()->with('subscribe_status', $ok)->with('status', $ok);
    }

    public function confirmPage(Request $request)
    {
        return $this->tokenPage($request, 'confirm');
    }

    public function confirm(Request $request)
    {
        return $this->act($request, 'confirm', fn (string $t) => $this->cms->confirmSubscription($t));
    }

    public function unsubscribePage(Request $request)
    {
        return $this->tokenPage($request, 'unsubscribe');
    }

    public function unsubscribe(Request $request)
    {
        return $this->act($request, 'unsubscribe', fn (string $t) => $this->cms->unsubscribe($t));
    }

    /** GET only previews (mail scanners prefetch links); the side effect needs the visitor's click (POST). */
    private function tokenPage(Request $request, string $kind)
    {
        $token = (string) $request->query('token', '');
        $state = 'ready';
        if (! preg_match('/^[A-Za-z0-9._~+\/=-]{8,600}$/', $token)) {
            $state = 'invalid';
        } elseif ($kind === 'confirm') {
            try {
                $p = $this->cms->previewConfirmation($token);
                $state = ($p['status'] ?? '') === 'CONFIRMED' ? 'done' : 'ready';
            } catch (CmsRequestException $e) {
                $state = $e->status === 410 ? 'expired' : 'invalid';
            } catch (CmsUnavailableException) {
                $state = 'unavailable';
            }
        }

        return view('newsletter.'.$kind, ['token' => $token, 'state' => $state]);
    }

    private function act(Request $request, string $kind, callable $call)
    {
        $token = (string) $request->input('token', '');
        $state = 'done';
        try {
            $call($token);
        } catch (CmsRequestException $e) {
            $state = $e->status === 410 ? 'expired' : ($e->status === 429 ? 'limited' : 'invalid');
        } catch (CmsUnavailableException) {
            $state = 'unavailable';
        }

        return view('newsletter.'.$kind, ['token' => $token, 'state' => $state]);
    }
}
