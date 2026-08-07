@component('emails.layout', ['subject' => 'اشتراک شما رو به انقضاست'])
  <p>سلام{{ $name ? ' '.$name : '' }}،</p>
  <p>اشتراک شما تا <strong>{{ $expiresAt }}</strong> منقضی می‌شود.</p>
  <p>با تمدید اشتراک، دسترسی به آزمون‌ها و امکانات ویژه حفظ می‌شود.</p>
  <p style="margin-top:24px;">
    <a href="{{ $renewUrl }}" style="display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;">
      تمدید اشتراک
    </a>
  </p>
@endcomponent
