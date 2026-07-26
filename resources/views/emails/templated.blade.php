<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('company.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <div style="max-width:600px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border-radius:8px;padding:28px;line-height:1.5;">
            {{-- The template body is plain text with macros; macros are already replaced. nl2br + e() = safe, no user HTML/code. --}}
            {!! nl2br(e($body)) !!}
        </div>
        <p style="color:#71717a;font-size:12px;text-align:center;margin-top:16px;">
            {{ config('company.name') }} &middot; {{ config('company.phone') }} &middot; {{ config('company.email') }}
        </p>
    </div>
</body>
</html>
