<?php

namespace App\Http\Controllers;

use App\Services\R007Api\R007ApiException;
use App\Services\Social\SocialAuthApi;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/** Account > Sign-in methods: disconnect a provider, set or change the password. (Connecting starts at /auth/{p}/redirect?intent=connect.) */
class SignInMethodsController extends Controller
{
    public function __construct(private readonly SocialAuthApi $api) {}

    public function disconnect(Request $request, string $identityId)
    {
        abort_unless(preg_match('/^[0-9a-fA-F-]{32,40}$/', $identityId) === 1, 404);
        try {
            $this->api->unlink($identityId);
        } catch (R007ApiException $e) {
            Log::info('social disconnect refused', ['status' => $e->status, 'code' => $e->code()]);
            if ($e->status === 404) {
                abort(404);
            }

            return redirect()->route('account')->withFragment('sign-in')->with('error', ApiProblem::message($e));
        }

        return redirect()->route('account')->withFragment('sign-in')->with('status', 'Disconnected. You can connect it again any time.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        try {
            $this->api->setPassword($data['password'], filled($data['current_password'] ?? null) ? $data['current_password'] : null);
        } catch (R007ApiException $e) {
            return redirect()->route('account')->withFragment('sign-in')->with('error', ApiProblem::message($e));
        }

        return redirect()->route('account')->withFragment('sign-in')->with('status', 'Your password has been saved.');
    }
}
