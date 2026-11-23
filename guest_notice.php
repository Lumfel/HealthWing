<?php
/**
 * Shared "please sign in"notice for pages that require an owner login.
 * Renders a complete, self-contained styled page (inline CSS, no
 * dependency on vendor stylesheets) so it never shows as raw unstyled
 * text, even if something else about the page fails.
 *
 * Usage: require this, then call show_guest_notice('message'); exit();
 */
function show_guest_notice(string $message = "You need to sign in to view this page.")
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content=" width=device-width, initial-scale=1.0">
        <title>Sign in required · HealthWing</title>
        <style>
            body {
                margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
                background: #F6FAFA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            }
            .notice-card {
                background: #fff; border-radius: 16px; padding: 36px 32px; max-width: 380px; width: 90%;
                text-align: center; box-shadow: 0 12px 36px rgba(0,0,0,0.1); border: 1px solid #E3EBEC;
            }
            .notice-icon {
                width: 52px; height: 52px; border-radius: 50%; background: #E7F3F7; color: #4A9DBB;
                display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto;
            }
            .notice-card h1 { font-size: 18px; color: #16262A; margin: 0 0 8px 0; }
            .notice-card p { font-size: 14px; color: #62787B; margin: 0 0 22px 0; line-height: 1.5; }
            .notice-card a {
                display: inline-block; background: #4A9DBB; color: #fff; text-decoration: none;
                padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px;
            }
            .notice-card a:hover { background: #3D8CA8; }
        </style>
    </head>
    <body>
        <div class="notice-card">
            <div class="notice-icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            </div>
            <h1>Sign in required</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="/Sign%20in%20html.html">Go to Sign In</a>
        </div>
    </body>
    </html>
    <?php
}
