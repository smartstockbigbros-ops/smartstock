<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * Real, server-side login/register form - posts to AuthController::login()/
 * register() (see routes.php), which checks the `users` table via
 * app/libraries/Auth.php. Replaces the old localStorage-only demo
 * (public/assets/js/auth.js, no longer used).
 */
$reg = $mode === 'register';
$title = $reg ? 'Create account' : 'Log in';
$err = function ($k) use ($errors) { return isset($errors[$k]) ? '<span class="field-err">' . html_escape($errors[$k]) . '</span>' : ''; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1829">
    <title><?= $title ?> · SmartStock</title>
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
    <h1><?= $reg ? 'Create your account' : 'Welcome back' ?></h1>
    <p class="auth-sub"><?= $reg ? 'Register to start monitoring your hardware stock.' : 'Log in to open your dashboard.' ?></p>

    <?php if (!empty($flash)): ?><div class="notice" style="margin-bottom:14px"><?= html_escape($flash) ?></div><?php endif; ?>
    <?php if (!empty($errors['form'])): ?><div class="auth-error" role="alert"><?= html_escape($errors['form']) ?></div><?php endif; ?>

    <a class="btn btn-light google-btn" href="<?= site_url('auth/google') ?>?mode=<?= $reg ? 'register' : 'login' ?>">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.4 6.1 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.1 18.9 12 24 12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.4 6.1 29.5 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.4 26.7 36 24 36c-5.2 0-9.6-3.1-11.3-7.6l-6.5 5C9.4 39.6 16.1 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.2 5.2C40.5 36.3 44 30.8 44 24c0-1.3-.1-2.7-.4-3.5z"/></svg>
        <span><?= $reg ? 'Sign up with Google' : 'Log in with Google' ?></span>
    </a>
    <?php if (empty($google_enabled)): ?>
        <p class="auth-sub" style="margin:6px 0 0;font-size:12px">Google isn't set up on this server yet. Add the three GOOGLE_* values to <code>.env</code> to turn it on.</p>
    <?php endif; ?>
    <div class="auth-divider"><span>or</span></div>

    <form method="post" action="<?= site_url($reg ? 'register' : 'login') ?>" class="inv-form" novalidate>
        <?php if ($reg): ?>
            <label>Email<input name="email" type="email" value="<?= html_escape($data['email'] ?? '') ?>" autocomplete="email" required><?= $err('email') ?></label>
            <label>Username<input name="username" value="<?= html_escape($data['username'] ?? '') ?>" autocomplete="username" required><?= $err('username') ?></label>
        <?php else: ?>
            <label>Username or email<input name="username" value="<?= html_escape($data['username'] ?? '') ?>" autocomplete="username" autofocus required></label>
        <?php endif; ?>
        <label>Password<input name="password" type="password" autocomplete="<?= $reg ? 'new-password' : 'current-password' ?>" required><?= $err('password') ?></label>
        <?php if ($reg): ?>
        <label>Confirm password<input name="confirm" type="password" autocomplete="new-password" required><?= $err('confirm') ?></label>
        <?php endif; ?>
        <?php if (!$reg): ?><p class="auth-forgot"><a class="link" href="<?= site_url('forgot-password') ?>">Forgot password?</a></p><?php endif; ?>
        <button class="btn btn-teal auth-submit" type="submit"><?= $reg ? 'Create account' : 'Log in' ?></button>
    </form>

    <p class="auth-switch">
        <?php if ($reg): ?>Already have an account? <a class="link" href="<?= site_url('login') ?>">Log in</a>
        <?php else: ?>New here? <a class="link" href="<?= site_url('register') ?>">Create an account</a><?php endif; ?>
    </p>
</main>
</body>
</html>
