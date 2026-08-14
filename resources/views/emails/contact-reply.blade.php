@component('emails.layout', ['subject' => 'پاسخ پیام تماس'])
  <p>{{ $name }} عزیز،</p>
  <p>پاسخ پیام شما با شماره پیگیری <strong dir="ltr">{{ $trackingCode }}</strong>:</p>
  <p style="white-space:pre-wrap;background:#f8fafc;padding:12px;border-radius:8px;">{{ $replyText }}</p>
@endcomponent
