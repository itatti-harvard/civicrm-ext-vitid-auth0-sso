<?php

require_once 'vitid_auth0.civix.php';
// phpcs:disable
use CRM_VitidAuth0_ExtensionUtil as E;
// phpcs:enable

/**
 * Load the extension's bundled Composer dependencies (Auth0 SDK and friends).
 *
 * Composer registers its autoloader *prepended*, which would let our bundled
 * copies of libraries CiviCRM core also ships (psr/log, guzzle, ...) shadow
 * core's and cause "Declaration ... must be compatible" fatals. Re-register it
 * appended so core's classes always win and ours are only a fallback.
 */
function _vitid_auth0_load_vendor(): void {
  static $loaded = FALSE;
  if ($loaded) {
    return;
  }
  $loaded = TRUE;
  $loader = require __DIR__ . '/vendor/autoload.php';
  $loader->unregister();
  $loader->register(FALSE);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function vitid_auth0_civicrm_config(&$config): void {
  _vitid_auth0_civix_civicrm_config($config);
  
  // Register template directory for Smarty 5 compatibility
  $extRoot = __DIR__;
  $templateDir = $extRoot . DIRECTORY_SEPARATOR . 'templates';
  
  if (!empty($templateDir) && is_dir($templateDir)) {
    $template = CRM_Core_Smarty::singleton();
    // Use addTemplateDir() for Smarty 5
    if (method_exists($template, 'addTemplateDir')) {
      $template->addTemplateDir($templateDir);
    }
  }
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function vitid_auth0_civicrm_install(): void {
  _vitid_auth0_civix_civicrm_install();
  
  try {
    // Create the role mapping table
    // Note: CRM_Core_DAO::executeQuery() doesn't handle multiple SQL statements
    // Execute each CREATE TABLE statement separately
    CRM_Core_DAO::executeQuery("
      CREATE TABLE IF NOT EXISTS `civicrm_vitid_role_mapping` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `auth0_role_name` VARCHAR(255) NOT NULL UNIQUE,
        `civicrm_role_id` INT UNSIGNED NOT NULL,
        `is_active` TINYINT DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_active` (`is_active`),
        INDEX `idx_auth0_role` (`auth0_role_name`),
        FOREIGN KEY (`civicrm_role_id`) REFERENCES `civicrm_role`(`id`) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create OAuth state storage table (Generic Key-Value Store)
    CRM_Core_DAO::executeQuery("
      CREATE TABLE IF NOT EXISTS `civicrm_vitid_auth0_state` (
        `key` VARCHAR(255) NOT NULL PRIMARY KEY,
        `value` TEXT NOT NULL,
        `expire` INT UNSIGNED NOT NULL,
        INDEX `idx_expire` (`expire`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    Civi::log()->info('VIT ID Authentication: Database tables created successfully');
    
    // Migrate existing role mappings from settings to new table (if upgrading)
    $existingMappings = Civi::settings()->get('vitid_auth0_role_mappings');
    if (!empty($existingMappings)) {
      // Decode if it's JSON string
      if (is_string($existingMappings)) {
        $existingMappings = json_decode($existingMappings, TRUE);
      }
      
      if (is_array($existingMappings) && !empty($existingMappings)) {
        // Insert mappings into the new table
        foreach ($existingMappings as $auth0Role => $civiRoleId) {
          if (!empty($auth0Role) && !empty($civiRoleId)) {
            // Handle both old format (array) and new format (single value)
            $roleIds = is_array($civiRoleId) ? $civiRoleId : [$civiRoleId];
            foreach ($roleIds as $roleId) {
              CRM_Core_DAO::executeQuery(
                "INSERT IGNORE INTO civicrm_vitid_role_mapping (auth0_role_name, civicrm_role_id, is_active) 
                 VALUES (%1, %2, 1)",
                [
                  1 => [$auth0Role, 'String'],
                  2 => [$roleId, 'Integer'],
                ]
              );
            }
          }
        }
        Civi::log()->info('VIT ID Authentication: Role mappings migrated to new table');
      }
    }
  } catch (Exception $e) {
    Civi::log()->error('VIT ID Authentication: Error during install: ' . $e->getMessage());
    throw $e;
  }
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function vitid_auth0_civicrm_enable(): void {
  _vitid_auth0_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function vitid_auth0_civicrm_uninstall(): void {
  try {
    // Drop the role mapping table
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_vitid_role_mapping");
    
    // Drop the OAuth state table
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_vitid_auth0_state");
    
    Civi::log()->info('VIT ID Authentication: Tables dropped on uninstall');
    
    // Clean up extension settings directly from database
    CRM_Core_DAO::executeQuery(
      "DELETE FROM civicrm_setting WHERE name IN ('vitid_auth0_domain', 'vitid_auth0_client_id', 'vitid_auth0_client_secret', 'vitid_auth0_role_mappings')"
    );
    
    Civi::log()->info('VIT ID Authentication: Extension settings cleaned up on uninstall');
  } catch (Exception $e) {
    Civi::log()->error('VIT ID Authentication: Error cleaning up on uninstall: ' . $e->getMessage());
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function vitid_auth0_civicrm_navigationMenu(&$menu): void {
  _vitid_auth0_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('VIT ID Authentication Settings'),
    'name' => 'vitid_auth0_settings',
    'url' => 'civicrm/admin/vitid-auth0/settings',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _vitid_auth0_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_alterContent().
 *
 * Add "Log in with VIT ID" button to the login page.
 * This hook only works for template-based login pages (CiviCRM < 6.7.0).
 * For Angular-based login pages (CiviCRM 6.7.0+), use JavaScript injection instead.
 */
function vitid_auth0_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  // Only proceed if this is a page context
  if ($context !== 'page') {
    return;
  }

  // Check CiviCRM version - skip for 6.7.0+ (Angular-based login)
  $version = CRM_Utils_System::version();
  $versionParts = explode('.', $version);
  $majorVersion = isset($versionParts[0]) ? (int)$versionParts[0] : 0;
  $minorVersion = isset($versionParts[1]) ? (int)$versionParts[1] : 0;
  
  // Skip this hook for CiviCRM 6.7.0+ (Angular login page uses JavaScript injection)
  if ($majorVersion > 6 || ($majorVersion === 6 && $minorVersion >= 7)) {
    return;
  }

  // Check if this is the standalone login page (template-based)
  if (strpos($tplName, 'CRM/Standaloneusers/Page/Login.tpl') !== FALSE ||
      strpos($tplName, 'Login.tpl') !== FALSE) {
    
    // Get the Auth0 login URL, preserving destination if present
    $query = '';
    $destination = CRM_Utils_Request::retrieve('destination', 'String');
    
    // Fallback: If no destination param, but we are on a protected page (not login page),
    // use the current URI as the destination.
    if (empty($destination)) {
      $requestUri = $_SERVER['REQUEST_URI'] ?? '';
      // Check if we are NOT on a login page (avoid loops)
      if (!empty($requestUri) && 
          strpos($requestUri, 'civicrm/login') === FALSE && 
          strpos($requestUri, 'civicrm/vitid-auth0/login') === FALSE &&
          strpos($requestUri, 'standalone/login') === FALSE) {
        $destination = $requestUri;
      }
    }

    if (!empty($destination)) {
      $query = 'destination=' . urlencode($destination);
    }
    $loginUrl = CRM_Utils_System::url('civicrm/vitid-auth0/login', $query, TRUE, NULL, FALSE);
    
    // Create the VIT ID login button HTML
    $vitidButton = '
    <div class="vitid-auth0-login" style="margin: 20px 0; text-align: center;">
      <a href="' . htmlspecialchars($loginUrl) . '" class="button" style="display: inline-block; padding: 12px 24px; background-color: #635BFF; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; border: none;">
        <i class="crm-i fa-sign-in" style="margin-right: 8px;"></i>
        Log in with VIT ID
      </a>
      <div style="margin: 15px 0; color: #666;">
        <span style="display: inline-block; width: 40%; height: 1px; background-color: #ddd; vertical-align: middle;"></span>
        <span style="padding: 0 10px;">or</span>
        <span style="display: inline-block; width: 40%; height: 1px; background-color: #ddd; vertical-align: middle;"></span>
      </div>
    </div>';
    
    // Insert the button before the login form
    // Find the form opening tag and insert our button after it
    $formPattern = '/(<form[^>]*id=["\']Login["\'][^>]*>)/i';
    if (preg_match($formPattern, $content)) {
      $content = preg_replace($formPattern, '$1' . $vitidButton, $content);
    } else {
      // Fallback: insert at the beginning if we can't find the form
      $content = $vitidButton . $content;
    }
  }
}

/**
 * Implements hook_civicrm_dao().
 *
 * Register custom DAO classes.
 */
function vitid_auth0_civicrm_dao(&$dao) {
  $dao[] = 'CRM_VitidAuth0_DAO_RoleMapping';
}

/**
 * Implements hook_civicrm_checkAccess().
 *
 * Grant public access to VIT ID authentication routes.
 */
function vitid_auth0_civicrm_checkAccess($entity, $action, &$params, &$permissions) {
  // Get the current path
  $path = CRM_Utils_System::currentPath();
  
  // Allow anonymous access to VIT ID authentication pages
  $publicPaths = [
    'civicrm/vitid-auth0/login',
    'civicrm/vitid-auth0/callback',
    'civicrm/vitid-auth0/error',
  ];
  
  if (in_array($path, $publicPaths)) {
    // Grant access by returning TRUE
    $permissions = TRUE;
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * Intercept logout to handle Auth0 logout for VIT ID users.
 * Also inject JavaScript and CSS for Angular-based login page (CiviCRM 6.7.2+).
 */
function vitid_auth0_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  
  // Handle logout for VIT ID users
  if ($pageName === 'CRM_Core_Page_Logout') {
    $session = CRM_Core_Session::singleton();
    
    // Check if this user logged in via VIT ID
    if ($session->get('vitid_auth0_login')) {
      try {
        $auth0Domain = Civi::settings()->get('vitid_auth0_domain');
        $clientId = Civi::settings()->get('vitid_auth0_client_id');
        
        if ($auth0Domain && $clientId) {
          // Build the return URL (back to CiviCRM login page)
          $returnTo = urlencode(CRM_Utils_System::url('civicrm/login', 'reset=1', TRUE, NULL, FALSE));
          
          // Clear CiviCRM session
          $session->reset();
          
          // Redirect to Auth0 logout
          $logoutUrl = "https://{$auth0Domain}/v2/logout?client_id={$clientId}&returnTo={$returnTo}";
          CRM_Utils_System::redirect($logoutUrl);
          exit;
        }
      } catch (Exception $e) {
        // Log the error but allow standard logout to proceed
        Civi::log()->error('VIT ID Auth0 logout error: ' . $e->getMessage());
      }
    }
    // If not a VIT ID login, let standard logout proceed
  }
  
  // Handle login page for Angular-based login (CiviCRM 6.7.2+)
  // Check if this is the standalone login page
  if ($pageName === 'CRM_Standaloneusers_Page_Login' || 
      (is_object($page) && get_class($page) === 'CRM_Standaloneusers_Page_Login')) {
    
    // Get CiviCRM version to determine if we need Angular support
    $version = CRM_Utils_System::version();
    $versionParts = explode('.', $version);
    $majorVersion = isset($versionParts[0]) ? (int)$versionParts[0] : 0;
    $minorVersion = isset($versionParts[1]) ? (int)$versionParts[1] : 0;
    
    // For CiviCRM 6.7.0+, use JavaScript injection for Angular login page
    if ($majorVersion > 6 || ($majorVersion === 6 && $minorVersion >= 7)) {
      $resources = CRM_Core_Resources::singleton();
      
      // Add CSS file
      $resources->addStyleFile('vitid_auth0', 'css/vitid-auth0-login.css');
      
      // Add JavaScript file
      $resources->addScriptFile('vitid_auth0', 'js/vitid-auth0-login.js');
      
      // Pass login URL to JavaScript via CRM.vars
      // Preserve destination if present
      $query = '';
      $destination = CRM_Utils_Request::retrieve('destination', 'String');
      
      // Log destination detection for debugging
      if (Civi::settings()->get('vitid_auth0_debug')) {
        Civi::log()->debug('VIT ID PageRun - Detecting destination on login page. GET[destination]: ' . ($_GET['destination'] ?? 'NULL') . ', Request::retrieve: ' . ($destination ?? 'NULL'));
      }
      
      // Fallback: If no destination param, but we are on a protected page (not login page),
      // use the current URI as the destination.
      if (empty($destination)) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        // Check if we are NOT on a login page (avoid loops)
        if (!empty($requestUri) && 
            strpos($requestUri, 'civicrm/login') === FALSE && 
            strpos($requestUri, 'civicrm/vitid-auth0/login') === FALSE &&
            strpos($requestUri, 'standalone/login') === FALSE) {
          
          $destination = $requestUri;
          if (Civi::settings()->get('vitid_auth0_debug')) {
            Civi::log()->debug('VIT ID PageRun - Using current URI as destination: ' . $destination);
          }
        }
      }
      
      if (!empty($destination)) {
        $query = 'destination=' . urlencode($destination);
      }
      $loginUrl = CRM_Utils_System::url('civicrm/vitid-auth0/login', $query, TRUE, NULL, FALSE);
      
      if (Civi::settings()->get('vitid_auth0_debug')) {
        Civi::log()->debug('VIT ID PageRun - Generated login URL: ' . $loginUrl);
      }
      
      $resources->addVars('vitidAuth0', [
        'loginUrl' => $loginUrl,
      ]);
    }
  }
}
