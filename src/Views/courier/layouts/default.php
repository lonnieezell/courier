<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title><?= esc($subject ?? '') ?></title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f5; font-family: Arial, Helvetica, sans-serif; }
        table { border-collapse: collapse; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f4f5">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden;">

                <!-- Header -->
                <tr>
                    <td bgcolor="#1e293b" style="padding: 24px 32px;">
                        <p style="margin:0;font-size:20px;font-weight:bold;color:#ffffff;">Your Company</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding: 32px; color: #1e293b; font-size: 15px; line-height: 1.6;">
                        <?= $content ?>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td bgcolor="#f8fafc" style="padding: 24px 32px; border-top: 1px solid #e2e8f0;">
                        <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5;">
                            You are receiving this email because you subscribed to our list.<br>
                            <a href="{courier_unsubscribe_url}" style="color:#94a3b8;">Unsubscribe</a>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
{courier_tracking_pixel}
</body>
</html>
