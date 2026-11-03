<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1829">
    <title>Forgot password · SmartStock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/smartstock.css') ?>">
</head>
<body class="auth-body">
<main class="auth-card card">
    <div class="auth-brand">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#17a9b8" stroke-width="2.2" stroke-linecap="round"><path d="M3 7h9"/><path d="M3 12h13"/><path d="M3 17h9"/><path d="M17 6.5a4 4 0 0 1 4 0M18.3 8.3a2 2 0 0 1 1.4 0" stroke-width="1.8"/></svg>
        <span>SmartStock</span>
    </div>
    <h1>Reset your password</h1>
    <p class="auth-sub">Enter the email on your account and we'll send a reset link.</p>

    <?php if ($sent): ?>
        <div class="notice">If that email has an account, a reset link has been sent.</div>
        <?php if ($reset_link): ?>
            <div class="notice" style="margin-top:10px;background:#fff6dd;border-color:#f0dca0;color:#7a5a00">
                No mail server is configured in this environment, so here's the link directly (expires in 1 hour):<br>
                <a class="link" href="<?= html_escape($reset_link) ?>"><?= html_escape($reset_link) ?></a>
            </div>
        <?php endif; ?>
        <p class="auth-switch"><a class="link" href="<?= site_url('login') ?>">Back to log in</a></p>
    <?php else: ?>
        <form method="post" action="<?= site_url('forgot-password') ?>" class="inv-form" novalidate>
            <label>Email<input name="email" type="email" autocomplete="email" autofocus required></label>
            <button class="btn btn-teal auth-submit" type="submit">Send reset link</button>
        </form>
        <p class="auth-switch"><a class="link" href="<?= site_url('login') ?>">Back to log in</a></p>
    <?php endif; ?>
</main>
</body>
</html>
