<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Local (username + password) and Google sign-in, plus forgot-password
 * tokens, all against the `users` / `password_resets` tables
 * (database/smartstockdb.sql or migrations 001/005/006).
 */
class Auth
{
    protected $_lava;

    public function __construct() {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
        $this->_lava->call->library('session');
    }

    /* ---------- local account ---------- */

    /**
     * @return array{ok:bool, errors?:array, user?:array}
     */
    public function register($username, $email, $password, $role = 'user') {
        $errors = [];
        $username = trim((string) $username);
        $email    = trim(strtolower((string) $email));

        if ($username === '' || strlen($username) < 3) $errors['username'] = 'Username must be at least 3 characters.';
        elseif (!preg_match('/^[\w.\-]+$/', $username)) $errors['username'] = 'Username can only use letters, numbers, dot, dash and underscore.';
        elseif ($this->_find_one(['username' => $username])) $errors['username'] = 'That username is already taken.';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        elseif ($this->_find_one(['email' => $email])) $errors['email'] = 'An account with that email already exists.';

        if (strlen((string) $password) < 6) $errors['password'] = 'Password must be at least 6 characters.';

        if ($errors) return ['ok' => false, 'errors' => $errors];

        $id = $this->_lava->db->table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => $role,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $user = $this->_find_one(['id' => $id]);
        if ($user) $this->_start_session($user);
        return ['ok' => true, 'user' => $user];
    }

    /** Login by username OR email. */
    public function login($identifier, $password) {
        $identifier = trim((string) $identifier);
        $user = $this->_find_one(['username' => $identifier]) ?: $this->_find_one(['email' => strtolower($identifier)]);

        if (!$user || empty($user['password']) || !password_verify((string) $password, $user['password'])) {
            return false;
        }
        if (!(int) $user['is_active']) {
            return false;
        }
        $this->_start_session($user);
        return true;
    }

    public function is_logged_in() {
        return (bool) $this->_lava->session->userdata('logged_in');
    }

    public function logout() {
        $this->_lava->session->sess_destroy();
    }

    /* ---------- Google sign-in ---------- */

    /**
     * Sign in (or sign up) with a Google account.
     *
     * Matching order:
     *  1. google_id already linked                          -> that user
     *  2. a local account with the same (Google-verified) email -> link google_id onto it
     *  3. otherwise                                          -> create a new, password-less
     *     account, but ONLY when $allow_create is true (the "Sign up with Google" path).
     *
     * @return string 'ok' (session started) | 'no_account' (login-only and nothing matched)
     *                | 'disabled' (matched account is deactivated)
     *                | 'created' (new account made and signed in)
     */
    public function login_with_google($google_id, $email, $name, $avatar = null, $allow_create = true) {
        $email = trim(strtolower((string) $email));
        $created = false;

        $user = $this->_find_one(['google_id' => $google_id]);

        if (!$user && $email !== '') {
            $existing = $this->_find_one(['email' => $email]);
            if ($existing) {
                $this->_lava->db->table('users')->where('id', $existing['id'])->update([
                    'google_id' => $google_id,
                    'avatar'    => $avatar ?: ($existing['avatar'] ?? null),
                ]);
                $user = $this->_find_one(['id' => $existing['id']]);
            }
        }

        if (!$user) {
            if (!$allow_create) return 'no_account';

            $username = $this->_unique_username_from($email !== '' ? $email : $name);
            $id = $this->_lava->db->table('users')->insert([
                'username'   => $username,
                'email'      => $email !== '' ? $email : $username . '@users.noreply',
                'password'   => null,
                'google_id'  => $google_id,
                'avatar'     => $avatar,
                'role'       => 'user',
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $user = $this->_find_one(['id' => $id]);
            $created = true;
        }

        if (!$user || !(int) $user['is_active']) return 'disabled';
        $this->_start_session($user);
        return $created ? 'created' : 'ok';
    }

    /* ---------- forgot password ---------- */

    /**
     * Always returns ['ok'=>true] regardless of whether the email matched - the
     * caller shows the same generic message either way, so this can't be used to
     * probe which emails have accounts. The raw token (to put in the email/link)
     * is only returned when a matching, local (non-Google-only) account exists.
     */
    public function request_password_reset($email) {
        $email = trim(strtolower((string) $email));
        $user = $this->_find_one(['email' => $email]);
        if (!$user) return ['ok' => true, 'token' => null, 'user' => null];

        $token = bin2hex(random_bytes(32));
        $this->_lava->db->raw(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())',
            [(int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]
        );
        return ['ok' => true, 'token' => $token, 'user' => $user];
    }

    /** Null if the token is missing, expired, already used, or unknown. */
    public function verify_reset_token($token) {
        if (!is_string($token) || $token === '') return null;
        $row = $this->_lava->db->raw(
            'SELECT pr.*, u.username, u.email FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
             LIMIT 1',
            [hash('sha256', $token)]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return bool true if the password was changed. */
    public function reset_password($token, $new_password) {
        if (strlen((string) $new_password) < 6) return false;
        $row = $this->verify_reset_token($token);
        if (!$row) return false;

        $this->_lava->db->table('users')->where('id', $row['user_id'])->update([
            'password' => password_hash($new_password, PASSWORD_DEFAULT),
        ]);
        $this->_lava->db->raw('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $row['id']]);
        return true;
    }

    /* ---------- helpers ---------- */

    private function _start_session($user) {
        $this->_lava->session->set_userdata([
            'user_id'   => $user['id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'avatar'    => $user['avatar'] ?? null,
            'logged_in' => true,
        ]);
        // New session ID on every successful login/signup - old data stays, only the
        // ID (and its cookie) changes, which is what blocks session-fixation attacks.
        $this->_lava->session->after_successful_login();
    }

    /** One row as a plain assoc array, or null - normalises whatever db->where()->get() hands back. */
    private function _find_one($conditions) {
        $result = $this->_lava->db->table('users')->where($conditions)->get();
        if (is_array($result)) {
            if (isset($result['id'])) return $result;
            if (isset($result[0])) return is_object($result[0]) ? (array) $result[0] : $result[0];
            return null;
        }
        return is_object($result) ? (array) $result : null;
    }

    private function _unique_username_from($seed) {
        $base = preg_replace('/[^a-z0-9._\-]/', '', strtolower(strtok((string) $seed, '@'))) ?: 'user';
        $base = substr($base, 0, 90);
        $username = $base;
        $i = 1;
        while ($this->_find_one(['username' => $username])) {
            $username = $base . $i;
            $i++;
        }
        return $username;
    }
}
