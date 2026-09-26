# Social sign-in set-up runbook (owner)

Goal: let customers use **Continue with Google** and **Continue with Facebook** on https://007resorts.com. Until you finish the
steps below the buttons simply do not appear (email + password keeps working). Nothing here costs money.

You will create two sets of credentials, put them in the website's environment, and switch the providers on in the API.

## 0. URLs you will need

| What | Production | Local development |
| --- | --- | --- |
| Website home page | `https://007resorts.com` | `http://localhost:8163` |
| **Google** authorized redirect URI | `https://007resorts.com/auth/google/callback` | `http://localhost:8163/auth/google/callback` |
| **Facebook** valid OAuth redirect URI | `https://007resorts.com/auth/facebook/callback` | `http://localhost:8163/auth/facebook/callback` |
| Privacy Policy URL (both consoles ask) | `https://007resorts.com/privacy` | |
| Terms of Service URL | `https://007resorts.com/terms` | |
| Data deletion instructions URL (Facebook) | `https://007resorts.com/privacy` (make sure the page says how to ask for deletion, e.g. email hello@007resorts.com) | |
| Authorized JavaScript origins (Google, not required by our flow but harmless) | `https://007resorts.com` | `http://localhost:8163` |

The website builds these callback URLs from its `APP_URL` setting, so `APP_URL` in the production `.env` **must be exactly
`https://007resorts.com`** (no trailing slash, https). If you use `www.` or another domain, register that domain's URLs instead.
The URLs must match character for character, otherwise the provider shows a "redirect_uri_mismatch" error.

## 1. Google (Google Cloud Console)

1. Go to https://console.cloud.google.com and sign in with the Google account that should own this. Create a project called
   `007 Resort & Spa` (top bar, project picker, New project).
2. **OAuth consent screen** (Menu, APIs & Services, OAuth consent screen; newer consoles call it *Google Auth Platform*):
   * User type: **External**.
   * App name: `007 Resort & Spa`. User support email and developer contact email: a mailbox you monitor.
   * App logo (optional, 120x120 px). App home page: `https://007resorts.com`. Privacy policy: `https://007resorts.com/privacy`.
     Terms of service: `https://007resorts.com/terms`.
   * Authorized domains: `007resorts.com`.
   * Scopes: add only `.../auth/userinfo.email`, `.../auth/userinfo.profile` and `openid` (all "non-sensitive": no Google review needed).
3. **Publishing status**: the app starts in *Testing* (only listed test users can sign in). Press **Publish app** (Audience, Publish app,
   status *In production*). Because we only use the non-sensitive scopes above, it goes live without a verification review;
   Google may still ask you to verify domain ownership of `007resorts.com` in Search Console. While in Testing, add your own
   Gmail addresses under *Test users* to try it.
4. **Credentials**: APIs & Services, Credentials, Create credentials, **OAuth client ID**:
   * Application type: **Web application**, name `007resorts-website`.
   * Authorized redirect URIs: add `https://007resorts.com/auth/google/callback` (and `http://localhost:8163/auth/google/callback` for local testing).
   * Create, then copy the **Client ID** and **Client secret**.
