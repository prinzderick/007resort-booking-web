<?php

namespace Tests\Unit;

use App\Support\Avatar;
use App\Support\NigerianPhone;
use App\Support\SafeReturn;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialSupportTest extends TestCase
{
    /** @return array<string, array{0: mixed}> */
    public static function badReturns(): array
    {
        return [
            'absolute https' => ['https://evil.test/'], 'protocol relative' => ['//evil.test/x'], 'backslash' => ['/\\evil.test'],
            'js scheme' => ['javascript:alert(1)'], 'data scheme' => ['data:text/html,x'], 'crlf' => ["/a\r\nLocation: https://evil.test"],
            'encoded crlf' => ['/a%0d%0aSet-Cookie:x'], 'tab trick' => ["/\t/evil.test"], 'no slash' => ['sports'],
            'empty' => [''], 'null' => [null], 'array' => [['/x']], 'auth loop' => ['/auth/google/redirect'], 'login loop' => ['/login?x=1'], 'huge' => ['/'.str_repeat('a', 3000)],
        ];
    }

    #[DataProvider('badReturns')]
    public function test_unsafe_return_paths_are_refused(mixed $value): void
    {
        $this->assertNull(SafeReturn::path($value));
    }

    public function test_same_site_relative_paths_are_kept(): void
    {
        $this->assertSame('/sports/x?date=2026-09-30', SafeReturn::path('/sports/x?date=2026-09-30'));
        $this->assertSame('/checkout/0192f6a0-0000-7000-8000-000000000501', SafeReturn::path('/checkout/0192f6a0-0000-7000-8000-000000000501'));
        $this->assertSame('/', SafeReturn::path('/'));
    }

    public function test_intended_urls_are_reduced_to_a_path_only_for_this_host(): void
    {
        $this->assertSame('/pool?a=1', SafeReturn::fromIntended('https://007resorts.com/pool?a=1', '007resorts.com'));
        $this->assertNull(SafeReturn::fromIntended('https://evil.test/pool', '007resorts.com'));
        $this->assertNull(SafeReturn::fromIntended('https://007resorts.com@evil.test/pool', '007resorts.com'));
        $this->assertNull(SafeReturn::fromIntended(null, '007resorts.com'));
    }

    public function test_nigerian_phone_formats_normalise_to_e164(): void
    {
        foreach (['0803 123 4567', '08031234567', '+234 803 123 4567', '2348031234567', '803-123-4567', '(0803) 123 4567'] as $ok) {
            $this->assertSame('+2348031234567', NigerianPhone::normalize($ok), $ok);
        }
        $this->assertSame('+2347012345678', NigerianPhone::normalize('07012345678'));
        foreach (['12345', '0603 123 4567', '+1 415 555 0100', '0803 123 456', 'abc', '', null] as $bad) {
            $this->assertNull(NigerianPhone::normalize($bad), (string) $bad);
        }
    }

    public function test_avatars_must_be_https_from_allowed_hosts(): void
    {
        $this->assertNotNull(Avatar::safeUrl('https://lh3.googleusercontent.com/a/x=s96'));
        $this->assertNotNull(Avatar::safeUrl('https://scontent-lhr8-1.xx.fbcdn.net/v/t1/p.jpg'));
        $this->assertNotNull(Avatar::safeUrl('https://platform-lookaside.fbsbx.com/platform/profilepic/?asid=1'));
        foreach (['http://lh3.googleusercontent.com/a', 'https://evil.test/a.png', 'https://lh3.googleusercontent.com.evil.test/a', 'https://user@lh3.googleusercontent.com/a', 'javascript:alert(1)', 'https://lh3.googleusercontent.com:8443/a', '//lh3.googleusercontent.com/a', null] as $bad) {
            $this->assertNull(Avatar::safeUrl($bad), (string) $bad);
        }
        $this->assertSame('AO', Avatar::initials('Amaka Okafor'));
        $this->assertSame('?', Avatar::initials(null));
    }
}
