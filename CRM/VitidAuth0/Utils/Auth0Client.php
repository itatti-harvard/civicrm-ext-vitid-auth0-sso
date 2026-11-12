<?php

use Auth0\SDK\Auth0 as Auth0SDK;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Store\MemoryStore;
use CRM_VitidAuth0_Utils_DatabaseStore as DatabaseStore;

/**
 * Wrapper class for Auth0 SDK.
 */
class CRM_VitidAuth0_Utils_Auth0Client {

  /**
   * Cookie secret salt suffix (used with client secret for deterministic but secure cookie secret).
   */
  const COOKIE_SECRET_SALT = 'vitid_auth0_cookie_salt';

  /**
   * @var Auth0SDK
   */
  private $auth0;

  /**
   * @var array
   */
  private $config;

  /**
   * Get the callback URL dynamically.
   *
   * @return string
   */
  private function getCallbackUrl() {
    return CRM_Utils_System::url('civicrm/vitid-auth0/callback', '', TRUE, NULL, FALSE);
  }

  /**
   * Get Auth0 namespace URL for a given claim name.
   *
   * @param string $claimName Claim name (e.g., 'roles', 'app_metadata')
   * @return string Namespace URL
   */
  private function getAuth0NamespaceUrl($claimName) {
    return 'https://' . $this->config['domain'] . '/' . $claimName;
  }

  /**
   * Check if debug logging is enabled.
   *
   * @return bool
   */
  private function isDebugEnabled() {
    return (bool) Civi::settings()->get('vitid_auth0_debug');
  }

  /**
   * Log debug message if debug logging is enabled.
   *
   * @param string $message
   */
  private function debugLog($message) {
    if ($this->isDebugEnabled()) {
      \Civi::log()->debug($message);
    }
  }

  /**
   * Constructor.
   *
   * @throws \Exception
   */
  public function __construct() {
    $this->config = [
      'domain' => Civi::settings()->get('vitid_auth0_domain'),
      'clientId' => Civi::settings()->get('vitid_auth0_client_id'),
      'clientSecret' => Civi::settings()->get('vitid_auth0_client_secret'),
    ];

    // Validate configuration
    if (empty($this->config['domain']) || empty($this->config['clientId']) || empty($this->config['clientSecret'])) {
      throw new \Exception('VIT ID authentication is not properly configured. Please check the settings.');
    }

    // Get callback URL dynamically
    $callbackUrl = $this->getCallbackUrl();
    
    // Generate a cookie secret based on client secret (deterministic but secure)
    $cookieSecret = hash('sha256', $this->config['clientSecret'] . self::COOKIE_SECRET_SALT);

    // Initialize Auth0 SDK
    $configuration = new SdkConfiguration([
      'domain' => $this->config['domain'],
      'clientId' => $this->config['clientId'],
      'clientSecret' => $this->config['clientSecret'],
      'redirectUri' => $callbackUrl,
      'cookieSecret' => $cookieSecret,
      'scope' => ['openid', 'profile', 'email'],
      // State, nonce and PKCE verifier must survive the round trip to Auth0,
      // so they go in the database, scoped to this browser.
      'transientStorage' => new DatabaseStore(),
      // The SDK's own session (user profile and tokens) is only read in the
      // callback request that creates it; CiviCRM's session takes over from
      // there. Keeping it in memory means tokens are never written anywhere,
      // and the SDK clearing it cannot touch the transient values above.
      'sessionStorage' => new MemoryStore(),
    ]);

    $this->auth0 = new Auth0SDK($configuration);
  }

  /**
   * Get the Auth0 login URL.
   *
   * @return string
   */
  public function getLoginUrl() {
    $this->debugLog('VIT ID getLoginUrl - Generating login URL via SDK');
    // The SDK handles state, nonce, and PKCE generation automatically
    return $this->auth0->login();
  }

