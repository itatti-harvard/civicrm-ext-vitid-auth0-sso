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
                Civi::log()->warning('VIT ID Callback - Invalid state parameter format: ' . substr($value, 0, 50));
                throw new Exception('Invalid state parameter format.');
              }
            } elseif ($param === 'code' && !empty($value)) {
              // Authorization code should be URL-safe alphanumeric
              if (preg_match('/^[A-Za-z0-9\-_]+$/', $value)) {
                $params[$param] = $value;
              } else {
                Civi::log()->warning('VIT ID Callback - Invalid code parameter format: ' . substr($value, 0, 50));
                throw new Exception('Invalid authorization code format.');
              }
            } elseif (in_array($param, ['error', 'error_description'])) {
              // Error parameters: allow alphanumeric, spaces, and common punctuation
              if (preg_match('/^[A-Za-z0-9\s\-_.,:;!?()]+$/u', $value)) {
                $params[$param] = $value;
              } else {
                // Log but don't fail - error messages might contain special chars
                $params[$param] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
              }
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
      
      $sessionHandler = ini_get('session.save_handler');
      $sessionSavePath = session_save_path();
      
      // CiviCRM 6.7.2 with Redis session handler compatibility
      // Redis session handler automatically manages session IDs and cookies
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Session handler: ' . $sessionHandler . ', Save path: ' . ($sessionSavePath ?: 'default'));
        
        if ($sessionHandler === 'redis' && $sessionSavePath) {
          Civi::log()->debug('VIT ID Callback - Using Redis session handler: ' . $sessionSavePath);
        }
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
      Civi::log()->info('VIT ID Callback - Session state before redirect - Session Handler: ' . $sessionHandler . ', Session ID: ' . ($sessionId ?: 'NULL') . ', Session Cookie: ' . ($sessionCookieValue !== 'NOT_SET' ? 'SET' : 'NOT_SET') . ', userID: ' . ($sessionUserId ?? 'NULL') . ', ufID: ' . ($sessionUfId ?? 'NULL') . ', Expected Contact ID: ' . $userData['civicrm_id']);
      
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
      
      // Check if we have a stored return URL
      $storedReturnUrl = $session->get('vitid_auth0_return_url');
      if (!empty($storedReturnUrl)) {
        if ($debugEnabled) {
          Civi::log()->debug('VIT ID Callback - Found stored return URL: ' . $storedReturnUrl);
        }
        // Validate URL to prevent open redirect vulnerabilities
        // Ensure it's a local URL or trusted domain
        $baseUrl = CRM_Utils_System::url('civicrm', '', TRUE, NULL, FALSE);
        $baseUrlHost = parse_url($baseUrl, PHP_URL_HOST);
        $returnUrlHost = parse_url($storedReturnUrl, PHP_URL_HOST);
        
        if (empty($returnUrlHost) || $returnUrlHost === $baseUrlHost) {
          $redirectUrl = $storedReturnUrl;
          // Clear the stored URL
          $session->set('vitid_auth0_return_url', NULL);
        } else {
          Civi::log()->warning('VIT ID Callback - Ignored potentially unsafe return URL: ' . $storedReturnUrl);
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
      $errorMessage = 'VIT ID callback error: ' . $e->getMessage();
      $errorMessage .= ' | File: ' . $e->getFile() . ':' . $e->getLine();
      $errorMessage .= ' | Stack trace: ' . $e->getTraceAsString();
      Civi::log()->error($errorMessage);

      // Always show generic error message to users (security best practice)
      // Full error details are logged above for administrators to review
      $userErrorMessage = E::ts('An error occurred during VIT ID authentication. Please try again or contact your administrator.');
      
      // In debug mode, log additional context but still don't expose to users
      if ($debugEnabled) {
        Civi::log()->debug('VIT ID Callback - Error details (debug mode): ' . $e->getMessage());
      }

      // Display error on login page
      CRM_Core_Session::setStatus(
        $userErrorMessage,
        E::ts('VIT ID Authentication Error'),
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
}