5. Put them in the website environment (never in git, never in chat):
   `SOCIAL_GOOGLE_CLIENT_ID=...` and `SOCIAL_GOOGLE_CLIENT_SECRET=...`.
   Also give the **same Client ID** to the API (`SOCIAL_GOOGLE_CLIENT_ID` in the API's environment) so it can verify Google ID tokens.

## 2. Facebook (Meta for Developers)

1. Go to https://developers.facebook.com, log in with the Facebook account of the business owner, and *Get Started* (verify email/phone if asked).
2. **My Apps, Create app**. Use case: **Authenticate and request data from users with Facebook Login**. App name `007 Resort & Spa`,
   contact email, and (recommended) connect your Meta Business portfolio.
3. In the app dashboard add the product **Facebook Login for Business** or **Facebook Login** (the classic "Facebook Login" product if offered) and choose **Web**.
   Site URL: `https://007resorts.com`.
4. Facebook Login, **Settings**:
   * **Valid OAuth Redirect URIs**: `https://007resorts.com/auth/facebook/callback` (and the localhost one for testing).
   * Client OAuth Login: **On**. Web OAuth Login: **On**. Enforce HTTPS: **On**. *Use Strict Mode for Redirect URIs*: **On**.
5. **App settings, Basic**: App domains `007resorts.com`; Privacy Policy URL `https://007resorts.com/privacy`; Terms of Service URL
   `https://007resorts.com/terms`; User data deletion: the privacy URL (or an email address); App icon (1024x1024); Category (Business and pages / Sports).
   Copy the **App ID** and **App Secret** (press Show).
6. **Permissions**: the site asks for `email` and `public_profile` only. These two are available by default ("Standard access"), so
   no App Review is needed for them. (Do not request anything else.)
7. **Go live**: top bar switch **App mode: Development, Live** (needs the privacy policy URL and a category first). In Development mode
   only people with a role on the app (Roles) can sign in, which is handy for testing before switching to Live.
8. Environment: `SOCIAL_FACEBOOK_CLIENT_ID=<App ID>` and `SOCIAL_FACEBOOK_CLIENT_SECRET=<App Secret>`.

Note: Facebook does not tell us whether a person's email is verified, so the platform treats Facebook emails as unverified: a customer
signing in with Facebook adds and confirms their email by a 6-digit code before their first payment. This is intentional
(it prevents account take-over) and is not a misconfiguration.

## 3. Website environment (`.env` on the VPS)

```
APP_URL=https://007resorts.com
SOCIAL_ENABLED_PROVIDERS=google,facebook
SOCIAL_GOOGLE_CLIENT_ID=...
SOCIAL_GOOGLE_CLIENT_SECRET=...
SOCIAL_FACEBOOK_CLIENT_ID=...
SOCIAL_FACEBOOK_CLIENT_SECRET=...
SOCIAL_FAKE=false
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1
```

Then `php artisan config:cache`. nginx must pass `X-Forwarded-Proto https` (the site already trusts `TRUSTED_PROXIES`); the callback URL itself is
taken from `APP_URL`, never from request headers.

## 4. API side (once)

* The website's service token needs the `customer.social` scope: `php artisan r007:service-token create --name=booking-web --scope=public.read,public.checkout,customer.social`
  (or rotate the existing token, which keeps its scope; check the scope in the admin's service tokens screen).
* API environment: `SOCIAL_PROVIDERS_ENABLED=google,facebook`, `SOCIAL_GOOGLE_CLIENT_ID=<same Google client id>`,
  `SOCIAL_TRUSTED_EMAIL_PROVIDERS=google`. See the API's `docs/CUSTOMER_SOCIAL_LOGIN.md`.

## 5. Check it works

1. Open https://007resorts.com/login in a private window. You should see *Continue with Google* and *Continue with Facebook*.
2. Sign in with a personal account. The first time you see the terms/privacy step, then (if needed) a phone/email step, then you land where you started.
3. Account page, *Sign-in methods*: the provider is listed as Connected. You cannot disconnect your only sign-in method.
4. If a button is missing: the provider is not in `SOCIAL_ENABLED_PROVIDERS`, its credentials are empty, or the API has it disabled
   (the website hides a button whenever the API cannot confirm the provider is enabled).

Common errors: `redirect_uri_mismatch` (Google) or "URL blocked / redirect URI is not whitelisted" (Facebook): the registered URI differs from
`APP_URL` + `/auth/<provider>/callback`. "This app is not verified/only test users" : app is still in Testing (Google) or Development mode (Facebook).

## 6. Rotating secrets

Regenerate the client secret in the provider console, update the website `.env`, `php artisan config:cache`. Nothing else changes; existing customers stay linked.
