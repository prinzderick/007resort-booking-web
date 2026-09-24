<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsRequestException;
use App\Services\Cms\CmsUnavailableException;
use App\Services\Online\ContentService;
use App\Services\Online\SiteContext;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public const TOPICS = ['GENERAL' => 'General question', 'BOOKING' => 'A booking', 'EVENTS' => 'Events and celebrations', 'MEMBERSHIP' => 'Membership', 'FEEDBACK' => 'Feedback', 'PRESS' => 'Press', 'OTHER' => 'Something else'];

    public function __construct(private readonly CmsClient $cms, private readonly SiteContext $ctx, private readonly ContentService $content) {}

    public function show()
    {
        return view('contact', [
            'page' => $this->content->page('contact'),
            'topics' => self::TOPICS,
            'faqs' => $this->content->faqs(null, 4),
            'wa' => $this->ctx->whatsappUrl($this->ctx->site()['contact']['whatsappMessage'] ?? null),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'in:'.implode(',', array_keys(self::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            $this->cms->contact($data + ['website' => ''], $request->ip());
        } catch (CmsRequestException $e) {
            if ($e->status === 422) {
                return back()->withInput()->withErrors(collect($e->errors)->map(fn ($m) => is_array($m) ? $m[0] : $m)->all() ?: ['form' => 'Please check the form and try again.']);
            }
            if ($e->status === 429) {
                return back()->withInput()->withErrors(['form' => 'That is a lot of messages in a short time. Please wait a little and try again, or WhatsApp us.']);
            }

            return back()->withInput()->withErrors(['form' => 'We could not send your message. Please try again or call us.']);
        } catch (CmsUnavailableException) {
            return back()->withInput()->withErrors(['form' => 'We could not send your message right now. Please try again in a few minutes, or call or WhatsApp us.']);
        }

        return redirect()->route('contact')->with('status', 'Thank you. Your message has reached us and we will reply by email or phone soon.');
    }
}
