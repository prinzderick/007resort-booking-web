<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test_accent_marks_the_starred_word_or_the_last_word_and_escapes(): void
    {
        $this->assertSame('Your weekend starts <i>here.</i>', (string) Text::accent('Your weekend starts *here.*'));
        $this->assertSame('Swim <i>slow</i>', (string) Text::accent('Swim slow'));
        $this->assertSame('Swim slow', (string) Text::accent('Swim slow', false));
        $this->assertSame('&lt;b&gt;x&lt;/b&gt; <i>&amp;</i>', (string) Text::accent('<b>x</b> *&*'));
        $this->assertSame('Your weekend starts here.', Text::plain('Your weekend starts *here.*'));
    }

    public function test_first_number_for_count_up(): void
    {
        $this->assertSame(['n' => 26.0, 'rest' => '+', 'raw' => '26'], Text::firstNumber('26+'));
        $this->assertSame(1200.0, Text::firstNumber('1,200')['n']);
        $this->assertNull(Text::firstNumber('Open'));
    }
}
