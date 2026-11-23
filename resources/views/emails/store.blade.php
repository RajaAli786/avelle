<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>

<body style="margin:0;background:#f8f4f6;font-family:Arial,sans-serif;color:#25202a">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:32px 12px">
                <table role="presentation" width="560" style="max-width:100%;background:white;border-radius:16px"
                    cellpadding="28">
                    <tr>
                        <td>
                            <div style="font-size:30px;font-weight:bold">avelle<span style="color:#ec3688">.</span>
                            </div>
                            <h1 style="font-size:24px">{{ $heading }}</h1>
                            <p style="line-height:1.7;white-space:pre-line">{{ $messageText }}</p>
                            @foreach ($lines as $line)
                            <p style="line-height:1.6">{{ $line }}</p>
                            @endforeach @if ($actionUrl)
                            <p style="margin:28px 0"><a href="{{ $actionUrl }}"
                                    style="background:#e83383;color:white;padding:14px 22px;text-decoration:none;border-radius:8px;display:inline-block">{{ $actionLabel }}</a>
                            </p>
                            <p style="font-size:12px;word-break:break-all">Or open: {{ $actionUrl }}</p>
                            @endif
                            <p style="font-size:12px;color:#716773">Your style. Your everyday.<br>This is an
                                automated message from Avelle.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>