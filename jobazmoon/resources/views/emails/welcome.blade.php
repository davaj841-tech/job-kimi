@component('emails.layout', ['subject' => 'به جاب‌آزمون خوش آمدید'])
  <p>سلام{{ $name ? ' '.$name : '' }}،</p>
  <p>به جاب‌آزمون خوش آمدید! حساب شما با موفقیت فعال شد.</p>
  <p><strong>شروع سریع:</strong></p>
  <ol>
    <li>یک آزمون رایگان را شروع کنید</li>
    <li>رزومه خود را بسازید</li>
    <li>آگهی‌های استخدام را بررسی کنید</li>
  </ol>
  <p style="margin-top:24px;">
    <a href="{{ $examUrl }}" style="display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;">
      رفتن به آزمون‌ها
    </a>
  </p>
@endcomponent
