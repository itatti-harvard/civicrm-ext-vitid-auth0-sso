<?php

use CRM_VitidAuth0_ExtensionUtil as E;

/**
 * Page for handling Auth0 callback.
 */
class CRM_VitidAuth0_Page_Callback extends CRM_Core_Page {

  public function run() {
    $debugEnabled = (bool) Civi::settings()->get('vitid_auth0_debug');
    
    try {
      // Step 1: Initialize Auth0 client
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 1: Initializing Auth0 client');
      }
      _vitid_auth0_load_vendor();
      $auth0Client = new CRM_VitidAuth0_Utils_Auth0Client();

      // Step 2: Get and validate callback parameters
      // Sanitize and validate OAuth callback parameters to prevent injection attacks
      $params = [];
      $allowedParams = ['code', 'state', 'error', 'error_description'];
      
      foreach ($allowedParams as $param) {
        if (isset($_GET[$param])) {
          // Validate that parameter is a string and sanitize it
          if (is_string($_GET[$param])) {
            // Sanitize: remove null bytes and trim whitespace
            $value = trim(str_replace("\0", '', $_GET[$param]));
            // Additional validation based on parameter type
            if ($param === 'state' && !empty($value)) {
              // State should be alphanumeric/hex (from our random_bytes generation)
              if (preg_match('/^[a-f0-9]+$/i', $value)) {
                $params[$param] = $value;
              } else {
                Civi::log()->warning('VIT ID Callback - Invalid state parameter format: ' . json_encode(substr($value, 0, 50), JSON_INVALID_UTF8_SUBSTITUTE));
                throw new Exception('Invalid state parameter format.');
              }
            } elseif ($param === 'code' && !empty($value)) {
              // Authorization code should be URL-safe alphanumeric
              if (preg_match('/^[A-Za-z0-9\-_]+$/', $value)) {
                $params[$param] = $value;
              } else {
                Civi::log()->warning('VIT ID Callback - Invalid code parameter format: ' . json_encode(substr($value, 0, 50), JSON_INVALID_UTF8_SUBSTITUTE));
                throw new Exception('Invalid authorization code format.');
              }
            } elseif (in_array($param, ['error', 'error_description'])) {
              // Only ever logged (JSON-encoded), never shown to the user
              $params[$param] = $value;
            }
          }
        }
      }
      
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 2: Received callback parameters: ' . json_encode(array_keys($params)));
      }

