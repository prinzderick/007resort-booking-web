<?php

namespace App\Http\Controllers;

use App\Services\Social\SocialGateway;
use App\Services\Social\SocialProviders;
use Illuminate\Http\Request;

/**
 * Local stand-in for the Google/Facebook consent screen (SOCIAL_FAKE=true). Never available in production
 * (SocialProviders::fakeEnabled() is false whenever APP_ENV=production, whatever the env says).
 */
class FakeProviderController extends Controller
{
    public function authorize(Request $request, string $provider, SocialProviders $providers)
    {
        abort_unless($providers->fakeEnabled() && $providers->known($provider), 404);

        $state = (string) $request->query('state');

        return view('auth.social.fake', [
            'provider' => $providers->label($provider),
            'key' => $provider,
            'state' => $state,
            'personas' => SocialGateway::PERSONAS,
        ]);
    }
}
