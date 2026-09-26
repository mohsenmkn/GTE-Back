<!-- Modules/Payroll/resources/views/pdf/payslip.blade.php -->
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { direction: rtl; font-size: 12px; margin: 0; padding: 15px; }

        /* 🔑 هدر با لوگو و نام شرکت */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px double #333;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .company-logo {
            width: 80px;
            height: 80px;
        }

        .company-info {
            text-align: center;
            flex: 1;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .payslip-title {
            font-size: 14px;
            color: #555;
            margin: 3px 0;
        }

        .info-table {
            width: 100%;
            margin-bottom: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }

        .info-table td {
            padding: 6px 10px;
        }

        .info-table .label {
            font-weight: bold;
            color: #555;
            width: 25%;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .items-table th {
            background: #2c3e50;
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 13px;
        }

        .items-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
            min-height: 30px;
        }

        .items-table tr:nth-child(even) {
            background: #fafafa;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .col-work { color: #6b21a8; }
        .col-benefit { color: #15803d; }
        .col-deduction { color: #b91c1c; }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            border: 1px solid #333;
            padding: 8px;
            font-size: 13px;
        }

        .total-benefits {
            color: #15803d;
            font-weight: bold;
        }

        .total-deductions {
            color: #b91c1c;
            font-weight: bold;
        }

        .net-pay-box {
            border: 2px solid #1e40af;
            padding: 10px;
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin-top: 10px;
            background: #eff6ff;
        }

        .footer {
            margin-top: 25px;
            text-align: left;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
    </style>
</head>
<body>
{{-- 🔑 هدر با لوگو و نام شرکت --}}
<div class="header">
    {{-- لوگو: مسیر را در public/images/ قرار دهید --}}
    <img src="{{ public_path('images/logo-gahrtarabar.png') }}"
         alt="لوگو" class="company-logo"
         onerror="this.style.display='none'">

    <div class="company-info">
        <h1 class="company-name">
            شرکت حمل و نقل ترکیبی مواد معدنی گهر ترابر سیرجان (سهامی عام)
        </h1>
        <div class="payslip-title">
            فیش حقوقی - {{ $payslip->yearMonthLabel }}
        </div>
    </div>

    <div style="width: 80px;"></div> {{-- برای تعادل layout --}}
</div>

{{-- اطلاعات کارمند --}}
<table class="info-table">
    <tr>
        <td class="label">کد پرسنلی:</td>
        <td>{{ $payslip->personnelCode }}</td>
        <td class="label">نام و نام خانوادگی:</td>
        <td>{{ $payslip->employeeName }}</td>
    </tr>
    <tr>
        <td class="label">کد ملی:</td>
        <td>{{ $payslip->nationalId }}</td>
        <td class="label">نام پدر:</td>
        <td>{{ $payslip->fatherName ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">روزهای کارکرد:</td>
        <td>{{ $payslip->effectiveDays }} روز</td>
        <td></td>
        <td></td>
    </tr>
</table>

{{-- جدول 3 ستونه --}}
<table class="items-table">
    <thead>
    <tr>
        <th style="width: 33%;">کارکرد</th>
        <th style="width: 34%;">مزایا</th>
        <th style="width: 33%;">کسورات</th>
    </tr>
    </thead>
    <tbody>
    @php
        $workItems = $payslip->items['کارکرد'] ?? [];
        $benefitItems = $payslip->items['مزایا'] ?? [];
        $deductionItems = $payslip->items['کسورات'] ?? [];
        $maxRows = max(count($workItems), count($benefitItems), count($deductionItems));
    @endphp

    @for ($i = 0; $i < $maxRows; $i++)
        <tr>
            <td class="col-work">
                @if(isset($workItems[$i]))
                    <div class="item-row">
                        <span>{{ $workItems[$i]['title'] }}</span>
                        <span>{{ number_format($workItems[$i]['amount']) }}</span>
                    </div>
                @endif
            </td>
            <td class="col-benefit">
                @if(isset($benefitItems[$i]))
                    <div class="item-row">
                        <span>{{ $benefitItems[$i]['title'] }}</span>
                        <span>{{ number_format($benefitItems[$i]['amount']) }}</span>
                    </div>
                @endif
            </td>
            <td class="col-deduction">
                @if(isset($deductionItems[$i]))
                    <div class="item-row">
                        <span>{{ $deductionItems[$i]['title'] }}</span>
                        <span>{{ number_format($deductionItems[$i]['amount']) }}</span>
                    </div>
                @endif
            </td>
        </tr>
    @endfor
    </tbody>
    <tfoot>
    <tr>
        <td></td>
        <td class="total-benefits">
            <div class="item-row">
                <span>جمع مزایا:</span>
                <span>{{ number_format($payslip->totalBenefits) }}</span>
            </div>
        </td>
        <td class="total-deductions">
            <div class="item-row">
                <span>جمع کسورات:</span>
                <span>{{ number_format($payslip->totalDeductions) }}</span>
            </div>
        </td>
    </tr>
    </tfoot>
</table>

{{-- خالص پرداختی --}}
<div class="net-pay-box">
    خالص پرداختی: {{ number_format($payslip->netPay) }} ریال
</div>

{{-- فوتر --}}
<div class="footer">
    HRM-FO-۲۳-۰۰ | تاریخ چاپ: {{ now()->format('Y/m/d') }}
</div>
</body>
</html>
