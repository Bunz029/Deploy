<!DOCTYPE html>
<html lang="en" style="margin:0;padding:0;">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>ISU‑E Admin Password Reset</title>
  </head>
  <body style="margin:0;padding:0;background:#f3f4f6;color:#111827;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,'Apple Color Emoji','Segoe UI Emoji';">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f3f4f6;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;background:#ffffff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);overflow:hidden;">
            <tr>
              <td style="background:#10b981;padding:20px 24px;color:#ffffff;">
                <div style="text-align:center;margin-bottom:6px;">
                  <img src="{{ url('storage/email_icon/email_icon.png') }}" width="64" height="64" alt="Logo" style="display:inline-block;border-radius:12px;border:2px solid rgba(255,255,255,0.35);background:#ffffff;object-fit:cover;" />
                </div>
                <h1 style="margin:0;text-align:center;font-size:20px;line-height:1.4;font-weight:700;">ISU‑E Campus Map Admin</h1>
                <div style="opacity:.9;font-size:12px;text-align:center;">Secure Password Reset</div>
              </td>
            </tr>
            <tr>
              <td style="padding:28px 24px 8px 24px;">
                <h2 style="margin:0 0 8px 0;font-size:18px;line-height:1.4;color:#111827;">Reset your password</h2>
                <p style="margin:0 0 16px 0;font-size:14px;line-height:1.7;color:#374151;">
                  We received a request to reset the password for your admin account.
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">
                  <tr>
                    <td style="font-size:13px;color:#6b7280;">Email:</td>
                    <td style="font-size:13px;color:#111827;padding-left:8px;"><strong>{{ $email }}</strong></td>
                  </tr>
                  <tr>
                    <td style="font-size:13px;color:#6b7280;">Expires:</td>
                    <td style="font-size:13px;color:#111827;padding-left:8px;">{{ $expiresAt }}</td>
                  </tr>
                </table>
                <p style="margin:0 0 20px 0;font-size:14px;line-height:1.7;color:#374151;">Click the button below to set a new password:</p>
              </td>
            </tr>
            <tr>
              <td style="padding:0 24px 8px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                  <tr>
                    <td align="center" bgcolor="#10b981" style="border-radius:8px;">
                      <a href="{{ $resetUrl }}" target="_blank" style="display:inline-block;padding:12px 20px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:.2px;">Reset Password</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:8px 24px 24px 24px;">
                <p style="margin:0 0 10px 0;font-size:12px;line-height:1.6;color:#6b7280;">If the button doesn't work, copy and paste this link into your browser:</p>
                <div style="word-break:break-all;font-size:12px;color:#065f46;">
                  <a href="{{ $resetUrl }}" style="color:#065f46;text-decoration:underline;">{{ $resetUrl }}</a>
                </div>
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;" />
                <p style="margin:0;font-size:12px;color:#6b7280;">If you did not request a password reset, you can safely ignore this email.</p>
              </td>
            </tr>
            <tr>
              <td style="background:#f9fafb;padding:16px 24px;color:#6b7280;font-size:12px;text-align:center;">
                © {{ date('Y') }} ISU‑E Admin • This is an automated message
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
  </html>


