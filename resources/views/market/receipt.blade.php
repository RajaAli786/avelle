<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Order receipt {{ \App\Support\OrderDisplay::reference($order) }} · Avelle</title>
    <style>
        body {
            font: 14px Arial, sans-serif;
            color: #253047;
            background: #f3f4f7;
            margin: 0;
            padding: 35px
        }

        main {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 40px
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 2px solid #e93983;
            padding-bottom: 25px
        }

        h1 {
            letter-spacing: 4px;
            margin: 0 0 10px
        }

        h2 {
            font-size: 16px;
            margin-top: 30px
        }

        p {
            line-height: 1.8
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0
        }

        td,
        th {
            text-align: left;
            border-bottom: 1px solid #ddd;
            padding: 13px 8px
        }

        small {
            color: #7a8090
        }

        dl {
            margin-left: auto;
            width: 270px
        }

        dl div {
            display: flex;
            justify-content: space-between;
            padding: 8px
        }

        dd {
            margin: 0
        }

        .total {
            border-top: 1px solid #ddd;
            font-weight: bold
        }

        button {
            padding: 12px 20px;
            background: #e93983;
            border: 0;
            color: white;
            cursor: pointer;
            border-radius: 5px
        }

        footer {
            font-size: 11px;
            color: #7a8090;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            margin-top: 30px
        }

        @media print {
            body {
                background: white;
                padding: 0
            }

            main {
                padding: 15px;
                max-width: none
            }

            button {
                display: none
            }

            a {
                color: inherit;
                text-decoration: none
            }
        }

        @media(max-width:600px) {
            body {
                padding: 10px
            }

            main {
                padding: 20px
            }

            header {
                display: block
            }

            table {
                font-size: 11px
            }

            td,
            th {
                padding: 10px 3px
            }
        }
    </style>
</head>

<body>
    <main>
        <header>
            <div>
                <h1>{{ $settings['store_name'] }}</h1>
                @if ($settings['business_address'])
                    <p>{{ $settings['business_address'] }}</p>
                @endif
                <small>
                    Order receipt — not a tax invoice</small>
            </div>
            <div><strong>#{{ \App\Support\OrderDisplay::reference($order) }}</strong>
                <p>{{ date('d M Y', strtotime($order->created_at)) }}<br>{{ ucfirst($order->status) }}<br>Cash on
                    delivery · {{ $order->payment_status }}</p><button onclick="window.print()">Print / save
                    receipt</button>
            </div>
        </header>
        <h2>Deliver to</h2>
        <p>{{ $order->name }}<br>{{ $order->address }}, {{ $order->city }}, {{ $order->state }}
            {{ $order->pincode }}<br>{{ $order->phone }} · {{ $order->email }}</p>
        <table>
            <thead>
                <tr>
                    <th>Product / option</th>
                    <th>Qty</th>
                    <th>Unit price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach (json_decode($order->items, true) as $i)
                    <tr>
                        <td>{{ $i['name'] }}<br><small>{{ $i['size'] }} · {{ $i['color'] ?? '' }}</small></td>
                        <td>{{ $i['quantity'] }}</td>
                        <td>₹{{ $i['price'] }}</td>
                        <td>₹{{ $i['price'] * $i['quantity'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <dl>
            <div>
                <dt>Subtotal</dt>
                <dd>₹{{ $order->subtotal }}</dd>
            </div>
            <div>
                <dt>Shipping</dt>
                <dd>₹{{ $order->shipping }}</dd>
            </div>
            <div>
                <dt>Discount {{ $order->code }}</dt>
                <dd>−₹{{ $order->discount }}</dd>
            </div>
            <div class="total">
                <dt>Total</dt>
                <dd>₹{{ $order->total }}</dd>
            </div>
        </dl>
        <footer>Order reference: {{ \App\Support\OrderDisplay::reference($order) }}<br>
            @if ($settings['support_email'])
                Support: {{ $settings['support_email'] }}
            @endif
        </footer>
    </main>
</body>

</html>
