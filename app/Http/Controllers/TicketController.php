<?php

namespace App\Http\Controllers;

use App\Services\Online\TicketService;
use App\Services\R007Api\R007ApiException;
use App\Support\QrCode;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function show(string $id)
    {
        return view('tickets.show', ['tickets' => [$this->load($id)]]);
    }

    /** All individual QR tickets of a pool order. */
    public function order(string $orderId)
    {
        try {
            $tickets = $this->tickets->forSource('orderId', $orderId);
        } catch (R007ApiException $e) {
            abort_if(in_array($e->status, [403, 404], true), 404);
            throw $e;
        }
        abort_if($tickets === [], 404);

        return view('tickets.show', ['tickets' => $tickets]);
    }

    public function qr(string $id): Response
    {
        return response(QrCode::svg($this->load($id)['qrToken']), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function download(string $id): Response
    {
        $ticket = $this->load($id);

        return response(QrCode::svg($ticket['qrToken']), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="007-resort-ticket-'.substr($ticket['id'], -8).'.svg"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function load(string $id): array
    {
        try {
            return $this->tickets->entitlement($id);
        } catch (R007ApiException $e) {
            abort_if(in_array($e->status, [403, 404], true), 404);
            throw $e;
        }
    }
}
