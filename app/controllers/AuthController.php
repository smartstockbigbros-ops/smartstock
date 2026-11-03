<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Real, server-side auth for SmartStock: local username/password (users
 * table), Google sign-in, and forgot-password - replacing the old
 * localStorage-only demo login (public/assets/js/auth.js, now unused).
 * See app/libraries/Auth.php and app/libraries/GoogleAuth.php.
 *
 * NOTE: this framework's redirect() helper has a no-op `?? exit()` line
 * (operator precedence: `$exit === true` is never null, so the right side
 * of `??` never runs) - it does NOT stop execution on its own. Every
 * redirect() below is followed by an explicit `exit;` for that reason.
 */
class AuthController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->call->library('session');
        $this->call->library('auth');
    }

    public function login() {
        if ($this->auth->is_logged_in()) { redirect('dashboard'); exit; }

        $errors = [];
        $username = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) $this->io->post('username'));
            $password = (string) $this->io->post('password');
            if ($username === '' || $password === '') {
                $errors['form'] = 'Enter your username/email and password.';
            } elseif ($this->auth->login($username, $password)) {
                redirect('dashboard'); exit;
            } else {
                $errors['form'] = 'Invalid username/email or password.';
            }
        }
        $this->call->view('smartstock/auth', $this->auth_view_data('login', $errors, ['username' => $username]));
    }

    public function register() {
        if ($this->auth->is_logged_in()) { redirect('dashboard'); exit; }

        $errors = [];
        $data = ['username' => '', 'email' => ''];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data['username'] = trim((string) $this->io->post('username'));
            $data['email']    = trim((string) $this->io->post('email'));
            $password = (string) $this->io->post('password');
            $confirm  = (string) $this->io->post('confirm');

            if ($password !== $confirm) {
                $errors['confirm'] = 'Passwords do not match.';
            } else {
                $result = $this->auth->register($data['username'], $data['email'], $password);
                if ($result['ok']) { redirect('dashboard'); exit; }
                $errors = $result['errors'];
            }
        }
        $this->call->view('smartstock/auth', $this->auth_view_data('register', $errors, $data));
    }

    public function logout() {
        $this->auth->logout();
        redirect('login'); exit;
    }

    /* ---------- forgot password ---------- */

    public function forgot() {
        $sent = false;
        $reset_link = null; // shown on-screen only because this prototype has no SMTP configured - see note in the view
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) $this->io->post('email'));
            $result = $this->auth->request_password_reset($email);
            $sent = true;
            if (!empty($result['token'])) {
                $reset_link = site_url('reset-password/' . $result['token']);
                $this->try_send_reset_email($result['user']['email'], $result['user']['username'], $reset_link);
            }
        }
        $this->call->view('smartstock/forgot', ['sent' => $sent, 'reset_link' => $reset_link]);
    }

    public function reset($token = '') {
        $row = $this->auth->verify_reset_token($token);
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
            $password = (string) $this->io->post('password');
            $confirm  = (string) $this->io->post('confirm');
            if ($password !== $confirm) {
                $errors['confirm'] = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $errors['password'] = 'Password must be at least 6 characters.';
            } elseif ($this->auth->reset_password($token, $password)) {
                $this->session->set_flashdata('login_msg', 'Password updated - log in with your new password.');
                redirect('login'); exit;
            } else {
                $errors['form'] = 'That reset link just expired. Request a new one.';
            }
        }
        $this->call->view('smartstock/reset', ['token' => $token, 'valid' => (bool) $row, 'errors' => $errors]);
    }

    /* ---------- Google sign-in ---------- */

    /**
     * Starts the Google flow. ?mode=login (default) only signs in existing accounts;
     * ?mode=register is the "Sign up with Google" path and creates the account.
     */
    public function google() {
        $this->call->library('googleauth');
        $mode = (($_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
        $back = $mode === 'register' ? 'register' : 'login';

        if (!$this->googleauth->is_configured()) {
            $this->session->set_flashdata('login_msg', 'Google sign-in is not configured on this server yet (missing GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI in .env).');
            redirect($back); exit;
        }
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('oauth_state', $state);
        $this->session->set_userdata('oauth_mode', $mode);
        redirect($this->googleauth->auth_url($state)); exit;
    }

    public function google_callback() {
        $this->call->library('googleauth');
        $state = (string) ($_GET['state'] ?? '');
        $code  = (string) ($_GET['code'] ?? '');
        $expected = $this->session->userdata('oauth_state');
        $mode     = $this->session->userdata('oauth_mode') === 'register' ? 'register' : 'login';
        $this->session->unset_userdata('oauth_state');
        $this->session->unset_userdata('oauth_mode');

        if (isset($_GET['error'])) { $this->google_fail('Google sign-in was cancelled.', $mode); return; }
        if ($code === '' || !$expected || !hash_equals((string) $expected, $state)) { $this->google_fail('Google sign-in failed (invalid state). Please try again.', $mode); return; }

        $profile = $this->googleauth->handle_callback($code);
        if (!$profile) {
            $why = trim((string) $this->googleauth->last_error);
            $this->google_fail('Could not verify that Google account.' . ($why !== '' ? ' ' . $why : ' Please try again.'), $mode); return;
        }

        // Never link/create by an email Google has not verified.
        if ($profile['email'] === '' || empty($profile['email_verified'])) {
            $this->google_fail('That Google account has no verified email address, so it cannot be used here.', $mode); return;
        }

        $result = $this->auth->login_with_google(
            $profile['sub'], $profile['email'], $profile['name'], $profile['picture'],
            $mode === 'register'
        );

        switch ($result) {
            case 'ok':
            case 'created':
                redirect('dashboard'); exit;
            case 'no_account':
                $this->session->set_flashdata('login_msg', 'No SmartStock account uses that Google account yet. Sign up with Google to create one.');
                redirect('register'); exit;
            default: // 'disabled'
                $this->google_fail('That account is disabled.', $mode); return;
        }
    }

    private function google_fail($msg, $mode = 'login') {
        $this->session->set_flashdata('login_msg', $msg);
        redirect($mode === 'register' ? 'register' : 'login'); exit;
    }

    /* ---------- helpers ---------- */

    private function auth_view_data($mode, $errors, $data) {
        $this->call->library('googleauth');
        return [
            'mode' => $mode, 'errors' => $errors, 'data' => $data,
            'flash' => $this->session->flashdata('login_msg'),
            'google_enabled' => $this->googleauth->is_configured(), // informational; the button is always shown so the setup hint is discoverable
        ];
    }

    /** Best-effort only: most local dev setups have no mail server, so the reset link is shown on-screen regardless. */
    private function try_send_reset_email($to, $username, $link) {
        try {
            $this->call->library('email');
            $this->email->sender(getenv('MAIL_FROM') ?: 'no-reply@smartstock.local', 'SmartStock')
                ->recipient($to)
                ->subject('Reset your SmartStock password')
                ->email_content(
                    '<p>Hi ' . html_escape($username) . ',</p><p>Click below to set a new password (expires in 1 hour):</p>' .
                    '<p><a href="' . html_escape($link) . '">' . html_escape($link) . '</a></p>',
                    'html'
                )->send();
        } catch (\Throwable $e) {
            // No SMTP configured - fine, the view shows the link directly instead.
        }
    }
}
