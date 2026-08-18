@component('emails.layout', ['subject' => 'پیام جدید از فرم تماس'])
  <p>یک پیام جدید از فرم تماس دریافت شد:</p>
  @if (!empty($trackingCode))
    <p><strong>شماره پیگیری:</strong> <span dir="ltr">{{ $trackingCode }}</span></p>
  @endif
  <p><strong>نام:</strong> {{ $name }}</p>
  <p><strong>موبایل:</strong> <span dir="ltr">{{ $mobile ?? '—' }}</span></p>
  <p><strong>ایمیل:</strong> <span dir="ltr">{{ $email }}</span></p>
  <p><strong>موضوع:</strong> {{ $subjectLabel }}</p>
  <p><strong>پیام:</strong></p>
  <p style="white-space:pre-wrap;background:#f8fafc;padding:12px;border-radius:8px;">{{ $messageText }}</p>
@endcomponent
