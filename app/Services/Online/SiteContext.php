<?php

namespace App\Services\Online;

use App\Services\Cms\CmsClient;
use App\Services\Cms\CmsUnavailableException;
use App\Support\OpenHours;
use Carbon\CarbonImmutable;

/**
 * Per-request view of site-wide CMS settings, normalised with safe fallbacks so that chrome (header, footer,
 * announcement, hours pill) and booking pages render even when the CMS was never reachable (`$up === false`).
 */
class SiteContext
{
    /** @var array<string, mixed>|null */
    private ?array $site = null;

    private bool $up = true;

    /** @var list<array<string, mixed>>|null */
    private ?array $pages = null;

    public function __construct(private readonly CmsClient $cms) {}

    public function cms(): CmsClient
    {
        return $this->cms;
    }

    /** @return array<string, mixed> */
    public function site(): array
    {
        if ($this->site !== null) {
            return $this->site;
        }
        try {
            $raw = $this->cms->site();
        } catch (CmsUnavailableException) {
            $raw = [];
            $this->up = false;
        }
        $c = (array) config('site.contact');
        $contact = array_filter((array) ($raw['contact'] ?? []), fn ($v) => filled($v)) + array_filter([
            'phone' => $c['phone'] ?? null, 'email' => $c['email'] ?? null, 'address' => $c['address'] ?? null, 'whatsapp' => $c['whatsapp'] ?? null, 'mapUrl' => $c['map_url'] ?? null,
        ], fn ($v) => filled($v));
        $hours = (array) ($raw['hours'] ?? []);

        return $this->site = [
            'brand' => ((array) ($raw['brand'] ?? [])) + ['name' => config('site.name'), 'tagline' => '', 'logo' => null],
            'contact' => $contact,
            'hours' => $hours,
            'open' => OpenHours::status($hours, CarbonImmutable::now(config('r007.display_timezone', 'Africa/Lagos'))),
            'social' => array_filter((array) ($raw['social'] ?? []), fn ($v) => filled($v)),
            'seo' => ((array) ($raw['seo'] ?? [])) + ['titleTemplate' => '%s | '.config('site.name'), 'defaultTitle' => config('site.name'), 'defaultDescription' => '', 'ogImage' => null],
            'announcement' => ((array) ($raw['announcement'] ?? [])) + ['enabled' => false, 'text' => '', 'link' => null, 'tone' => 'INFO'],
            'booking' => ((array) ($raw['booking'] ?? [])) + ['ticketsCtaLabel' => 'Buy tickets', 'bookingCtaLabel' => 'Book a court', 'membershipCtaLabel' => 'Join the club', 'eventsCtaLabel' => 'Get tickets'],
            'footer' => ((array) ($raw['footer'] ?? [])) + ['text' => '', 'copyright' => config('site.name')],
        ];
    }

    /** False when the CMS could not be read at all (no fresh and no stale copy). */
    public function up(): bool
    {
        $this->site();

        return $this->up;
    }

    /** @return list<array{label: string, href: string, hint?: string}> */
    public function nav(): array
    {
        return (array) config('site.nav');
    }

    /** CMS pages flagged showInFooter (legal etc.) @return list<array{slug: string, title: string}> */
    public function footerPages(): array
    {
        if ($this->pages === null) {
            try {
                $this->pages = array_values(array_filter($this->cms->pages(), fn ($p) => ! empty($p['showInFooter'])));
            } catch (CmsUnavailableException) {
                $this->pages = [];
            }
        }

        return $this->pages;
    }

    /** @return array<string, mixed> */
    public function contact(): array
    {
        return $this->site()['contact'];
    }

    public function whatsappUrl(?string $message = null): ?string
    {
        $n = preg_replace('/\D+/', '', (string) ($this->contact()['whatsapp'] ?? ''));

        return $n ? 'https://wa.me/'.$n.($message ? '?text='.rawurlencode($message) : '') : null;
    }
}
