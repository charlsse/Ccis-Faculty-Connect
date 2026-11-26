@php($policies = app(\App\Services\PrivacyDocumentation::class)->acknowledgmentPolicies())
@if($policies)
    @php($samePolicyVersions = old('privacy_sha256') === $policies['privacy']['sha256'] && old('terms_sha256') === $policies['terms']['sha256'] && old('privacy_version') === $policies['privacy']['version'] && old('terms_version') === $policies['terms']['version'])
    <fieldset class="policy-acknowledgment">
        <legend>Policy acknowledgment</legend>
        <p>Notice acknowledgment is separate from consent to any specific processing purpose.</p>
        @foreach($policies as $kind => $policy)
            <input type="hidden" name="{{ $kind }}_version" value="{{ $policy['version'] }}">
            <input type="hidden" name="{{ $kind }}_sha256" value="{{ $policy['sha256'] }}">
        @endforeach
        <label class="check-row"><input type="checkbox" name="privacy_acknowledged" value="1" required @checked($samePolicyVersions && old('privacy_acknowledged'))><span>I acknowledge receipt of the <a href="{{ route('privacy.published', 'privacy-notice') }}" target="_blank" rel="noopener">Privacy Notice ({{ $policies['privacy']['version'] }}; opens in a new tab)</a>.</span></label>
        @error('privacy_acknowledged')<small class="field-error">{{ $message }}</small>@enderror
        <label class="check-row"><input type="checkbox" name="terms_accepted" value="1" required @checked($samePolicyVersions && old('terms_accepted'))><span>I agree to the <a href="{{ route('privacy.published', 'terms-of-use') }}" target="_blank" rel="noopener">Terms of Use ({{ $policies['terms']['version'] }}; opens in a new tab)</a>.</span></label>
        @error('terms_accepted')<small class="field-error">{{ $message }}</small>@enderror
        @if($errors->hasAny(['privacy_version','privacy_sha256','terms_version','terms_sha256']))<p class="field-error" role="alert">The policy version has changed. Reload this page and review both documents before continuing.</p>@endif
    </fieldset>
@endif