  /**
   * Handle the callback from Auth0.
   *
   * @param array $params GET parameters from callback
   * @return array User data
   * @throws \Exception
   */
  public function handleCallback($params) {
    $this->debugLog('VIT ID handleCallback - Processing callback via SDK');

    // Check for error in params first
    if (!empty($params['error'])) {
      $errorDescription = $params['error_description'] ?? $params['error'];
      throw new \Exception('VIT ID authentication failed: ' . json_encode((string) $errorDescription));
    }

    try {
      // Exchange authorization code for tokens
      // This method automatically:
      // 1. Validates state
      // 2. Exchanges code for tokens (PKCE)
      // 3. Verifies ID token signature (using cached JWKS)
      // 4. Validates ID token claims (iss, aud, exp, nonce)
      $this->auth0->exchange();
      
      $this->debugLog('VIT ID handleCallback - Token exchange and validation successful');

      // Get user profile from ID token
      $user = $this->auth0->getUser();
      
      if (!$user) {
        throw new \Exception('Failed to retrieve user information from Auth0.');
      }

      $this->debugLog('VIT ID handleCallback - User retrieved successfully');
      
      // Log user data (masked) if debug enabled
      if ($this->isDebugEnabled()) {
        $debugUser = $user;
        if (isset($debugUser['sub'])) {
          $debugUser['sub'] = substr($debugUser['sub'], 0, 10) . '...';
        }
        $this->debugLog('VIT ID handleCallback - User data keys: ' . json_encode(array_keys($user)));
      }
      
      // Extract user data in the format expected by RoleMapper
      $userData = [
        'auth0_id' => $user['sub'] ?? NULL,
        'email' => $user['email'] ?? NULL,
        'name' => $user['name'] ?? NULL,
        'roles' => $user['roles'] ?? [],
        'app_metadata' => $user['app_metadata'] ?? [],
      ];
      
      // Handle namespaced roles (same logic as before, but adapted for SDK user array)
      // First, check the namespace generated from configured domain
      $namespacedRolesKey = $this->getAuth0NamespaceUrl('roles');
      if (isset($user[$namespacedRolesKey]) && is_array($user[$namespacedRolesKey])) {
        $this->debugLog('VIT ID handleCallback - Found namespaced roles at ' . $namespacedRolesKey);
        if (empty($userData['roles'])) {
          $userData['roles'] = $user[$namespacedRolesKey];
        }
      }
      
      // If still no roles found, check all keys for any namespace ending with /roles
      if (empty($userData['roles'])) {
        foreach ($user as $key => $value) {
          if (is_string($key) && (strpos($key, 'http://') === 0 || strpos($key, 'https://') === 0)) {
            if (preg_match('#/roles$#', $key) && is_array($value) && !empty($value)) {
              $this->debugLog('VIT ID handleCallback - Found roles in alternative namespace: ' . $key);
              $userData['roles'] = $value;
              break;
            }
          }
        }
      }
      
      $this->debugLog('VIT ID handleCallback - Final extracted roles: ' . json_encode($userData['roles']));

      // Extract civicrm_id from app_metadata
      if (isset($user['app_metadata']['civicrm_id'])) {
        $userData['civicrm_id'] = $user['app_metadata']['civicrm_id'];
      } elseif (isset($user['https://civicrm.org/app_metadata']['civicrm_id'])) {
        $userData['civicrm_id'] = $user['https://civicrm.org/app_metadata']['civicrm_id'];
      } else {
        // Check for custom namespace
        $namespacedAppMetadataKey = $this->getAuth0NamespaceUrl('app_metadata');
        if (isset($user[$namespacedAppMetadataKey]['civicrm_id'])) {
          $userData['civicrm_id'] = $user[$namespacedAppMetadataKey]['civicrm_id'];
        } else {
          // Check all keys for any namespace ending with /app_metadata
          foreach ($user as $key => $value) {
            if (is_string($key) && (strpos($key, 'http://') === 0 || strpos($key, 'https://') === 0)) {
              if (preg_match('#/app_metadata$#', $key) && is_array($value) && isset($value['civicrm_id'])) {
                $this->debugLog('VIT ID handleCallback - Found civicrm_id in alternative namespace: ' . $key);
                $userData['civicrm_id'] = $value['civicrm_id'];
                break;
              }
            }
          }
        }
      }

      return $userData;

    } catch (\Exception $e) {
      Civi::log()->error('VIT ID callback error: ' . $e->getMessage());
      throw new \Exception('Failed to complete VIT ID authentication: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Fetch roles from Auth0 using Management API.
   *
   * @return array List of roles
   * @throws \Exception
   */
  public function fetchRoles() {
    try {
      // Get Management API token
      $managementToken = $this->getManagementApiToken();

      // Fetch roles from Auth0
      $rolesUrl = "https://{$this->config['domain']}/api/v2/roles";
      
      $ch = curl_init($rolesUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $managementToken,
        'Content-Type: application/json',
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($httpCode !== 200) {
        throw new \Exception('Failed to fetch roles from VIT ID. HTTP Code: ' . $httpCode);
      }

      $roles = json_decode($response, true);
      
      if (!is_array($roles)) {
        throw new \Exception('Invalid response from VIT ID API.');
      }

      return $roles;

    } catch (\Exception $e) {
      Civi::log()->error('VIT ID fetch roles error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Get Management API token.
   *
   * @return string
   * @throws \Exception
   */
  private function getManagementApiToken() {
    // Note: We could potentially use the SDK for this too if we configured it for Management API
    // But for now, keeping the existing working curl implementation for Management API is fine
    // as it's separate from the authentication flow we're refactoring.
    
    $tokenUrl = "https://{$this->config['domain']}/oauth/token";
    
    $data = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->config['clientId'],
      'client_secret' => $this->config['clientSecret'],
      'audience' => "https://{$this->config['domain']}/api/v2/",
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
      throw new \Exception('Failed to get Management API token. HTTP Code: ' . $httpCode);
    }

    $tokenData = json_decode($response, true);
    
    if (empty($tokenData['access_token'])) {
      throw new \Exception('Management API token not received.');
    }

    return $tokenData['access_token'];
  }

  /**
   * Logout from Auth0.
   *
   * @return string Logout URL
   */
  public function getLogoutUrl() {
    // Same return URL as the logout handler in vitid_auth0.php; it must be
    // listed in the Auth0 application's Allowed Logout URLs.
    $returnTo = CRM_Utils_System::url('civicrm/login', 'reset=1', TRUE, NULL, FALSE);
    return $this->auth0->logout($returnTo);
  }
}
