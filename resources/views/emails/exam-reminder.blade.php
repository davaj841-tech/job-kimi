@component('emails.layout', ['subject' => $subject])
  <p>سلام{{ $name ? ' '.$name : '' }}،</p>
  <p>یادآوری می‌کنیم آزمون مرتبط با آگهی <strong>{{ $title }}</strong> فردا برگزار می‌شود.</p>
  @if($examDate)
    <p>تاریخ آزمون: <strong>{{ $examDate }}</strong></p>
  @endif
  <p style="margin-top:24px;">
    <a href="{{ $url }}" style="display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;">
      مشاهده جزئیات
    </a>
  </p>
@endcomponent
