<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; direction: rtl; }
    .header { background: #0a1c33; color: #fff; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
    .title { font-size: 18px; font-weight: bold; }
    .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: right; }
    th { background: #f8fafc; }
    .footer { margin-top: 24px; font-size: 11px; color: #64748b; text-align: center; }
    .qr { width: 80px; height: 80px; border: 1px dashed #94a3b8; text-align: center; line-height: 80px; color: #94a3b8; }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">جاب‌آزمون — فاکتور رسمی</div>
    <div>شماره: {{ $invoiceNumber }} | تاریخ: {{ $date }}</div>
  </div>

  <div class="box">
    <strong>فروشنده:</strong> جاب‌آزمون<br>
    شناسه ملی: --- | آدرس: تهران
  </div>

  <div class="box">
    <strong>خریدار:</strong> {{ $user?->name ?: 'کاربر' }}<br>
    موبایل: {{ $user?->mobile }} | ایمیل: {{ $user?->email ?: '—' }}
  </div>

  <table>
    <thead>
      <tr>
        <th>ردیف</th>
        <th>شرح کالا/خدمت</th>
        <th>تعداد</th>
        <th>مبلغ واحد (ریال)</th>
        <th>مبلغ کل (ریال)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>۱</td>
        <td>{{ $description }}</td>
        <td>۱</td>
        <td>{{ number_format($original) }}</td>
        <td>{{ number_format($original) }}</td>
      </tr>
      <tr>
        <td colspan="4">تخفیف</td>
        <td>{{ number_format($discount) }}</td>
      </tr>
      <tr>
        <td colspan="4"><strong>مبلغ قابل پرداخت</strong></td>
        <td><strong>{{ number_format($final) }}</strong></td>
      </tr>
    </tbody>
  </table>

  <div style="margin-top:16px;" class="qr">QR</div>

  <div class="footer">
    این فاکتور به صورت الکترونیکی صادر شده و نیاز به مهر و امضا ندارد.
  </div>
</body>
</html>
