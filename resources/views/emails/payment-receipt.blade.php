@component('emails.layout', ['subject' => 'رسید پرداخت'])
  <p>سلام {{ $name ?: 'کاربر عزیز' }}،</p>
  <p>پرداخت شما با موفقیت ثبت شد.</p>
  <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;">
    <tr>
      <td style="padding:12px 16px;color:#64748b;font-size:12px;">شماره فاکتور</td>
      <td style="padding:12px 16px;text-align:left;font-weight:bold;">{{ $invoiceNumber }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;color:#64748b;font-size:12px;background:#f8fafc;">مبلغ</td>
      <td style="padding:12px 16px;text-align:left;font-weight:bold;background:#f8fafc;">{{ number_format($amount) }} ریال</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;color:#64748b;font-size:12px;">شرح</td>
      <td style="padding:12px 16px;text-align:left;">{{ $description }}</td>
    </tr>
    <tr>
      <td style="padding:12px 16px;color:#64748b;font-size:12px;background:#f8fafc;">زمان</td>
      <td style="padding:12px 16px;text-align:left;background:#f8fafc;">{{ $paidAt }}</td>
    </tr>
  </table>
  <p style="text-align:center;margin:24px 0;">
    <a href="{{ $invoiceUrl }}" style="display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;">
      مشاهده کیف پول / فاکتور
    </a>
  </p>
@endcomponent
