@php
    $items = $signin['items'];
    $connected = array_column($items, 'provider');
    $offer = array_values(array_diff(array_column($socialProviders, 'key'), $connected));
    $pwLabel = $signin['hasPassword'] ? 'Change password' : 'Set a password';
    $label = fn (string $p) => config("social.providers.$p.label", ucfirst($p));
    $avatar = collect($items)->pluck('avatarUrl')->filter()->first();
@endphp
<section id="sign-in" style="margin-top:44px" aria-labelledby="si-h">
    <div class="sec-head" style="margin-bottom:6px"><h2 id="si-h" class="h-2">Sign-in methods</h2></div>
    <p style="color:var(--mute);margin-bottom:16px">The ways you can get into this account. Keep at least one.</p>
    <ul class="stack plain" role="list">
        <li class="row-item method">
            <div class="method__who">
                <span class="method__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
                <div><p style="font-weight:600">Email and password</p><p class="method__sub">{{ $signin['hasPassword'] ? ($user['email'] ?? 'Password set') : 'No password set yet' }}</p></div>
            </div>
            <a class="btn btn--line btn--sm" href="#set-password" data-open="set-password">{{ $pwLabel }}</a>
        </li>
        @foreach ($items as $i)
            <li class="row-item method">
                <div class="method__who">
                    <x-avatar :url="$i['avatarUrl'] ?? null" :name="$user['name'] ?? $label($i['provider'])" :size="44" />
                    <div>
                        <p style="font-weight:600">{{ $label($i['provider']) }} <span class="status status--ok">Connected</span></p>
                        <p class="method__sub">{{ $i['email'] ?? 'No email shared' }}@if (! empty($i['linkedAt'])) &middot; since {{ \App\Support\Lagos::parse($i['linkedAt'])->format('j M Y') }}@endif</p>
                    </div>
                </div>
                @if ($signin['canUnlink'])
                    <form method="POST" action="{{ route('account.signin.disconnect', $i['id']) }}" data-once data-confirm="Disconnect {{ $label($i['provider']) }}? You will no longer be able to sign in with it.">
                        @csrf<button type="submit" class="btn btn--line btn--sm">Disconnect</button>
                    </form>
                @else
                    <button type="button" class="btn btn--line btn--sm" disabled aria-describedby="last-method">Disconnect</button>
                @endif
            </li>
        @endforeach
    </ul>
    @if (! $signin['canUnlink'] && $items !== [])
        <p id="last-method" class="hint" style="font-size:13.5px;color:var(--mute);margin-top:10px">This is your only way to sign in, so it cannot be disconnected. {{ $signin['hasPassword'] ? 'Verify your email or connect another provider first.' : 'Set a password or connect another provider first.' }}</p>
    @endif
    @if ($offer !== [])
        <div class="connect">
            <p class="connect__h">Connect another way to sign in</p>
            <x-social-buttons intent="connect" from="account" :only="$offer" :divider="false" label="Connect %s" />
        </div>
    @endif
    <details id="set-password" class="panel panel--line pw" @if ($errors->has('password') || $errors->has('current_password')) open @endif style="margin-top:16px">
        <summary>{{ $pwLabel }}</summary>
        <form method="POST" action="{{ route('account.signin.password') }}" class="stack" data-once style="margin-top:14px">
            @csrf
            @if ($signin['hasPassword'])<x-field name="current_password" label="Current password" type="password" autocomplete="current-password" />@endif
            <x-field name="password" label="New password" type="password" autocomplete="new-password" hint="At least 10 characters." />
            <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" />
            <button type="submit" class="btn btn--sm" style="justify-self:start" data-busy="Saving…">Save password</button>
        </form>
    </details>
</section>
