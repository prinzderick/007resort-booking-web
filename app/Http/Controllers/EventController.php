<?php

namespace App\Http\Controllers;

use App\Services\Cms\CmsClient;
use App\Services\Online\ContentService;
use App\Services\Online\SiteContext;
use App\Services\Online\SiteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly CmsClient $cms,
        private readonly ContentService $content,
        private readonly SiteContext $ctx,
        private readonly SiteService $facilities,
    ) {}

    public function index(Request $request)
    {
        $all = $this->content->events(['limit' => 50, 'occurrences' => 6]);
        $months = ContentService::months($all);
        $cats = collect($all)->pluck('category')->filter()->unique()->values()->all();
        $month = (string) $request->query('month', '');
        $cat = strtoupper((string) $request->query('category', ''));
        $month = isset($months[$month]) ? $month : '';
        $cat = in_array($cat, $cats, true) ? $cat : '';
        $tz = config('r007.display_timezone', 'Africa/Lagos');

        $shown = array_values(array_filter($all, function ($e) use ($month, $cat, $tz) {
            return ($month === '' || CarbonImmutable::parse($e['startsAt'])->setTimezone($tz)->format('Y-m') === $month)
                && ($cat === '' || $e['category'] === $cat);
        }));
        $grouped = [];
        foreach ($shown as $e) {
            $grouped[CarbonImmutable::parse($e['startsAt'])->setTimezone($tz)->format('F Y')][] = $e;
        }

        return view('events.index', [
            'page' => $this->content->page('events'),
            'grouped' => $grouped, 'months' => $months, 'cats' => $cats, 'month' => $month, 'cat' => $cat,
            'featured' => collect($all)->firstWhere('featured', true),
            'links' => collect($all)->mapWithKeys(fn ($e) => [$e['occurrenceKey'] ?? $e['slug'] => $this->content->eventLink($e, $this->facilities)])->all(),
            'total' => count($all),
        ]);
    }

    public function show(string $slug)
    {
        $event = $this->cms->event($slug);
        abort_if($event === null, 404);
        $start = (string) request()->query('start', '');
        $occ = collect($event['nextOccurrences'] ?? [])->firstWhere('startsAt', $start) ?: ($event['nextOccurrences'][0] ?? ['startsAt' => $event['startsAt'], 'endsAt' => $event['endsAt'], 'startsAtLocal' => $event['startsAtLocal'] ?? null, 'endsAtLocal' => $event['endsAtLocal'] ?? null]);

        return view('events.show', [
            'event' => $event,
            'occ' => $occ,
            'link' => $this->content->eventLink($event, $this->facilities),
            'more' => array_values(array_filter($this->content->events(['limit' => 5]), fn ($e) => $e['slug'] !== $slug)),
        ]);
    }

    /** Add-to-calendar file (RFC 5545), one occurrence. */
    public function ics(Request $request, string $slug): Response
    {
        $event = $this->cms->event($slug);
        abort_if($event === null, 404);
        $start = CarbonImmutable::parse((string) ($request->query('start') ?: ($event['nextOccurrences'][0]['startsAt'] ?? $event['startsAt'])))->utc();
        $end = CarbonImmutable::parse((string) ($request->query('end') ?: ($event['nextOccurrences'][0]['endsAt'] ?? $event['endsAt'])))->utc();
        $esc = fn (string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
        $brand = $this->ctx->site()['brand']['name'] ?? config('site.name');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.$brand.'//Events//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:'.($event['slug'].'-'.$start->format('Ymd\THis')).'@'.parse_url(url('/'), PHP_URL_HOST),
            'DTSTAMP:'.CarbonImmutable::now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'), 'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$esc((string) $event['title']),
            'DESCRIPTION:'.$esc(trim(($event['summary'] ?? '').' '.route('events.show', $slug))),
            'LOCATION:'.$esc(trim(($event['venue']['label'] ?? '').', '.($this->ctx->contact()['address'] ?? ''), ', ')),
            'URL:'.route('events.show', $slug),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.$slug.'.ics"']);
    }
}
