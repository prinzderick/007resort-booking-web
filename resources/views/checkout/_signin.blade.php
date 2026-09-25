{{-- Unobtrusive alternative for people who already have an account. The social sign-in agent (feat/social-signin) can drop its buttons into the stack below. --}}
<div class="co-signin">
    @if (! app(\App\Services\Online\CustomerService::class)->check())
        <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    @endif
    @stack('checkout-social')
</div>
