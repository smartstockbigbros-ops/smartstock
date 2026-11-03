<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Minimal "Sign in with Google" client (OAuth 2.0 authorization-code flow),
 * using plain cURL instead of Google's SDK so the project needs no Composer
 * dependency. Configure via .env:
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
 * Get these from https://console.cloud.google.com/apis/credentials
 * (OAuth client ID -> Web application). GOOGLE_REDIRECT_URI must exactly
 * match an "Authorized redirect URI" there, e.g.
 * http://127.0.0.1:3000/auth/google/callback
 */
class GoogleAuth
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    /** Human-readable reason the last Google call failed (shown to the user + logged). */
    public $last_error = '';

    public function __construct() {
        $this->client_id     = trim((string) getenv('GOOGLE_CLIENT_ID'));
        $this->client_secret = trim((string) getenv('GOOGLE_CLIENT_SECRET'));
        $this->redirect_uri  = trim((string) getenv('GOOGLE_REDIRECT_URI'));
    }

    /** True once all three .env values are set - the "Sign in with Google" button only shows when this is true. */
    public function is_configured() {
        return $this->client_id !== '' && $this->client_secret !== '' && $this->redirect_uri !== '';
    }

    /** URL to send the browser to. $state is an opaque CSRF token the caller must verify on callback. */
    public function auth_url($state) {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * Exchange the ?code=... from the callback for the signed-in Google profile.
     * @return array{sub:string,email:string,name:string,picture:?string,email_verified:bool}|null  null on any failure.
     */
    public function handle_callback($code) {
        $token = $this->_post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri'  => $this->redirect_uri,
            'grant_type'    => 'authorization_code',
        ]);
        if (!$token || empty($token['access_token'])) return null;

        $info = $this->_get('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
        if (!$info || empty($info['sub'])) return null;

        return [
            'sub'     => (string) $info['sub'],
            'email'   => (string) ($info['email'] ?? ''),
            'name'    => (string) ($info['name'] ?? ''),
            'picture' => $info['picture'] ?? null,
            // Google only vouches for the address when this is true. Callers must not
            // link/create accounts by email unless it is (account-takeover protection).
            'email_verified' => filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function _post($url, array $fields) {
        return $this->_request($url, function ($ch) use ($fields) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        });
    }

    private function _get($url, $bearer_token) {
        return $this->_request($url, function ($ch) use ($bearer_token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $bearer_token]);
        });
    }

    private function _request($url, callable $configure) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        // TLS verification. On Windows/Laragon PHP often has no CA bundle configured, which makes
        // every HTTPS call fail with "SSL certificate problem". Fix properly with one of:
        //   - GOOGLE_CA_BUNDLE=C:\laragon\etc\ssl\cacert.pem   (path to a cacert.pem), or
        //   - setting curl.cainfo / openssl.cafile in php.ini.
        // GOOGLE_SSL_VERIFY=false turns verification off - LOCAL DEVELOPMENT ONLY.
        $verify = strtolower(trim((string) getenv('GOOGLE_SSL_VERIFY')));
        if ($verify === 'false' || $verify === '0') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $ca = trim((string) getenv('GOOGLE_CA_BUNDLE'));
            if ($ca !== '' && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }

        $configure($ch);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->last_error = 'Could not reach Google: ' . $err
                . (stripos($err, 'SSL') !== false ? ' (set GOOGLE_CA_BUNDLE in .env to your cacert.pem, or GOOGLE_SSL_VERIFY=false for local dev only)' : '');
            error_log('[GoogleAuth] ' . $this->last_error);
            return null;
        }
        $data = json_decode($body, true);
        if ($code !== 200) {
            $detail = is_array($data) ? trim(($data['error'] ?? '') . ' ' . ($data['error_description'] ?? '')) : substr($body, 0, 120);
            $this->last_error = 'Google replied HTTP ' . $code . ($detail !== '' ? ': ' . $detail : '');
            error_log('[GoogleAuth] ' . $this->last_error);
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
