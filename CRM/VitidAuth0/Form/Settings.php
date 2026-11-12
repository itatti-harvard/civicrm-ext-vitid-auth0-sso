<?php

use CRM_VitidAuth0_ExtensionUtil as E;

/**
 * Form controller for VIT ID Authentication Settings.
 */
class CRM_VitidAuth0_Form_Settings extends CRM_Core_Form {

  /**
   * A hostname such as tenant.eu.auth0.com or a custom domain like login.example.org.
   */
  const DOMAIN_PATTERN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i';

  /**
   * Get the template file name.
   *
   * @return string
   */
  public function getTemplateFileName() {
    $ext = E::LONG_NAME;
    $extPath = CRM_Core_Resources::singleton()->getPath($ext);
    return $extPath . '/templates/CRM/VitidAuth0/Form/Settings.tpl';
  }

  /**
   * Build the form.
   */
  public function buildQuickForm() {
    // Add form elements for Auth0 configuration
    $this->add(
      'text',
      'vitid_auth0_domain',
      E::ts('Auth0 Domain'),
      ['size' => 50, 'placeholder' => 'your-tenant.eu.auth0.com or login.example.org'],
      TRUE
    );
    $this->addRule('vitid_auth0_domain', E::ts('Enter the domain without https://, for example your-tenant.eu.auth0.com or login.example.org.'), 'regex', self::DOMAIN_PATTERN);

    $this->add(
      'text',
      'vitid_auth0_client_id',
      E::ts('Auth0 Client ID'),
      ['size' => 50],
      TRUE
    );
    // Validate client ID format (alphanumeric and some special chars)
    $this->addRule('vitid_auth0_client_id', E::ts('Client ID contains invalid characters'), 'regex', '/^[A-Za-z0-9\-_]+$/');

    // The saved secret is never sent back to the browser; leaving the field
    // empty keeps it, so it is only required until one has been saved.
    $hasSecret = !empty(Civi::settings()->get('vitid_auth0_client_secret'));
    $this->assign('hasClientSecret', $hasSecret);
    $this->add(
      'password',
      'vitid_auth0_client_secret',
      E::ts('Auth0 Client Secret'),
      [
        'size' => 50,
        'autocomplete' => 'new-password',
        'placeholder' => $hasSecret ? E::ts('Saved; leave empty to keep it') : '',
      ],
      !$hasSecret
    );
    // Client secret validation - basic length check (Auth0 secrets are typically long)
    $this->addRule('vitid_auth0_client_secret', E::ts('Client secret must be at least 10 characters'), 'minlength', 10);

    $this->add(
      'checkbox',
      'vitid_auth0_debug',
      E::ts('Enable Debug Logging')
    );

    // Role mapping fields
    $this->add(
      'text',
      'auth0_role_name',
      E::ts('Auth0 Role Name'),
      ['size' => 40, 'placeholder' => 'e.g., civicrm-administrator']
    );
    // Validate role name format (alphanumeric, dash, underscore)
    $this->addRule('auth0_role_name', E::ts('Role name can only contain letters, numbers, dashes, and underscores'), 'regex', '/^[A-Za-z0-9\-_]+$/');

    // Get CiviCRM roles for dropdown
    $civiRoles = CRM_VitidAuth0_Utils_RoleMapper::getCiviCRMRolesList();
    $this->add(
      'select',
      'civicrm_role_id',
      E::ts('CiviCRM Role'),
      ['' => E::ts('-- Select Role --')] + $civiRoles
    );

    // Get current role mappings from the dedicated table
    $currentMappings = CRM_VitidAuth0_Utils_RoleMapper::getAllMappings(FALSE);
    $this->assign('currentMappings', $currentMappings);
    $this->assign('civiRoles', $civiRoles);

    // Add buttons
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save Settings'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'submit',
        'name' => E::ts('Add Role Mapping'),
        'subName' => 'add_mapping',
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ]);

    // Assign callback URL
    $callbackUrl = CRM_Utils_System::url('civicrm/vitid-auth0/callback', '', TRUE, NULL, FALSE);
    $this->assign('callbackUrl', $callbackUrl);

    parent::buildQuickForm();
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = [];
    
    $defaults['vitid_auth0_domain'] = Civi::settings()->get('vitid_auth0_domain');
    $defaults['vitid_auth0_client_id'] = Civi::settings()->get('vitid_auth0_client_id');
    $defaults['vitid_auth0_debug'] = Civi::settings()->get('vitid_auth0_debug');

    return $defaults;
  }

  /**
   * Process form submission.
   */
  public function postProcess() {
    // Verify CSRF token for all form submissions
    if (!$this->controller->validate()) {
      CRM_Core_Session::setStatus(
        E::ts('Invalid form submission. Please try again.'),
        E::ts('Error'),
        'error'
      );
      return;
    }
    
    $values = $this->exportValues();
    $buttonName = $this->controller->getButtonName();

    // Handle delete mapping request
    if (strpos($buttonName, '_qf_Settings_submit_delete_') === 0) {
      $mappingId = str_replace('_qf_Settings_submit_delete_', '', $buttonName);
      
      // Validate mapping ID is a positive integer
      if (!ctype_digit($mappingId) || (int)$mappingId <= 0) {
        CRM_Core_Session::setStatus(
          E::ts('Invalid mapping ID.'),
          E::ts('Error'),
          'error'
        );
        $url = CRM_Utils_System::url('civicrm/admin/vitid-auth0/settings', 'reset=1', TRUE);
        CRM_Utils_System::redirect($url);
        return;
      }
      
      try {
        CRM_Core_DAO::executeQuery(
          "DELETE FROM civicrm_vitid_role_mapping WHERE id = %1",
          [1 => [(int)$mappingId, 'Integer']]
        );
        
        CRM_Core_Session::setStatus(
          E::ts('Role mapping deleted successfully.'),
          E::ts('Deleted'),
          'success'
        );
      } catch (Exception $e) {
        Civi::log()->error('Error deleting role mapping ' . (int) $mappingId . ': ' . $e->getMessage());
        CRM_Core_Session::setStatus(
          E::ts('The role mapping could not be deleted. Please try again; if it keeps failing, check the CiviCRM log.'),
          E::ts('Error'),
          'error'
        );
      }
      
      // Redirect to refresh the page and show updated mappings
      $url = CRM_Utils_System::url('civicrm/admin/vitid-auth0/settings', 'reset=1', TRUE);
      CRM_Utils_System::redirect($url);
    }

    // Handle add mapping request
    if (strpos($buttonName, '_qf_Settings_submit_add_mapping') === 0) {
      $auth0RoleName = trim($values['auth0_role_name'] ?? '');
      $civiRoleId = $values['civicrm_role_id'] ?? '';

      if (empty($auth0RoleName)) {
        CRM_Core_Session::setStatus(
          E::ts('Please enter an Auth0 role name.'),
          E::ts('Required Field'),
          'error'
        );
        return;
      }

      // Validate role name format
      if (!preg_match('/^[A-Za-z0-9\-_]+$/', $auth0RoleName)) {
        CRM_Core_Session::setStatus(
          E::ts('Role name can only contain letters, numbers, dashes, and underscores.'),
          E::ts('Invalid Format'),
          'error'
        );
        return;
      }

      if (empty($civiRoleId)) {
        CRM_Core_Session::setStatus(
          E::ts('Please select a CiviCRM role.'),
          E::ts('Required Field'),
          'error'
        );
        return;
      }

      // Validate CiviCRM role ID is a positive integer
      if (!ctype_digit((string)$civiRoleId) || (int)$civiRoleId <= 0) {
        CRM_Core_Session::setStatus(
          E::ts('Invalid CiviCRM role selected.'),
          E::ts('Invalid Selection'),
          'error'
        );
        return;
      }

      $alreadyMapped = CRM_Core_DAO::singleValueQuery(
        "SELECT COUNT(*) FROM civicrm_vitid_role_mapping WHERE auth0_role_name = %1",
        [1 => [$auth0RoleName, 'String']]
      );
      if ($alreadyMapped) {
        CRM_Core_Session::setStatus(
          E::ts('The Auth0 role "%1" is already mapped. To change its CiviCRM role, delete the existing mapping and add it again.', [1 => $auth0RoleName]),
          E::ts('Already Mapped'),
          'error'
        );
        return;
      }

      try {
        CRM_Core_DAO::executeQuery(
          "INSERT INTO civicrm_vitid_role_mapping (auth0_role_name, civicrm_role_id, is_active) 
           VALUES (%1, %2, 1)",
          [
            1 => [$auth0RoleName, 'String'],
            2 => [(int)$civiRoleId, 'Integer'],
          ]
        );

        CRM_Core_Session::setStatus(
          E::ts('Role mapping added successfully.'),
          E::ts('Added'),
          'success'
        );
      } catch (Exception $e) {
        Civi::log()->error('Error adding role mapping for ' . json_encode($auth0RoleName) . ': ' . $e->getMessage());
        CRM_Core_Session::setStatus(
          E::ts('The role mapping could not be added. Please try again; if it keeps failing, check the CiviCRM log.'),
          E::ts('Error'),
          'error'
        );
      }

      // Redirect to refresh the page and show updated mappings
      $url = CRM_Utils_System::url('civicrm/admin/vitid-auth0/settings', 'reset=1', TRUE);
      CRM_Utils_System::redirect($url);
    }

    // Handle save settings (default action)
    $domain = trim($values['vitid_auth0_domain'] ?? '');
    if (!empty($domain) && !preg_match(self::DOMAIN_PATTERN, $domain)) {
      CRM_Core_Session::setStatus(
        E::ts('Enter the domain without https://, for example your-tenant.eu.auth0.com or login.example.org.'),
        E::ts('Invalid Format'),
        'error'
      );
      return;
    }
    
    // Validate and sanitize client ID
    $clientId = trim($values['vitid_auth0_client_id'] ?? '');
    if (!empty($clientId) && !preg_match('/^[A-Za-z0-9\-_]+$/', $clientId)) {
      CRM_Core_Session::setStatus(
        E::ts('Client ID contains invalid characters.'),
        E::ts('Invalid Format'),
        'error'
      );
      return;
    }
    
    // Save Auth0 configuration
    Civi::settings()->set('vitid_auth0_domain', $domain);
    Civi::settings()->set('vitid_auth0_client_id', $clientId);
    
    // Only update secret if provided
    if (!empty($values['vitid_auth0_client_secret'])) {
      $clientSecret = trim($values['vitid_auth0_client_secret']);
      // Basic validation - Auth0 secrets are typically long strings
      if (strlen($clientSecret) < 10) {
        CRM_Core_Session::setStatus(
          E::ts('Client secret must be at least 10 characters long.'),
          E::ts('Invalid Format'),
          'error'
        );
        return;
      }
      Civi::settings()->set('vitid_auth0_client_secret', $clientSecret);
    }

    // Save debug setting
    Civi::settings()->set('vitid_auth0_debug', !empty($values['vitid_auth0_debug']));

    CRM_Core_Session::setStatus(
      E::ts('VIT ID Authentication settings have been saved.'),
      E::ts('Settings Saved'),
      'success'
    );

    parent::postProcess();
  }
}
