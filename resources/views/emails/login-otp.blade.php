<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Login Verification Code</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
        }

        table {
            border-collapse: separate;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            color: #4f46e5;
            text-decoration: none;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }

            .content-cell {
                padding: 28px 20px !important;
            }

            .otp-digits {
                font-size: 32px !important;
                letter-spacing: 8px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 32px 12px; background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">

    <!-- Email Outer Wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background-color: #f1f5f9; width: 100%;">
        <tr>
            <td align="center" style="padding: 10px 0;">

                <!-- Main Card Container -->
                <table role="presentation" class="email-container" width="560" cellpadding="0" cellspacing="0"
                    border="0"
                    style="max-width: 560px; width: 100%; background-color: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04); border: 1px solid #e2e8f0;">

                    <!-- Decorative Top Brand Bar -->
                    <tr>
                        <td
                            style="height: 6px; background: linear-gradient(90deg, #4f46e5 0%, #6366f1 50%, #8b5cf6 100%); line-height: 6px; font-size: 6px;">
                            &nbsp;</td>
                    </tr>

                    <!-- Header with Brand & Logo -->
                    <tr>
                        <td align="center" style="padding: 34px 36px 20px 36px; text-align: center;">
                            <!-- Icon / Brand Badge -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
                                style="margin: 0 auto 16px auto;">
                                <tr>
                                    <td align="center"
                                        style="width: 52px; height: 52px; background-color: #eef2ff; background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); border-radius: 14px; border: 1px solid #c7d2fe; text-align: center; vertical-align: middle;">
                                        <!-- Lock/Shield Icon -->
                                        <img src="{{ asset('assets/images/4.png') }}" alt="Logo" width="36" height="36" style="display: block; margin: 0 auto; border: 0;" />
                                    </td>
                                </tr>
                            </table>

                            <h2
                                style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.025em; line-height: 1.3;">
                                Mostech HRMS
                            </h2>
                            <p
                                style="margin: 4px 0 0 0; font-size: 13px; font-weight: 500; color: #64748b; letter-spacing: 0.02em; text-transform: uppercase;">
                                Secure Account Access
                            </p>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 36px;">
                            <div style="height: 1px; background-color: #f1f5f9; width: 100%;"></div>
                        </td>
                    </tr>

                    <!-- Main Content Body -->
                    <tr>
                        <td class="content-cell" style="padding: 32px 38px 30px 38px;">

                            <!-- Greeting -->
                            <h3 style="margin: 0 0 12px 0; font-size: 17px; font-weight: 700; color: #0f172a;">
                                Hello, {{ $userName }} 👋
                            </h3>

                            <p style="margin: 0 0 24px 0; font-size: 14.5px; line-height: 1.65; color: #475569;">
                                We received a request to log in to your account. Please use the one-time verification
                                code below to complete your authentication:
                            </p>

                            <!-- Hero OTP Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="margin: 26px 0; background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); border: 1.5px solid #e0e7ff; border-radius: 14px; text-align: center;">
                                <tr>
                                    <td style="padding: 26px 20px 22px 20px;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: #6366f1; margin-bottom: 12px;">
                                            Verification Code
                                        </div>

                                        <!-- The 6-digit OTP code -->
                                        <div class="otp-digits"
                                            style="display: inline-block; font-family: 'JetBrains Mono', 'SF Mono', Consolas, Menlo, Monaco, monospace; font-size: 36px; font-weight: 800; letter-spacing: 12px; color: #1e1b4b; background-color: #ffffff; padding: 12px 18px 12px 28px; border-radius: 10px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);">
                                            {{ $otp }}
                                        </div>

                                        <!-- Expiry pill -->
                                        <div style="margin-top: 14px;">
                                            <span
                                                style="display: inline-block; background-color: #fee2e2; color: #991b1b; font-size: 11.5px; font-weight: 600; padding: 4px 12px; border-radius: 20px;">
                                                ⏱️ Expires in {{ $expiresInMinutes }} minutes
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Instructions -->
                            <p
                                style="margin: 0 0 22px 0; font-size: 13.5px; line-height: 1.6; color: #64748b; text-align: center;">
                                Enter this 6-digit code on the sign-in screen to verify your identity.
                            </p>

                            <!-- Security Checklist & Alert -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="background-color: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; margin-top: 26px;">
                                <tr>
                                    <td style="padding: 14px 16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td valign="top"
                                                    style="padding-right: 10px; font-size: 15px; line-height: 1;">
                                                    🔒
                                                </td>
                                                <td style="font-size: 12.5px; line-height: 1.5; color: #92400e;">
                                                    <strong>Security Notice:</strong> Never share this code with anyone.
                                                    Our support team will never ask for your verification code. If you
                                                    did not make this request, your account may be at risk—please
                                                    contact your IT administrator.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding: 24px 38px 30px 38px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                            <p style="margin: 0 0 8px 0; font-size: 12px; color: #64748b;">
                                Need help? Contact your internal HR or IT support team.
                            </p>
                            <p style="margin: 0; font-size: 11.5px; color: #94a3b8; line-height: 1.5;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'Mostech HRMS') }}. All rights
                                reserved.<br>
                                This is an automated notification. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- /Main Card Container -->

            </td>
        </tr>
    </table>

</body>

</html>