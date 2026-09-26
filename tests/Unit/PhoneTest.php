<?php

namespace Tests\Unit;

use App\Support\Mask;
use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function valid(): array
    {
        return [
            'local with zero' => ['08031234567', '+2348031234567'],
            'spaces' => ['0803 123 4567', '+2348031234567'],
            'dashes' => ['0803-123-4567', '+2348031234567'],
            'no leading zero' => ['803 123 4567', '+2348031234567'],
            'plus 234' => ['+234 803 123 4567', '+2348031234567'],
            'plus 234 with stray zero' => ['+234 0803 123 4567', '+2348031234567'],
            '234 no plus' => ['2348031234567', '+2348031234567'],
            '00234' => ['00234 803 123 4567', '+2348031234567'],
            'parentheses' => ['(0803) 123 4567', '+2348031234567'],
            'other country' => ['+44 7700 900123', '+447700900123'],
            '090 range' => ['09012345678', '+2349012345678'],
        ];
    }

    #[DataProvider('valid')]
    public function test_normalises(string $in, string $out): void
    {
        $this->assertSame($out, Phone::normalize($in));
    }

    /** @return array<string, array{string}> */
    public static function invalid(): array
    {
        return ['empty' => [''], 'letters' => ['0803abc4567'], 'too short' => ['0803123'], 'too long' => ['080312345678901'], 'landline-like' => ['01 234 5678'], 'plus junk' => ['+1'], 'only plus' => ['+']];
    }

    #[DataProvider('invalid')]
    public function test_rejects(string $in): void
    {
        $this->assertNull(Phone::normalize($in));
    }

    public function test_display_and_mask(): void
    {
        $this->assertSame('+234 803 123 4567', Phone::display('+2348031234567'));
        $this->assertSame('+234 803 *** 4567', Phone::mask('+2348031234567'));
        $this->assertSame('a**@example.com', Mask::email('ada@example.com'));
        $this->assertStringEndsWith('@example.com', Mask::email('chinedu@example.com'));
        $this->assertStringNotContainsString('chinedu', Mask::email('chinedu@example.com'));
    }
}
