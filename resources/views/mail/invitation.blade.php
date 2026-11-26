@php
    $expiry = \Illuminate\Support\Carbon::parse($expiresAt)->setTimezone(config('app.timezone'));
    $expiryLabel = $expiry->format('F j, Y g:i A').' '.(config('app.timezone') === 'Asia/Manila' ? 'Philippine Time (UTC+8)' : $expiry->format('e (P)'));
@endphp
<!doctype html>
<html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>FacultyConnect Invitation</title></head>
<body style="margin:0;padding:16px;background:#f0f5f1;font-family:Arial,Helvetica,sans-serif;color:#183e2c;line-height:1.6">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;background:#ffffff;border-top:6px solid #167449"><tr><td style="padding:24px;overflow-wrap:anywhere;word-break:break-word">
<h1 style="margin:0;color:#167449;font-size:28px">FacultyConnect</h1><p style="margin-top:4px">UMak &middot; CCIS Faculty Club</p>
<h2 style="font-size:21px">Welcome, {{ $name }}</h2>
<p>Sir Olivo, the faculty president, invited you to FacultyConnect, the CCIS Faculty Club Management Portal.</p>
<p>Your assigned role: <strong>{{ $role }}</strong></p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="16" style="background:#edf5ef;border:1px solid #cddfd3"><tr><td>
<strong>Your sign-in details</strong><br>Username<br><span style="font-family:Consolas,monospace">{{ $username }}</span><br><br>Temporary password<br><span style="font-family:Consolas,monospace">{{ $temporaryPassword }}</span>
</td></tr></table>
<p><strong>Use before:</strong> {{ $expiryLabel }}</p>
<p><a href="{{ $loginUrl }}" style="display:inline-block;background:#167449;color:#ffffff;padding:14px 20px;border-radius:6px;text-decoration:none;font-weight:bold">Log in to FacultyConnect</a></p>
<p style="font-size:13px">Or open this address: <a href="{{ $loginUrl }}" style="color:#167449;word-break:break-all">{{ $loginUrl }}</a></p>
<ol style="padding-left:22px"><li>Sign in using the temporary credentials above.</li><li>Choose a new password and verify your email.</li><li>Set up Google Authenticator or another TOTP app. Save your recovery codes privately.</li></ol>
<p>Do not share these credentials. For help, contact {{ $support ?: 'your FacultyConnect administrator' }}.</p>
</td></tr></table></td></tr></table></body></html>
