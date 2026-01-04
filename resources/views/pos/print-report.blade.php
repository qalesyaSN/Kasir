<!DOCTYPE html>
<html>
<head>
    <title>Closing Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* CSS agar tombol tidak ikut tercetak */
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
        }
        
        body { 
            font-family: 'Courier New', Courier, monospace; 
            width: 58mm; 
            font-size: 12px; 
            margin: 10px auto;
            padding: 5px;
            background: #fff;
        }
        .btn-print {
            width: 100%;
            padding: 10px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            cursor: pointer;
        }
        .text-center { text-align: center; }
        .border-bottom { border-bottom: 1px dashed black; margin-bottom: 5px; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">KLIK UNTUK CETAK / SIMPAN PDF</button>

    <div class="text-center bold">
        LAPORAN PENJUALAN<br>
        {{ date('d/m/Y') }}
    </div>
    <div class="border-bottom text-center">
        User: {{ auth()->user()->name }} | Jam: {{ date('H:i') }}
    </div>

    <table style="margin-top: 5px;">
        <tr class="bold" style="border-bottom: 1px solid black;">
            <td>Menu</td>
            <td style="text-align: center;">Qty</td>
            <td style="text-align: right;">Total</td>
        </tr>
        @foreach($sold_items as $item)
        <tr>
            <td>{{ $item->product->name }}</td>
            <td style="text-align: center;">{{ $item->total_qty }}</td>
            <td style="text-align: right;">{{ number_format($item->total_price) }}</td>
        </tr>
        @endforeach
    </table>

        <div class="border-bottom" style="margin-top: 5px;"></div>

    <table>
        <tr>
            <td>Total Item:</td>
            <td style="text-align: right;">{{ number_format($sold_items->sum('total_price')) }}</td>
        </tr>
        <tr>
            <td>Total Service (5%):</td>
            <td style="text-align: right;">{{ number_format($stats['total_service']) }}</td>
        </tr>
        <tr>
            <td>Total Pajak (10%):</td>
            <td style="text-align: right;">{{ number_format($stats['total_tax']) }}</td>
        </tr>
        <tr class="bold" style="font-size: 14px; border-top: 1px double black;">
            <td>GRAND TOTAL:</td>
            <td style="text-align: right;">Rp {{ number_format($stats['total_revenue']) }}</td>
        </tr>
    </table>


    <div class="border-bottom" style="margin-top: 10px;"></div>
    <div class="text-center" style="margin-top: 10px;">
        -- LAPORAN SELESAI --
    </div>
</body>
</html>

