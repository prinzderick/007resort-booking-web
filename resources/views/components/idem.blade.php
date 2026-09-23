{{-- Per-render submission id: forwarded to the API as Idempotency-Key and used to make double-clicks safe. --}}
<input type="hidden" name="_submission" value="{{ \Illuminate\Support\Str::uuid() }}">
