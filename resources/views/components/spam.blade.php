{{-- Honeypot + fill-time trap (see SpamGuard). The honeypot is hidden from people and assistive tech. --}}
<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
    <label>Leave this field empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
</div>
<input type="hidden" name="_ts" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) time()) }}">
