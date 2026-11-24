<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f7f2f6;font-family:Arial,Helvetica,sans-serif;color:#302535">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background:#ffffff;border:1px solid #eadfe8;border-radius:18px">
<tr><td style="padding:30px 30px 22px">
<div style="font-size:32px;font-weight:bold;letter-spacing:-1px">avelle<span style="color:#db2777">.</span></div>
</td></tr>
<tr><td style="padding:25px 30px;background:#fcecf4;border-top:1px solid #f3d8e7;border-bottom:1px solid #f3d8e7">
<h1 style="font-size:26px;line-height:1.3;margin:0 0 12px;color:#302535">{{ $heading }}</h1>
<p style="font-size:15px;line-height:1.8;margin:0;color:#55465b;white-space:pre-line">{{ $messageText }}</p>
</td></tr>
<tr><td style="padding:26px 30px">
@if ($orderDetails)
    <p style="font-size:13px;line-height:1.7;margin:0 0 8px;color:#65566c;word-break:break-all">Order reference {{ $orderDetails['reference'] }}</p>
    <p style="font-size:14px;line-height:1.7;margin:0 0 24px;color:#52405d"><strong>Status</strong> &nbsp; {{ ucfirst($orderDetails['status']) }}</p>
    <h2 style="font-size:19px;margin:0 0 16px;color:#302535">Your items</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    @foreach ($orderDetails['items'] as $item)
        <tr>
            <td width="92" valign="top" style="width:92px;padding:16px 14px 16px 0;border-top:1px solid #eee5ed">
                @if ($item['image_path'])
                    <img src="{{ $message->embed($item['image_path']) }}" alt="{{ $item['name'] }}" width="78" height="100" style="display:block;width:78px;height:100px;object-fit:cover;border-radius:8px;background:#f7f2f6">
                @elseif (str_starts_with($item['image_url'], 'https://'))
                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="78" height="100" style="display:block;width:78px;height:100px;object-fit:cover;border-radius:8px">
                @else
                    <div style="width:78px;padding:28px 0;text-align:center;font-size:13px;color:#8f5478;background:#fcecf4;border-radius:8px">Avelle</div>
                @endif
            </td>
            <td valign="top" style="padding:16px 0;border-top:1px solid #eee5ed">
                <p style="font-size:16px;line-height:1.6;font-weight:bold;margin:0 0 8px;color:#302535">{{ $item['name'] }}</p>
                <p style="font-size:14px;line-height:1.7;color:#65566c;margin:0">Size {{ $item['size'] }}@if (!empty($item['color'])) · {{ $item['color'] }}@endif</p>
                <p style="font-size:14px;line-height:1.7;color:#65566c;margin:3px 0">Qty {{ $item['quantity'] }} · &#8377;{{ number_format($item['price']) }} each</p>
                <p style="font-size:16px;font-weight:bold;color:#302535;margin:10px 0 0">&#8377;{{ number_format($item['price'] * $item['quantity']) }}</p>
            </td>
        </tr>
    @endforeach
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 24px;border-top:1px solid #e9dce7">
        @foreach (['Subtotal'=>'subtotal','Delivery'=>'shipping','Discount'=>'discount','Wallet credit'=>'wallet_spent'] as $label=>$key)
            <tr><td style="padding:12px 0 0;font-size:15px;color:#65566c">{{ $label }}</td><td align="right" style="padding:12px 0 0;font-size:15px;color:#403048">{{ in_array($key, ['discount','wallet_spent'])?'−':'' }}&#8377;{{ number_format($orderDetails[$key] ?? 0) }}</td></tr>
        @endforeach
        <tr><td style="padding:16px 0 0;font-size:20px;font-weight:bold">Total</td><td align="right" style="padding:16px 0 0;font-size:20px;font-weight:bold">&#8377;{{ number_format($orderDetails['total']) }}</td></tr>
    </table>
    <p style="font-size:14px;line-height:1.7;color:#65566c;margin:0 0 24px">{{ $orderDetails['payment'] }}</p>
    <table role="presentation" width="100%" cellpadding="18" cellspacing="0" style="background:#faf7fb;border:1px solid #eee5ed;border-radius:10px"><tr><td>
        <h2 style="font-size:17px;margin:0 0 10px;color:#302535">Delivery address</h2>
        <p style="font-size:15px;line-height:1.8;margin:0;color:#55465b"><strong>{{ $orderDetails['name'] }}</strong><br>{{ $orderDetails['address'] }}</p>
    </td></tr></table>
    @if ($orderDetails['tracking_number'])
        <p style="font-size:14px;line-height:1.8;color:#55465b;margin:20px 0 0"><strong>{{ $orderDetails['carrier'] ?: 'Shipment tracking' }}</strong><br>{{ $orderDetails['tracking_number'] }}</p>
    @endif
@else
    @foreach ($lines as $line)
        <p style="font-size:15px;line-height:1.8;color:#55465b">{{ $line }}</p>
    @endforeach
@endif
@if ($actionUrl)
    <p style="margin:28px 0 20px"><a href="{{ $actionUrl }}" style="background:#d92d78;color:#ffffff;padding:15px 24px;text-decoration:none;border-radius:8px;font-size:15px;font-weight:bold;display:inline-block">{{ $actionLabel }}</a></p>
    <p style="font-size:12px;line-height:1.8;color:#706278;word-break:break-all">You can also open <a href="{{ $actionUrl }}" style="color:#a92a65">{{ $actionUrl }}</a></p>
@endif
</td></tr>
<tr><td style="padding:22px 30px;background:#faf7fb;border-top:1px solid #eee5ed;border-radius:0 0 18px 18px">
<p style="font-size:13px;line-height:1.8;margin:0;color:#706278">Your style. Your everyday.<br>This is an automated message from Avelle.</p>
</td></tr></table>
</td></tr></table>
</body>
</html>
