<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recupera la teva contrasenya</title>
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
                Hem rebut una sol·licitud per restablir la contrasenya del teu compte. Per continuar, fes clic al botó següent:
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
                <tr>
                  <td style="border-radius:10px; background-color:#059669;">
                    <a href="{{ $resetUrl }}" target="_blank" style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                      Restablir contrasenya
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:16px 0; font-size:13px; color:#64748b; line-height:1.6;">
                Per motius de seguretat, aquest enllaç només és vàlid durant <strong>{{ $expiresInMinutes }} minuts</strong>. Un cop transcorregut aquest temps, hauràs de tornar a sol·licitar-ne un de nou.
              </p>

              <p style="margin:16px 0 0 0; font-size:13px; color:#94a3b8; line-height:1.6;">
                Si no has demanat aquest canvi, pots ignorar aquest correu — la teva contrasenya actual seguirà sent vàlida.
              </p>

              <p style="margin:24px 0 0 0; font-size:12px; color:#cbd5e1; word-break:break-all;">
                Si el botó no funciona, copia i enganxa aquest enllaç al navegador:<br>
                <a href="{{ $resetUrl }}" style="color:#059669;">{{ $resetUrl }}</a>
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
