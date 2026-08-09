<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $subject ?? 'جاب‌آزمون' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Tahoma,Arial,sans-serif;direction:rtl;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="background:#0a1c33;padding:20px 24px;color:#fff;font-size:20px;font-weight:bold;">
              جاب‌آزمون
            </td>
          </tr>
          <tr>
            <td style="padding:28px 24px;color:#1f2937;font-size:14px;line-height:1.9;">
              {{ $slot }}
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;text-align:center;">
              © جاب‌آزمون — این ایمیل به‌صورت خودکار ارسال شده است.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
