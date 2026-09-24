{{-- Props: $faqs (FAQ sections) --}}
<div class="faq">
    @foreach ($faqs as $f)
        <details @if ($loop->first && ! empty($open)) open @endif>
            <summary>{{ $f['question'] }}</summary>
            <div class="ans prose">{!! $f['answerHtml'] ?? '<p>'.e($f['answer'] ?? '').'</p>' !!}</div>
        </details>
    @endforeach
</div>
