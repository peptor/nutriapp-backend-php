<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tens un missatge nou</title>
</head>
<body style="margin:0; padding:0; background-color:#f0fdfa; font-family:Segoe UI, Helvetica, Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdfa; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px; width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 16px rgba(15,118,110,0.08);">

          <tr>
            <td style="background:linear-gradient(135deg,#ecfdf5,#ccfbf1); padding:32px 32px 24px 32px; text-align:center;">
              <img src="{{ $logoUrl }}" width="56" height="56" alt="NutriEvo" style="display:block; margin:0 auto 12px auto; border-radius:12px;">
              <h1 style="margin:0; font-size:22px; color:#065f46; font-weight:700;">NutriEvo</h1>
              <p style="margin:4px 0 0 0; font-size:13px; color:#0f766e;">Seguiment clínic entre visites</p>
            </td>
          </tr>

          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px 0; font-size:15px; color:#1e293b;">Hola{{ $userName ? ' '.$userName : '' }},</p>

              <p style="margin:0 0 16px 0; font-size:15px; color:#334155; line-height:1.6;">
                <strong>{{ $senderName }}</strong> t'ha enviat un missatge nou. Per seguretat no l'incloem en aquest correu: entra a NutriEvo per llegir-lo.
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
                <tr>
                  <td style="border-radius:10px; background-color:#059669;">
                    <a href="{{ $messageUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                      Llegir el missatge
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:16px 0 0 0; font-size:13px; color:#94a3b8; line-height:1.6;">
                Pots desactivar aquests avisos a Configuració &rsaquo; Notificacions.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 32px; background-color:#f8fafc; text-align:center;">
              <p style="margin:0; font-size:11px; color:#94a3b8;">Aquest és un correu automàtic, si us plau no hi responguis.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
