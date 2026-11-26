@extends('layouts.dashboard')

@section('title', 'My Profile')

@section('content')
    <section class="dashboard-hero content-hero">
        <div><p class="eyebrow">My Profile</p><h1>{{ $user->full_name }}</h1><span>{{ $user->faculty_id }} · {{ $user->role_label }}</span></div>
        <img class="profile-hero-avatar profile-photo" src="{{ $user->profile_photo_url }}" alt="Profile photo for {{ $user->full_name }}">
    </section>
    @if (session('status'))<div class="dashboard-alert success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="dashboard-alert error" role="alert">Please review the highlighted fields and try again.</div>@endif

    <div class="profile-layout">
        <section class="profile-summary-card">
            <img class="profile-summary-avatar profile-photo" src="{{ $user->profile_photo_url }}" alt="">
            <h2>{{ $user->full_name }}</h2><p>{{ $user->position }}</p>
            <div class="profile-facts">
                <span>{{ $user->isAcademicRosterDemo() ? 'Generated roster login ID (academic demo)' : 'Employee / Faculty ID' }} <strong>{{ $user->faculty_id }}</strong></span>
                <span>Department <strong>{{ $user->department }}</strong></span>
                <span>Official email <strong>{{ $user->email }}</strong></span>
                @if($user->pending_email)<span>Awaiting verification <strong>{{ $user->pending_email }}</strong></span>@endif
                <span>Contact <strong>{{ $user->masked_contact_number }}</strong></span>
                <span>Account status <strong>{{ str($user->status)->replace('_', ' ')->title() }}</strong></span>
            </div>
            <a class="button button-secondary profile-security-link" href="{{ route('security.show') }}">Security Settings</a>
        </section>

        <section class="content-editor-section">
            <div class="editor-section-heading"><p class="eyebrow">Editable Information</p><h2>Update your profile</h2></div>
            <form class="announcement-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PATCH')
                <div class="form-columns">
                    <label>First name<input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>@error('first_name') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Middle name<input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}">@error('middle_name') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Last name<input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>@error('last_name') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Employee / Faculty ID<input type="text" value="{{ $user->faculty_id }}" readonly aria-readonly="true"></label>
                    <label>Official email<input type="email" name="email" value="{{ old('email', $user->email) }}" @required(! $user->isAcademicRosterDemo())>@error('email') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Current password <small>Required only when changing email</small><input type="password" name="current_password" autocomplete="current-password">@error('current_password') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Department<input type="text" name="department" value="{{ old('department', $user->department) }}" required>@error('department') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Position / Designation<input type="text" name="position" value="{{ old('position', $user->position) }}" required>@error('position') <span class="field-error">{{ $message }}</span> @enderror</label>
                    <label>Contact number<input type="tel" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" autocomplete="tel">@error('contact_number') <span class="field-error">{{ $message }}</span> @enderror</label>
                </div>
                <label>Profile photo <small>JPEG, PNG, or WebP; maximum 2 MB</small><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">@error('profile_photo') <span class="field-error">{{ $message }}</span> @enderror</label>
                <label>Short profile information<textarea name="bio" rows="4" maxlength="1000" autocomplete="off">{{ old('bio', $user->bio) }}</textarea>@error('bio') <span class="field-error">{{ $message }}</span> @enderror</label>
                <div class="form-actions"><button class="button button-primary" type="submit">Save Profile</button></div>
            </form>
        </section>
    </div>
@endsection
