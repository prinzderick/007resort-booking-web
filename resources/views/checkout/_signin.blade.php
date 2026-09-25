{{-- Unobtrusive alternative for people who already have an account. Social buttons only render when a provider is enabled (feat/social-signin). --}}
@if (! app(\App\Services\Online\CustomerService::class)->check())
    <div class="co-signin">
        <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        <x-social-buttons from="login" :return-to="request()->getRequestUri()" label="Sign in with %s" :divider="false" />
        @stack('checkout-social')
    </div>
@endif
