<?php

namespace App\Http\Controllers;

use App\Services\R007Api\MockState;
use Illuminate\Http\Request;

/** Stand-in for Paystack's hosted page, registered only when R007_MOCK=true. */
class MockPaystackController extends Controller
{
    public function show(string $reference)
    {
        abort_unless(config('r007.mock'), 404);

        return view('mock.paystack', ['reference' => $reference, 'payment' => MockState::payment($reference)]);
    }

    public function complete(Request $request, string $reference)
    {
        abort_unless(config('r007.mock'), 404);
        $outcome = $request->input('outcome') === 'success' ? 'CAPTURED' : 'FAILED';
        $payment = MockState::settlePayment($reference, $outcome);

        return redirect()->to(($payment['callbackUrl'] ?? route('home')).'?reference='.urlencode($reference));
    }
}
