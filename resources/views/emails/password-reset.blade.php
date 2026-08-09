@component('emails.layout', ['subject' => 'بازنشانی رمز عبور'])
  <p>سلام{{ $name ? ' '.$name : '' }}،</p>
  <p>برای بازنشانی رمز عبور روی دکمه زیر کلیک کنید. این لینک تا {{ $expiresMinutes }} دقیقه معتبر است.</p>
  <p style="margin-top:24px;">
    <a href="{{ $resetUrl }}" style="display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;">
      بازنشانی رمز عبور
    </a>
  </p>
  <p style="color:#64748b;font-size:12px;">اگر این درخواست را شما نداده‌اید، این ایمیل را نادیده بگیرید. ورود اصلی جاب‌آزمون با کد OTP موبایل است.</p>
@endcomponent
