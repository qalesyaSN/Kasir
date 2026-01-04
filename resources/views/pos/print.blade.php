<!DOCTYPE html>
<html>
<head>
    <title>Print Struk #{{ $order->order_number }}</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 58mm; font-size: 12px; }
        .text-center { text-align: center; }
        .border-top { border-top: 1px dashed black; margin-top: 5px; padding-top: 5px; }
        table { width: 100%; }
    </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
    <div class="text-center">
        <strong>RESTORAN SAYA</strong><br>
        Meja: {{ $order->table->number }}<br>
        {{ $order->created_at->format('d/m/Y H:i') }}
    </div>

    <div class="border-top">
        <table>
            @foreach($order->order_items as $item)
            <tr>
                <td>{{ $item->qty }}x {{ $item->product->name }}</td>
                <td style="text-align: right;">{{ number_format($item->price * $item->qty) }}</td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="border-top">
        <table>
            <tr><td>Total:</td><td style="text-align: right;">{{ number_format($order->total_final) }}</td></tr>
            <tr><td>Bayar:</td><td style="text-align: right;">{{ number_format($order->paid_amount) }}</td></tr>
            <tr><td>Kembali:</td><td style="text-align: right;">{{ number_format($order->change_amount) }}</td></tr>
        </table>
    </div>

    <div class="text-center" style="margin-top: 10px;">
        TERIMA KASIH
    </div>
</body>
</html>
