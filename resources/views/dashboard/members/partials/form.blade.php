<div class="form-columns">
    <label>{{ $member->isAcademicRosterDemo() ? 'Generated roster login ID (academic demo)' : 'Employee / Faculty ID' }}<input type="text" name="faculty_id" value="{{ old('faculty_id', $member->faculty_id) }}" required>@error('faculty_id') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>First name<input type="text" name="first_name" value="{{ old('first_name', $member->first_name) }}" required>@error('first_name') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Middle name<input type="text" name="middle_name" value="{{ old('middle_name', $member->middle_name) }}">@error('middle_name') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Last name<input type="text" name="last_name" value="{{ old('last_name', $member->last_name) }}" required>@error('last_name') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Official email<input type="email" name="email" value="{{ old('email', $member->email) }}" @required(! $member->isAcademicRosterDemo())>@error('email') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Department<input type="text" name="department" value="{{ old('department', $member->department) }}" required>@error('department') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Position / Designation<input type="text" name="position" value="{{ old('position', $member->position) }}" required>@error('position') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Contact number<input type="tel" name="contact_number" value="{{ old('contact_number', $member->contact_number) }}" autocomplete="tel">@error('contact_number') <span class="field-error">{{ $message }}</span> @enderror</label>
    <label>Role<select name="role" required>@foreach(config('roles') as $value => $settings)<option value="{{ $value }}" @selected(old('role', $member->role) === $value)>{{ $settings['label'] }}</option>@endforeach</select>@error('role')<span class="field-error">{{ $message }}</span>@enderror</label>
    @if($member->exists)
    <label>Account access<select name="status" required><option value="pending_activation" @selected(old('status', $member->status) === 'pending_activation')>Pending Activation</option><option value="active" @selected(old('status', $member->status) === 'active')>Activate account</option><option value="deactivated" @selected(old('status', $member->status) === 'deactivated')>Deactivate account</option></select><small>Deactivated members cannot access the system. Saving this change revokes sessions and trusted browsers. Activation still requires all account setup checks.</small>@error('status') <span class="field-error">{{ $message }}</span> @enderror</label>
    @else
    <div><strong>Pending Activation</strong><p>This account becomes active after the member completes the required account setup.</p><input type="hidden" name="status" value="pending_activation"></div>
    @endif
</div>
<label>Short profile information<textarea name="bio" rows="4" maxlength="1000" autocomplete="off">{{ old('bio', $member->bio) }}</textarea>@error('bio') <span class="field-error">{{ $message }}</span> @enderror</label>
