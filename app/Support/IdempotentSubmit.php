<?php

namespace App\Support;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Double-click / double-submit safety for mutating forms.
 *
 * Every form carries a hidden `_submission` UUID generated at render time. It
 * is (a) forwarded to the API as the Idempotency-Key so a replay returns the
 * original result, and (b) guarded here with a short lock so two concurrent
 * submits of the same form cannot both reach the API, and a completed submit
 * replays its redirect instead of doing the work again.
 */
final class IdempotentSubmit
{
    /**
     * @param  Closure(string $idempotencyKey): RedirectResponse  $work
     */
    public static function run(Request $request, string $scope, Closure $work): RedirectResponse
    {
        $id = (string) $request->input('_submission');
        if (! Str::isUuid($id)) {
            return back()->withErrors(['form' => 'Your form expired. Please try again.']);
        }

        $key = 'idem:'.sha1($request->session()->token().'|'.$scope.'|'.$id);

        if (is_string($done = Cache::get($key.':done'))) {
            return redirect()->to($done);
        }

        $lock = Cache::lock($key.':lock', 30);
        if (! $lock->get()) {
            return back()->with('notice', 'Your request is already being processed. Please wait a moment.');
        }

        try {
            $response = $work($scope.':'.$id);
            $session = $response->getSession();
            if ($response->getStatusCode() === 302 && ! $session?->has('errors') && ! $session?->has('error')) {
                Cache::put($key.':done', $response->getTargetUrl(), 600);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
