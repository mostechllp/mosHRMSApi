Hello {{ $userName }},

We received a request to log in to your {{ config('app.name', 'Mostech HRMS') }} account.

Your one-time login verification code is:
{{ $otp }}

This code will expire in {{ $expiresInMinutes }} minutes.

Enter this 6-digit code on the sign-in screen to verify your identity.

SECURITY NOTICE:
Never share this code with anyone. Our support team will never ask for your verification code. If you did not make this request, please contact your IT administrator immediately.

Regards,
{{ config('app.name', 'Mostech HRMS') }} Team

