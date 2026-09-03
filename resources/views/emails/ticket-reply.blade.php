@component('emails.layout', ['subject' => 'پاسخ پشتیبانی'])
  <p>سلام {{ $name ?: 'کاربر عزیز' }}،</p>
  <p>پاسخ جدیدی برای تیکت «{{ $ticketSubject }}» ثبت شد:</p>
  <div style="margin:16px 0;padding:14px 16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;white-space:pre-wrap;">{{ $replyMessage }}</div>
  <p style="text-align:center;margin:24px 0;">
    <a href="{{ $ticketUrl }}" style="display:inline-block;background:#0a1c33;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;">
      مشاهده تیکت
    </a>
  </p>
@endcomponent
