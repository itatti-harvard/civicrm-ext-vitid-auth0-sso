<?php

/**
 * Replaces core's civicrm/logout page (see vitid_auth0_civicrm_alterMenu()).
 *
 * Someone who signed in with VIT ID is logged out of CiviCRM as core does it,
 * then sent to Auth0 to end their VIT ID session too, and Auth0 returns them
 * to the CiviCRM login page. Without this, "Log in with VIT ID" would sign
 * them straight back in without asking for credentials.
 *
 * Everyone else (password users, or nobody logged in) gets core's page as is.
 */
class CRM_VitidAuth0_Page_Logout extends CRM_Core_Page_Logout {

  public function run() {
    $userSystem = CRM_Core_Config::singleton()->userSystem;

    // Read before logout(), which empties the session.
    $isVitidLogin = $userSystem->isUserLoggedIn()
      && CRM_Core_Session::singleton()->get('vitid_auth0_login');
    if (!$isVitidLogin) {
      parent::run();
      return;
    }

    $redirectUrl = $userSystem->postLogoutUrl();
    try {
      _vitid_auth0_load_vendor();
      $redirectUrl = (new CRM_VitidAuth0_Utils_Auth0Client())->getLogoutUrl();
    }
    catch (\Throwable $e) {
      // Still log out of CiviCRM; only the VIT ID session stays open.
      Civi::log()->error('VIT ID logout: could not build the VIT ID logout URL, user ' . $userSystem->getLoggedInUfID() . ' stays signed in to VIT ID: ' . preg_replace('/[\x00-\x1F\x7F]/', ' ', $e->getMessage()));
    }

    $userSystem->logout();
    CRM_Utils_System::redirect($redirectUrl);
  }

}
