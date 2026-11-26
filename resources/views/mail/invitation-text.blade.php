@php
    $expiry = \Illuminate\Support\Carbon::parse($expiresAt)->setTimezone(config('app.timezone'));
    $expiryLabel = $expiry->format('F j, Y g:i A').' '.(config('app.timezone') === 'Asia/Manila' ? 'Philippine Time (UTC+8)' : $expiry->format('e (P)'));
@endphp
FacultyConnect — UMak CCIS Faculty Club

Welcome, {!! $name !!}.
Sir Olivo, the faculty president, invited you to FacultyConnect, the CCIS Faculty Club Management Portal.
Assigned role: {{ $role }}
Username: {!! $username !!}
Temporary password: {!! $temporaryPassword !!}
Expires: {{ $expiryLabel }}

Log in to FacultyConnect: {{ $loginUrl }}
1. Sign in using your temporary credentials.
2. Choose a new password and verify your email.
3. Set up Google Authenticator or another TOTP app. Save your recovery codes privately.
Do not share these credentials.
Support: {{ $support ?: 'your FacultyConnect administrator' }}
