<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $buttonLabel }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f0fdfa; font-family:Segoe UI, Helvetica, Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdfa; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 16px rgba(15,118,110,0.08);">

          <tr>
            <td style="background-color:#ffffff; padding:28px 32px 20px 32px; text-align:center; border-bottom:1px solid #ecfdf5;">
              <img src="{{ $logoUrl }}" width="320" alt="NutriEvo" style="display:block; margin:0 auto; max-width:100%; height:auto;">
            </td>
          </tr>

          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px 0; font-size:15px; color:#1e293b;">{{ $greeting }}</p>

              <p style="margin:0 0 16px 0; font-size:15px; color:#334155; line-height:1.6;">
                {{ $pushBody }}
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
                <tr>
                  <td style="border-radius:10px; background-color:#059669;">
                    <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                      {{ $buttonLabel }}
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:16px 0 0 0; font-size:13px; color:#94a3b8; line-height:1.6;">
                {{ $disableHint }}
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 32px; background-color:#f8fafc; text-align:center;">
              <p style="margin:0; font-size:11px; color:#94a3b8;">{{ $footer }}</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
