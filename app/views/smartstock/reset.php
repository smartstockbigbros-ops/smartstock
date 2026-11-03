<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$err = function ($k) use ($errors) { return isset($errors[$k]) ? '<span class="field-err">' . html_escape($errors[$k]) . '</span>' : ''; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1829">
    <title>Set new password · SmartStock</title>
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
    <h1>Set a new password</h1>

    <?php if (!$valid): ?>
        <div class="auth-error" role="alert">That reset link is invalid or has expired.</div>
        <p class="auth-switch"><a class="link" href="<?= site_url('forgot-password') ?>">Request a new link</a></p>
    <?php else: ?>
        <?php if (!empty($errors['form'])): ?><div class="auth-error" role="alert"><?= html_escape($errors['form']) ?></div><?php endif; ?>
        <form method="post" action="<?= site_url('reset-password/' . $token) ?>" class="inv-form" novalidate>
            <label>New password<input name="password" type="password" autocomplete="new-password" autofocus required><?= $err('password') ?></label>
            <label>Confirm new password<input name="confirm" type="password" autocomplete="new-password" required><?= $err('confirm') ?></label>
            <button class="btn btn-teal auth-submit" type="submit">Update password</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