      // Step 3: Handle the callback and get user data
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 3: Processing Auth0 callback');
      }
      $userData = $auth0Client->handleCallback($params);
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 3: Successfully received user data from Auth0');
      }

      // Step 4: Validate user
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 4: Validating user data');
      }
      $validation = CRM_VitidAuth0_Utils_RoleMapper::validateUser($userData);
      
      if (!$validation['valid']) {
        // Display error on login page
        CRM_Core_Session::setStatus($validation['error'], E::ts('VIT ID Authentication Error'), 'error');
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/login', 'reset=1', TRUE, NULL, FALSE));
        return;
      }
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 4: User validation successful');
      }

      // Step 5: Get CiviCRM roles
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 5: Mapping Auth0 roles to CiviCRM roles');
      }
      $auth0Roles = $userData['roles'] ?? [];
      $civiRoles = CRM_VitidAuth0_Utils_RoleMapper::getCiviCRMRoles($auth0Roles);
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 5: Mapped to CiviCRM role IDs: ' . json_encode($civiRoles));
      }
      // Without mapped roles, signing in would keep whatever roles the user had
      // before, so refuse (a mapping removed since step 4, or a failed lookup).
      if (empty($civiRoles)) {
        Civi::log()->warning('VIT ID Callback - No CiviCRM roles mapped for contact ID ' . $userData['civicrm_id'] . ' at sign-in; refused');
        CRM_Core_Session::setStatus(
          E::ts('You don\'t have the required permissions to access CiviCRM. Please contact your administrator.'),
          E::ts('VIT ID Authentication Error'),
          'error'
        );
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/login', 'reset=1', TRUE, NULL, FALSE));
        return;
      }

      // Step 6: Create user session
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 6: Creating user session for contact ID: ' . $userData['civicrm_id']);
      }
      $sessionCreated = CRM_VitidAuth0_Utils_RoleMapper::createUserSession(
        $userData['civicrm_id'],
        $civiRoles
      );

      if (!$sessionCreated) {
        throw new Exception('Failed to create user session.');
      }
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Step 6: User session created successfully');
      }

      // Log successful login
      Civi::log()->info('VIT ID login successful for contact ID: ' . $userData['civicrm_id']);

      // Ensure session is saved before redirecting
      // CiviCRM 6.7.2 with Redis session handler compatibility
      // CRITICAL: Ensure session is started and has a session ID before redirect
      $session = CRM_Core_Session::singleton();

      // Sign-in gave this session a new ID, but the pre-sign-in session still
      // exists with the same browser id. Drop it here so a later login's state
      // can only come from this session.
      $session->set(CRM_VitidAuth0_Utils_DatabaseStore::SESSION_KEY, NULL);
      
      $sessionHandler = ini_get('session.save_handler');

      // Not the save path: for Redis it can contain the password
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Session handler: ' . $sessionHandler);
      }
      
      // Store session objects - this ensures CiviCRM session data is persisted
      // For Redis sessions, this should commit data to Redis
      $session->storeSessionObjects();
      
      // For Redis sessions, verify session data is set correctly
      // PHP's Redis session handler will auto-commit when script ends
      if ($sessionHandler === 'redis' && isset($_SESSION['CiviCRM'])) {
        if ($debugEnabled) {
          Civi::log()->debug('VIT ID Callback - Redis session: $_SESSION[CiviCRM] data verified');
        }
      }
      
      // Verify session state before redirect
      $sessionUserId = $session->get('userID');
      $sessionUfId = $session->get('ufID');
      $sessionId = session_id();
      $sessionName = session_name();
      $sessionCookieValue = $_COOKIE[$sessionName] ?? 'NOT_SET';
      
      // Always log session state (critical for debugging login issues)
      Civi::log()->info('VIT ID Callback - Session state before redirect - Session Handler: ' . $sessionHandler . ', Session ID: ' . ($sessionId ? 'SET' : 'NULL') . ', Session Cookie: ' . ($sessionCookieValue !== 'NOT_SET' ? 'SET' : 'NOT_SET') . ', userID: ' . ($sessionUserId ?? 'NULL') . ', ufID: ' . ($sessionUfId ?? 'NULL') . ', Expected Contact ID: ' . $userData['civicrm_id']);
      
      // Verify session was set correctly
      if ($sessionUserId != $userData['civicrm_id']) {
        Civi::log()->error('VIT ID Callback - CRITICAL: Session userID mismatch! Expected: ' . $userData['civicrm_id'] . ', Got: ' . ($sessionUserId ?? 'NULL'));
      }
      
      if ($debugEnabled) {
        // Log session cookie parameters
        if (function_exists('session_get_cookie_params')) {
          $cookieParams = session_get_cookie_params();
          Civi::log()->debug('VIT ID Callback - Session cookie params: ' . json_encode([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Not set'
          ]));
        }
        
        // Log CiviCRM session structure if it exists
        if (isset($_SESSION['CiviCRM'])) {
          $civiSessionKeys = array_keys($_SESSION['CiviCRM']);
          Civi::log()->debug('VIT ID Callback - CiviCRM session keys: ' . json_encode($civiSessionKeys));
        }
      }
      
      // Redirect to CiviCRM dashboard (without reset parameter to avoid redirect loops)
      // Using dashboard instead of just /civicrm as it's the standard post-login destination
      $redirectUrl = CRM_Utils_System::url('civicrm/dashboard', '', TRUE, NULL, FALSE);
      
      // Use the stored return URL once, and only if it points at this site
      $storedReturnUrl = $session->get('vitid_auth0_return_url');
      $session->set('vitid_auth0_return_url', NULL);
      if (!empty($storedReturnUrl)) {
        if ($debugEnabled) {
          Civi::log()->debug('VIT ID Callback - Found stored return URL: ' . json_encode($storedReturnUrl, JSON_INVALID_UTF8_SUBSTITUTE));
        }
        if (self::isLocalUrl($storedReturnUrl)) {
          $redirectUrl = $storedReturnUrl;
        }
        else {
          Civi::log()->warning('VIT ID Callback - Ignored potentially unsafe return URL: ' . json_encode($storedReturnUrl, JSON_INVALID_UTF8_SUBSTITUTE));
        }
      }
      
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Redirecting to: ' . $redirectUrl);
        Civi::log()->debug('VIT ID Callback - Session saved, about to redirect (session will remain open)');
      }
      
      CRM_Utils_System::redirect($redirectUrl);
      exit; // Prevent further execution and parent::run() call

    } catch (Exception $e) {
      // Log full error details including stack trace (never expose to users)
      // The message can quote values from the request or the token; keep it on one line
      $errorMessage = 'VIT ID callback error: ' . preg_replace('/[\x00-\x1F\x7F]/', ' ', $e->getMessage());
      $errorMessage .= ' | File: ' . $e->getFile() . ':' . $e->getLine();
      $errorMessage .= ' | Stack trace: ' . $e->getTraceAsString();
      Civi::log()->error($errorMessage);

      // Show users a generic message; full details are logged above.
      // A login whose stored state is gone (started in another window or on the
      // other hostname, or left on the VIT ID page past an hour) just needs a new start.
      if (self::loginMustRestart($e)) {
        $userErrorMessage = E::ts('Your VIT ID sign-in expired or was started in another window. Please sign in again.');
        $userErrorTitle = E::ts('Please Sign In Again');
      }
      else {
        $userErrorMessage = E::ts('An error occurred during VIT ID authentication. Please try again or contact your administrator.');
        $userErrorTitle = E::ts('VIT ID Authentication Error');
      }

      // In debug mode, log additional context but still don't expose to users
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Error details (debug mode): ' . $e->getMessage());
      }

      // Display error on login page
      CRM_Core_Session::setStatus(
        $userErrorMessage,
        $userErrorTitle,
        'error'
      );
      
      // Ensure session is saved before error redirect
      $session = CRM_Core_Session::singleton();
      $session->storeSessionObjects();
      
      $loginUrl = CRM_Utils_System::url('civicrm/login', 'reset=1', TRUE, NULL, FALSE);
      CRM_Utils_System::redirect($loginUrl);
      exit; // Prevent further execution and parent::run() call
    }

    // This should never be reached, but included for safety
    parent::run();
  }

  /**
   * Whether the callback failed because this browser's stored login values
   * (state, PKCE verifier, nonce) are missing or belong to another login.
   *
   * @param \Throwable $e
   * @return bool
   */
  private static function loginMustRestart(\Throwable $e) {
    $cause = $e->getPrevious();
    return $cause instanceof \Auth0\SDK\Exception\StateException
      && in_array($cause->getMessage(), [
        \Auth0\SDK\Exception\StateException::MSG_INVALID_STATE,
        \Auth0\SDK\Exception\StateException::MSG_MISSING_CODE_VERIFIER,
        \Auth0\SDK\Exception\StateException::MSG_MISSING_NONCE,
      ], TRUE);
  }

  /**
   * Whether a post-login return URL stays on this site: a root-relative path,
   * or an absolute http(s) URL on the site's own host.
   *
   * Backslashes and control characters are refused outright: browsers read
   * "/\evil.example" as "//evil.example", while parse_url() sees no host.
   *
   * @param string $url
   * @return bool
   */
  private static function isLocalUrl($url) {
    if (!is_string($url) || $url === '' || preg_match('/[\\\\\x00-\x1F\x7F]/', $url)) {
      return FALSE;
    }
    if ($url[0] === '/') {
      return !isset($url[1]) || $url[1] !== '/';
    }
    $parts = parse_url($url);
    $siteHost = parse_url(CRM_Utils_System::url('civicrm', '', TRUE, NULL, FALSE), PHP_URL_HOST);
    return is_array($parts)
      && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], TRUE)
      && !empty($parts['host'])
      && strcasecmp($parts['host'], (string) $siteHost) === 0
      && !isset($parts['user'])
      && !isset($parts['pass']);
  }
}
