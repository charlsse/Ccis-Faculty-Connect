<div class="password-editor" data-password-editor>
    <div class="password-control">
        <label for="new-password">New password</label>
        <div class="password-field">
            <input id="new-password" type="password" name="password" autocomplete="new-password" minlength="12" required data-password-input aria-describedby="password-requirements password-error">
            <button type="button" data-password-toggle aria-controls="new-password" aria-pressed="false" aria-label="Show new password">Show</button>
        </div>
        <span id="password-error">@error('password')<small class="field-error">{{ $message }}</small>@enderror</span>
    </div>
    <div class="password-guidance" id="password-requirements">
        <strong>Password requirements</strong>
        <ul class="password-rules">
            @foreach(['length' => 'At least 12 characters', 'upper' => 'Uppercase letter', 'lower' => 'Lowercase letter', 'number' => 'Number', 'symbol' => 'Symbol or special character'] as $rule => $description)
                <li data-password-rule="{{ $rule }}"><span data-rule-icon aria-hidden="true">&#9675;</span><span class="visually-hidden" data-rule-state>Required: </span>{{ $description }}</li>
            @endforeach
        </ul>
        <div class="password-strength" data-password-strength hidden>
            <label for="password-strength-meter">Password strength <span data-strength-label>Not entered</span></label>
            <meter id="password-strength-meter" min="0" max="4" value="0" aria-label="Password strength" aria-valuetext="Not entered"></meter>
            <small>An estimate based on length and variety.</small>
        </div>
    </div>
    <div class="password-control">
        <label for="confirm-password">Confirm new password</label>
        <div class="password-field">
            <input id="confirm-password" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required data-password-input aria-describedby="password-match">
            <button type="button" data-password-toggle aria-controls="confirm-password" aria-pressed="false" aria-label="Show confirmation password">Show</button>
        </div>
        <small id="password-match" class="password-match" data-password-match role="status" aria-live="polite"></small>
    </div>
</div>
