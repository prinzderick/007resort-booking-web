<?php

namespace App\Http\Controllers;

use App\Services\Online\CustomerService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        try {
            $this->customers->register($data, 'register:'.hash('sha256', $data['email'].$request->input('_submission', '')));
        } catch (R007ApiException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors($this->fieldErrors($e) ?: ['form' => ApiProblem::message($e)]);
        }

        // Verification code is delivered by the API (email/SMS); we only collect it.
        return redirect()->route('verify', ['email' => $data['email']])
            ->with('notice', 'Account created. Enter the verification code we sent you.');
    }

    public function showVerify(Request $request)
    {
        return view('auth.verify', ['email' => (string) $request->query('email', '')]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4,12}$/'],
        ]);

        try {
            $result = $this->customers->verify($data['email'], $data['code']);
        } catch (R007ApiException $e) {
            return back()->withInput($request->only('email'))->withErrors(['code' => ApiProblem::message($e)]);
        }

        if ($this->customers->check()) {
            return redirect()->intended(route('account'))->with('status', 'Your email is verified. Welcome!');
        }

        return redirect()->route('login')->with('status', 'Email verified. Please sign in.');
    }

    public function resend(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            $this->customers->resend($data['email']);
        } catch (R007ApiException) {
            // Do not reveal whether the address exists.
        }

        return back()->with('status', 'If that address has an account, a new code is on its way.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        try {
            $this->customers->login($data['email'], $data['password']);
        } catch (R007ApiException $e) {
            if ($e->status === 403 && $e->detail && stripos($e->detail, 'verif') !== false) {
                return redirect()->route('verify', ['email' => $data['email']])->with('notice', 'Please verify your email first.');
            }

            return back()->withInput($request->only('email'))->withErrors(['email' => ApiProblem::message($e)]);
        }

        return redirect()->intended(route('account'));
    }

    public function logout(Request $request)
    {
        $this->customers->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been signed out.');
    }

    /** @return array<string, string> */
    private function fieldErrors(R007ApiException $e): array
    {
        $out = [];
        foreach ($e->errors() as $field => $messages) {
            $out[$field] = is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages;
        }

        return $out;
    }
}
