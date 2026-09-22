<?php

use CRM_VitidAuth0_ExtensionUtil as E;

/**
 * Page for initiating VIT ID login.
 */
class CRM_VitidAuth0_Page_Login extends CRM_Core_Page {

  public function run() {
    try {
      // Check if Auth0 is configured
      $domain = Civi::settings()->get('vitid_auth0_domain');
      $clientId = Civi::settings()->get('vitid_auth0_client_id');
      $clientSecret = Civi::settings()->get('vitid_auth0_client_secret');

      if (empty($domain) || empty($clientId) || empty($clientSecret)) {
        CRM_Core_Error::statusBounce(
          E::ts('VIT ID authentication is not configured. Please contact your administrator.'),
          CRM_Utils_System::url('civicrm/login', 'reset=1')
        );
      }

      // Initialize Auth0 client
      _vitid_auth0_load_vendor();
      $auth0Client = new CRM_VitidAuth0_Utils_Auth0Client();

      // Store the return URL in the session
      $session = CRM_Core_Session::singleton();
      
      // Check for explicit destination parameter first
      if (!empty($_GET['destination'])) {
        $returnUrl = $_GET['destination'];
        
        // Log destination detection for debugging
        if (Civi::settings()->get('vitid_auth0_debug')) {
          Civi::log()->debug('VIT ID Login Page - Found destination in GET: ' . $returnUrl);
        }
        
        // If it's a relative path (not starting with http and not starting with /), make it absolute
        // We allow paths starting with / (root-relative) to pass through as is
        if (strpos($returnUrl, 'http') !== 0 && strpos($returnUrl, '/') !== 0) {
          $returnUrl = CRM_Utils_System::url($returnUrl, '', TRUE, NULL, FALSE);
        }
        $session->set('vitid_auth0_return_url', $returnUrl);
      } else {
        // Fallback to user context
        $returnUrl = $session->readUserContext();
        
        // Log user context fallback for debugging
        if (Civi::settings()->get('vitid_auth0_debug')) {
          Civi::log()->debug('VIT ID Login Page - No destination in GET. Fallback to userContext: ' . ($returnUrl ?? 'NULL'));
        }
        
        if ($returnUrl) {
          $session->set('vitid_auth0_return_url', $returnUrl);
        }
      }

      // Get login URL and redirect
      $loginUrl = $auth0Client->getLoginUrl();
      CRM_Utils_System::redirect($loginUrl);

    } catch (Exception $e) {
      Civi::log()->error('VIT ID login error: ' . $e->getMessage());
      CRM_Core_Error::statusBounce(
        E::ts('An error occurred while initiating VIT ID login. Please try again or contact your administrator.'),
        CRM_Utils_System::url('civicrm/login', 'reset=1')
      );
    }

    parent::run();
  }
}
