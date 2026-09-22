<?php

/**
 * Handles role mapping between Auth0 and CiviCRM.
 */
class CRM_VitidAuth0_Utils_RoleMapper {

  /**
   * Check if debug logging is enabled.
   *
   * @return bool
   */
  private static function isDebugEnabled() {
    return (bool) Civi::settings()->get('vitid_auth0_debug');
  }

  /**
   * Log debug message if debug logging is enabled.
   *
   * @param string $message
   */
  private static function debugLog($message) {
    if (self::isDebugEnabled()) {
      Civi::log()->debug($message);
    }
  }

  /**
   * Validate if user has required role mappings.
   *
   * @param array $auth0Roles User's Auth0 role names
   * @return bool
   */
  public static function hasValidRoles($auth0Roles) {
    if (empty($auth0Roles)) {
      return FALSE;
    }

    try {
      // Build parameterized query with placeholders
      $placeholders = [];
      $params = [];
      $counter = 1;
      
      foreach ($auth0Roles as $role) {
        $placeholders[] = "%{$counter}";
        $params[$counter] = [$role, 'String'];
        $counter++;
      }
      
      $sql = "SELECT COUNT(*) as count FROM civicrm_vitid_role_mapping 
         WHERE auth0_role_name IN (" . implode(',', $placeholders) . ") AND is_active = 1";
      
      // Query the role mapping table using parameterized query
      $result = CRM_Core_DAO::executeQuery($sql, $params);
      
      if ($result->fetch()) {
        return $result->count > 0;
      }
    } catch (Exception $e) {
      Civi::log()->error('Error checking role mappings: ' . $e->getMessage());
    }

    return FALSE;
  }

  /**
   * Get CiviCRM roles for Auth0 roles.
   *
   * @param array $auth0Roles User's Auth0 role names
   * @return array CiviCRM role IDs
   */
  public static function getCiviCRMRoles($auth0Roles) {
    if (empty($auth0Roles)) {
      return [];
    }

    $civiRoles = [];

    try {
      // Build parameterized query with placeholders
      $placeholders = [];
      $params = [];
      $counter = 1;
      
      foreach ($auth0Roles as $role) {
        $placeholders[] = "%{$counter}";
        $params[$counter] = [$role, 'String'];
        $counter++;
      }
      
      $sql = "SELECT DISTINCT civicrm_role_id FROM civicrm_vitid_role_mapping 
         WHERE auth0_role_name IN (" . implode(',', $placeholders) . ") AND is_active = 1";
      
      // Query the role mapping table using parameterized query
      $result = CRM_Core_DAO::executeQuery($sql, $params);
      
      while ($result->fetch()) {
        $civiRoles[] = $result->civicrm_role_id;
      }
    } catch (Exception $e) {
      Civi::log()->error('Error retrieving CiviCRM roles: ' . $e->getMessage());
    }

    return array_unique($civiRoles);
  }

  /**
   * Get all CiviCRM roles.
   *
   * @return array
   */
  public static function getCiviCRMRolesList() {
    try {
      $roles = \Civi\Api4\Role::get(FALSE)
        ->addWhere('is_active', '=', 1)
        ->execute();

      $rolesList = [];
      foreach ($roles as $role) {
        $rolesList[$role['id']] = $role['label'];
      }

      return $rolesList;

    } catch (\Exception $e) {
      Civi::log()->error('Failed to fetch CiviCRM roles: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get all role mappings from the table.
   *
   * @param bool $activeOnly Only return active mappings
   * @return array
   */
  public static function getAllMappings($activeOnly = TRUE) {
    $mappings = [];

    try {
      if ($activeOnly) {
        $result = CRM_Core_DAO::executeQuery(
          "SELECT id, auth0_role_name, civicrm_role_id, is_active FROM civicrm_vitid_role_mapping WHERE is_active = %1 ORDER BY auth0_role_name",
          [1 => [1, 'Integer']]
        );
      } else {
        $result = CRM_Core_DAO::executeQuery(
          "SELECT id, auth0_role_name, civicrm_role_id, is_active FROM civicrm_vitid_role_mapping ORDER BY auth0_role_name"
        );
      }

      while ($result->fetch()) {
        $mappings[] = [
          'id' => $result->id,
          'auth0_role_name' => $result->auth0_role_name,
          'civicrm_role_id' => $result->civicrm_role_id,
          'is_active' => $result->is_active,
        ];
      }
    } catch (Exception $e) {
      Civi::log()->error('Error retrieving all role mappings: ' . $e->getMessage());
    }

    return $mappings;
  }

  /**
   * Add a new role mapping.
   *
   * @param string $auth0RoleName Auth0 role name
   * @param int $civiRoleId CiviCRM role ID
   * @return bool
   */
  public static function addMapping($auth0RoleName, $civiRoleId) {
    try {
      CRM_Core_DAO::executeQuery(
        "INSERT INTO civicrm_vitid_role_mapping (auth0_role_name, civicrm_role_id, is_active) 
         VALUES (%1, %2, 1)",
        [
          1 => [$auth0RoleName, 'String'],
          2 => [$civiRoleId, 'Integer'],
        ]
      );
      return TRUE;
    } catch (Exception $e) {
      Civi::log()->error('Error adding role mapping: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Delete a role mapping.
   *
   * @param int $mappingId Mapping ID
   * @return bool
   */
  public static function deleteMapping($mappingId) {
    try {
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_vitid_role_mapping WHERE id = %1",
        [1 => [$mappingId, 'Integer']]
      );
      return TRUE;
    } catch (Exception $e) {
      Civi::log()->error('Error deleting role mapping: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Update role mapping active status.
   *
   * @param int $mappingId Mapping ID
   * @param bool $isActive Active status
   * @return bool
   */
  public static function updateMappingStatus($mappingId, $isActive) {
    try {
      $status = $isActive ? 1 : 0;
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_vitid_role_mapping SET is_active = %1 WHERE id = %2",
        [
          1 => [$status, 'Integer'],
          2 => [$mappingId, 'Integer'],
        ]
      );
      return TRUE;
    } catch (Exception $e) {
      Civi::log()->error('Error updating role mapping status: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Create or update CiviCRM user session.
   *
   * @param int $contactId CiviCRM contact ID
   * @param array $civiRoles CiviCRM role IDs
   * @return bool
   */
  public static function createUserSession($contactId, $civiRoles) {
    // Initialize variables for error handling
    $contactIdForError = $contactId ?? 'UNKNOWN';
    try {
      // Verify contact exists
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', $contactId)
        ->execute()
        ->first();

      if (empty($contact)) {
        throw new \Exception('Contact not found.');
      }

      // Get or create user (which creates UFMatch automatically)
      // Query UFMatch by contact_id to check if user already exists
      // Query by contact_id only first to catch records with NULL domain_id
      // This handles cases where orphaned records exist from previous failed logins
      $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
        ->addWhere('contact_id', '=', $contactId)
        ->execute()
        ->first();

      $ufMatchId = NULL;
      $ufID = NULL;
      if (!empty($ufMatch)) {
        // User already exists - use existing UFMatch record
        $ufMatchId = $ufMatch['id'];
        $ufID = $ufMatch['uf_id'];
        
        self::debugLog('VIT ID createUserSession - Found existing UFMatch - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId . ', uf_id: ' . $ufID);
        
        // Get email for username check/update
        $email = NULL;
        if (!empty($contact['email'])) {
          $email = $contact['email'];
        } else {
          $emailResult = \Civi\Api4\Email::get(FALSE)
            ->addWhere('contact_id', '=', $contactId)
            ->addWhere('is_primary', '=', 1)
            ->setLimit(1)
            ->execute()
            ->first();
          if (!empty($emailResult)) {
            $email = $emailResult['email'];
          }
        }
        if (empty($email) && !empty($ufMatch['uf_name'])) {
          $email = $ufMatch['uf_name'];
        }
        
        // Ensure domain_id is set (for existing records, including those with NULL domain_id)
        $needsUpdate = FALSE;
        $updateValues = [];
        if (empty($ufMatch['domain_id'])) {
          $updateValues['domain_id'] = 1;
          $needsUpdate = TRUE;
        }
        
        // Ensure username is set (must be unique globally, use email if available)
        if (!empty($email) && (empty($ufMatch['username']) || $ufMatch['username'] !== $email)) {
          $updateValues['username'] = $email;
          $needsUpdate = TRUE;
        }
        
        if ($needsUpdate) {
          self::debugLog('VIT ID createUserSession - Updating existing UFMatch - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId);
          $updateQuery = \Civi\Api4\UFMatch::update(FALSE)
            ->addWhere('id', '=', $ufMatchId);
          foreach ($updateValues as $key => $value) {
            $updateQuery->addValue($key, $value);
          }
          $updateQuery->execute();
        }
      } else {
        // User doesn't exist - check if User exists before attempting creation
        // This prevents "already exists" errors by finding existing Users
        
        // First, get email for checking (needed for User.create anyway)
        $email = NULL;
        if (!empty($contact['email'])) {
          $email = $contact['email'];
        } else {
          // Query for primary email
          $emailResult = \Civi\Api4\Email::get(FALSE)
            ->addWhere('contact_id', '=', $contactId)
            ->addWhere('is_primary', '=', 1)
            ->setLimit(1)
            ->execute()
            ->first();
          if (!empty($emailResult)) {
            $email = $emailResult['email'];
          }
        }
        // Fallback to display_name-based email if no email found
        if (empty($email)) {
          $email = str_replace(' ', '.', strtolower($contact['display_name'])) . '@vitid-auth0.local';
        }
        
        // Check if User already exists (by contact_id or email)
        $existingUserFound = FALSE;
        try {
          // Check by contact_id first
          $existingUserCheck = \Civi\Api4\User::get(FALSE)
            ->addWhere('contact_id', '=', $contactId)
            ->execute()
            ->first();
          
          if (!empty($existingUserCheck)) {
            self::debugLog('VIT ID createUserSession - Found existing User by contact_id, querying UFMatch');
            $existingUserFound = TRUE;
          } else {
            // Also check by email (might be email conflict)
            if (!empty($email)) {
              $existingUserByEmail = \Civi\Api4\User::get(FALSE)
                ->addWhere('email', '=', $email)
                ->execute()
                ->first();
              
              if (!empty($existingUserByEmail)) {
                self::debugLog('VIT ID createUserSession - Found existing User by email=' . $email . ' with contact_id=' . ($existingUserByEmail['contact_id'] ?? 'NULL'));
                if (!empty($existingUserByEmail['contact_id']) && $existingUserByEmail['contact_id'] == $contactId) {
                  // Same contact - this is the user we need
                  $existingUserFound = TRUE;
                  $existingUserCheck = $existingUserByEmail;
                } else {
                  // Different contact - email conflict
                  Civi::log()->error('VIT ID createUserSession - Email conflict detected: email ' . $email . ' exists for contact_id=' . ($existingUserByEmail['contact_id'] ?? 'NULL') . ', but trying to create for contact_id=' . $contactId);
                }
              }
            }
          }
          
          if ($existingUserFound && !empty($existingUserCheck)) {
            // User exists, try to get UFMatch
            $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
              ->addWhere('contact_id', '=', $contactId)
              ->execute()
              ->first();
            
            if (!empty($ufMatch)) {
              // Found UFMatch - use existing record
              $ufMatchId = $ufMatch['id'];
              $ufID = $ufMatch['uf_id'];
              
              // Ensure domain_id and username are set correctly
              $needsUpdate = FALSE;
              $updateValues = [];
              if (empty($ufMatch['domain_id'])) {
                $updateValues['domain_id'] = 1;
                $needsUpdate = TRUE;
              }
              // Ensure username is set (must be unique globally, use email if available)
              if (!empty($email) && (empty($ufMatch['username']) || $ufMatch['username'] !== $email)) {
                $updateValues['username'] = $email;
                $needsUpdate = TRUE;
              }
              
              if ($needsUpdate) {
                self::debugLog('VIT ID createUserSession - Updating existing UFMatch after User check - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId);
                $updateQuery = \Civi\Api4\UFMatch::update(FALSE)
                  ->addWhere('id', '=', $ufMatchId);
                foreach ($updateValues as $key => $value) {
                  $updateQuery->addValue($key, $value);
                }
                $updateQuery->execute();
              }
              
              self::debugLog('VIT ID createUserSession - Found existing User and UFMatch - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId . ', uf_id: ' . $ufID);
              // Skip to role assignment (ufMatch is set, so we won't enter the create block)
            } else {
              // User exists but no UFMatch - this is unusual, log it
              Civi::log()->warning('VIT ID createUserSession - User exists for contact_id=' . $contactId . ' but no UFMatch record found. This may cause User.create to fail.');
            }
          }
        } catch (\Exception $userCheckError) {
          self::debugLog('VIT ID createUserSession - User check query failed (may not be available): ' . $userCheckError->getMessage());
        }
        
        // Only create if we still don't have a UFMatch
        if (empty($ufMatch)) {
          // User doesn't exist - create using User.create API
          // User.create will automatically create UFMatch record and set uf_id correctly
          self::debugLog('VIT ID createUserSession - Creating new user via User.create API - Contact ID: ' . $contactId);
          try {
            // Generate a random secure password for SSO user (won't be used for authentication)
            // Password is required by User.create API but authentication is handled by Auth0
            $randomPassword = bin2hex(random_bytes(32));
            
            // Email already retrieved above, use it
            // Create user via User.create API - this automatically creates UFMatch and sets uf_id
            // Log the values we're trying to insert for debugging
            self::debugLog('VIT ID createUserSession - Attempting User.create with: contact_id=' . $contactId . ', name=' . $contact['display_name'] . ', email=' . $email);
            
            // LINE ~388: This is where User.create API is called
            // If this fails with "already exists", check the error logs for constraint name and SQL query
            // Set username to email to ensure uniqueness (username has global unique constraint)
            $userResult = \Civi\Api4\User::create(FALSE)
              ->addValue('contact_id', $contactId)
              ->addValue('name', $contact['display_name'])
              ->addValue('email', $email)
              ->addValue('username', $email)
              ->addValue('password', $randomPassword)
              ->execute();
            
            $user = $userResult->first();
            
            // Get uf_id from the created user
            // In CiviCRM Standalone, User.create returns the user with id = uf_id
            $ufID = $user['id'] ?? NULL;
            
            if (empty($ufID)) {
              // If id is not available, query UFMatch to get it
              $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                ->addWhere('contact_id', '=', $contactId)
                ->execute()
                ->first();
              
              if (!empty($ufMatch)) {
                $ufMatchId = $ufMatch['id'];
                $ufID = $ufMatch['uf_id'];
              } else {
                throw new \Exception('User.create succeeded but UFMatch record not found.');
              }
            } else {
              // uf_id is the user id, UFMatch.id should match it
              $ufMatchId = $ufID;
            }
            
            // Ensure username is set correctly (fallback if User.create didn't accept username parameter)
            // Username must be unique globally, so we set it to email
            if (!empty($ufMatchId)) {
              $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                ->addWhere('id', '=', $ufMatchId)
                ->execute()
                ->first();
              
              if (!empty($ufMatch) && (empty($ufMatch['username']) || $ufMatch['username'] !== $email)) {
                self::debugLog('VIT ID createUserSession - Setting username to email for UFMatch ID: ' . $ufMatchId);
                \Civi\Api4\UFMatch::update(FALSE)
                  ->addWhere('id', '=', $ufMatchId)
                  ->addValue('username', $email)
                  ->execute();
              }
            }
            
            self::debugLog('VIT ID createUserSession - User created successfully - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId . ', uf_id: ' . $ufID . ', username: ' . $email);
            
          } catch (\Exception $e) {
            // Handle race condition: if CREATE fails due to duplicate key, retry query
            $errorMessage = $e->getMessage();
            
            // Log detailed error information including file, line, and exception details
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            $errorCode = method_exists($e, 'getCode') ? $e->getCode() : 'N/A';
            $errorTrace = $e->getTraceAsString();
            
            // Extract SQL error details if available (for DB_Error exceptions)
            $sqlError = '';
            $sqlQuery = '';
            $constraintName = '';
            
            // Try to extract SQL details from exception
            if (is_a($e, 'DB_Error') || (is_object($e) && (property_exists($e, 'userinfo') || method_exists($e, 'getUserInfo')))) {
              if (method_exists($e, 'getUserInfo')) {
                $userInfo = $e->getUserInfo();
                $sqlError = is_array($userInfo) ? json_encode($userInfo) : (string)$userInfo;
              } elseif (property_exists($e, 'userinfo')) {
                $sqlError = json_encode($e->userinfo);
              }
              
              // Try to extract SQL query from trace
              foreach ($e->getTrace() as $trace) {
                if (isset($trace['args']) && is_array($trace['args'])) {
                  foreach ($trace['args'] as $arg) {
                    if (is_string($arg) && (stripos($arg, 'INSERT INTO') !== FALSE || stripos($arg, 'civicrm_uf_match') !== FALSE || stripos($arg, 'civicrm_user') !== FALSE)) {
                      $sqlQuery = substr($arg, 0, 500); // Limit length
                      break 2;
                    }
                  }
                }
              }
              
              // Try to extract constraint name from error message (MySQL format: "Duplicate entry 'value' for key 'constraint_name'")
              if (preg_match("/for key ['\"]([^'\"]+)['\"]/i", $errorMessage, $matches)) {
                $constraintName = $matches[1];
              } elseif (preg_match("/UNIQUE constraint failed: ([^\s]+)/i", $errorMessage, $matches)) {
                $constraintName = $matches[1];
              }
            }
            
            // Log comprehensive error details
            $errorDetails = 'VIT ID createUserSession - User.create FAILED';
            $errorDetails .= ' | Called from: RoleMapper.php line ~393 (User::create()->execute())';
            $errorDetails .= ' | Exception thrown at: ' . $errorFile . ':' . $errorLine;
            $errorDetails .= ' | Error code: ' . $errorCode;
            $errorDetails .= ' | Error message: ' . $errorMessage;
            if (!empty($constraintName)) {
              $errorDetails .= ' | Constraint violated: ' . $constraintName;
            }
            $errorDetails .= ' | Attempted values: contact_id=' . $contactId . ', email=' . ($email ?? 'NULL') . ', name=' . ($contact['display_name'] ?? 'NULL');
            if (!empty($sqlQuery)) {
              $errorDetails .= ' | SQL query: ' . $sqlQuery;
            }
            
            Civi::log()->error($errorDetails);
            
            if (self::isDebugEnabled()) {
              Civi::log()->debug('VIT ID createUserSession - Full exception trace: ' . $errorTrace);
              if (!empty($sqlError)) {
                Civi::log()->debug('VIT ID createUserSession - SQL error details: ' . $sqlError);
              }
              if (!empty($constraintName)) {
                Civi::log()->debug('VIT ID createUserSession - Constraint violation detected: ' . $constraintName);
              }
            }
            
            if (stripos($errorMessage, 'already exists') !== FALSE || 
                stripos($errorMessage, 'duplicate') !== FALSE ||
                stripos($errorMessage, 'unique constraint') !== FALSE) {
              Civi::log()->warning('VIT ID createUserSession - User creation failed (likely race condition or existing user), retrying query - Contact ID: ' . $contactId . ', Email: ' . ($email ?? 'NULL') . ', Error: ' . $errorMessage . ' | Failed at line ' . __LINE__);
              
              // Try multiple query strategies to find the existing user/UFMatch record
              $ufMatch = NULL;
              
              // Strategy 1: Query UFMatch by contact_id (most likely match)
              self::debugLog('VIT ID createUserSession - Retry strategy 1: Querying UFMatch by contact_id=' . $contactId);
              $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                ->addWhere('contact_id', '=', $contactId)
                ->execute()
                ->first();
              
              // Strategy 2: If not found, try querying User by contact_id, then get UFMatch
              if (empty($ufMatch)) {
                self::debugLog('VIT ID createUserSession - Retry strategy 2: Querying User by contact_id=' . $contactId);
                try {
                  $existingUser = \Civi\Api4\User::get(FALSE)
                    ->addWhere('contact_id', '=', $contactId)
                    ->execute()
                    ->first();
                  
                  if (!empty($existingUser)) {
                    self::debugLog('VIT ID createUserSession - Found existing User, querying UFMatch by contact_id');
                    // User exists, now get UFMatch
                    $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                      ->addWhere('contact_id', '=', $contactId)
                      ->execute()
                      ->first();
                  }
                } catch (\Exception $userQueryError) {
                  self::debugLog('VIT ID createUserSession - User query failed: ' . $userQueryError->getMessage());
                }
              }
              
              // Strategy 3: If still not found, try querying by email (might be email conflict)
              if (empty($ufMatch) && !empty($email)) {
                self::debugLog('VIT ID createUserSession - Retry strategy 3: Querying User by email=' . $email);
                try {
                  $existingUserByEmail = \Civi\Api4\User::get(FALSE)
                    ->addWhere('email', '=', $email)
                    ->execute()
                    ->first();
                  
                  if (!empty($existingUserByEmail)) {
                    self::debugLog('VIT ID createUserSession - Found User by email with contact_id=' . ($existingUserByEmail['contact_id'] ?? 'NULL'));
                    // If email matches but contact_id is different, this is a conflict
                    if (!empty($existingUserByEmail['contact_id']) && $existingUserByEmail['contact_id'] == $contactId) {
                      // Same contact, get UFMatch
                      $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                        ->addWhere('contact_id', '=', $contactId)
                        ->execute()
                        ->first();
                    } else {
                      // Different contact - email conflict
                      Civi::log()->error('VIT ID createUserSession - Email conflict: email ' . $email . ' already exists for contact_id=' . ($existingUserByEmail['contact_id'] ?? 'NULL') . ', but trying to create for contact_id=' . $contactId);
                    }
                  }
                } catch (\Exception $emailQueryError) {
                  self::debugLog('VIT ID createUserSession - Email query failed: ' . $emailQueryError->getMessage());
                }
              }
              
              // Strategy 4: Try querying UFMatch with explicit domain_id filters
              if (empty($ufMatch)) {
                self::debugLog('VIT ID createUserSession - Retry strategy 4: Querying UFMatch with domain_id=1');
                $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
                  ->addWhere('contact_id', '=', $contactId)
                  ->addWhere('domain_id', '=', 1)
                  ->execute()
                  ->first();
              }
              
              if (empty($ufMatch)) {
                self::debugLog('VIT ID createUserSession - Retry strategy 5: Querying UFMatch with domain_id IS NULL');
                // Try with domain_id IS NULL using raw query since API4 might not support IS NULL easily
                try {
                  $ufMatchResult = CRM_Core_DAO::executeQuery(
                    "SELECT * FROM civicrm_uf_match WHERE contact_id = %1 AND (domain_id IS NULL OR domain_id = 1) LIMIT 1",
                    [1 => [$contactId, 'Integer']]
                  );
                  if ($ufMatchResult->fetch()) {
                    $ufMatch = [
                      'id' => $ufMatchResult->id,
                      'contact_id' => $ufMatchResult->contact_id,
                      'uf_id' => $ufMatchResult->uf_id,
                      'domain_id' => $ufMatchResult->domain_id,
                    ];
                  }
                } catch (\Exception $rawQueryError) {
                  self::debugLog('VIT ID createUserSession - Raw query failed: ' . $rawQueryError->getMessage());
                }
              }
              
              if (!empty($ufMatch)) {
                $ufMatchId = $ufMatch['id'];
                $ufID = $ufMatch['uf_id'];
                
                // Ensure domain_id is set (may have been NULL)
                if (empty($ufMatch['domain_id'])) {
                  self::debugLog('VIT ID createUserSession - Retry found UFMatch with NULL domain_id, updating to domain_id=1 - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId);
                  \Civi\Api4\UFMatch::update(FALSE)
                    ->addWhere('id', '=', $ufMatchId)
                    ->addValue('domain_id', 1)
                    ->execute();
                }
                
                self::debugLog('VIT ID createUserSession - Retry found existing UFMatch - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId . ', uf_id: ' . $ufID);
              } else {
                // If all retry strategies fail, log detailed error and rethrow
                Civi::log()->error('VIT ID createUserSession - User creation failed and all retry queries returned no results - Contact ID: ' . $contactId . ', Email: ' . ($email ?? 'NULL') . ', Error: ' . $errorMessage);
                self::debugLog('VIT ID createUserSession - All retry strategies exhausted. User.create failed but no existing record found.');
                throw $e;
              }
            } else {
              // For other errors, rethrow
              Civi::log()->error('VIT ID createUserSession - User creation failed with unexpected error - Contact ID: ' . $contactId . ', Error: ' . $errorMessage);
              throw $e;
            }
          }
        }
      }

      self::debugLog('VIT ID createUserSession - UFMatch ID: ' . $ufMatchId . ', Contact ID: ' . $contactId . ', uf_id: ' . $ufID);

      // Assign roles to user via civicrm_user_role table
      // The user_id in civicrm_user_role is the UFMatch.id (not contact_id)
      if (!empty($civiRoles) && !empty($ufMatchId)) {
        try {
          // Delete existing roles for this user
          CRM_Core_DAO::executeQuery(
            "DELETE FROM civicrm_user_role WHERE user_id = %1",
            [1 => [$ufMatchId, 'Integer']]
          );

          self::debugLog('VIT ID createUserSession - Assigning roles: ' . json_encode($civiRoles) . ' to user_id: ' . $ufMatchId);

          // Insert new role assignments using INSERT IGNORE to handle race conditions
          // This prevents duplicate key errors if DELETE didn't complete or concurrent requests occur
          foreach ($civiRoles as $roleId) {
            try {
              CRM_Core_DAO::executeQuery(
                "INSERT IGNORE INTO civicrm_user_role (user_id, role_id) VALUES (%1, %2)",
                [
                  1 => [$ufMatchId, 'Integer'],
                  2 => [$roleId, 'Integer'],
                ]
              );
            } catch (\Exception $e) {
              // If INSERT IGNORE still fails (shouldn't happen, but handle gracefully)
              $errorMessage = $e->getMessage();
              if (stripos($errorMessage, 'already exists') !== FALSE || 
                  stripos($errorMessage, 'duplicate') !== FALSE ||
                  stripos($errorMessage, 'unique constraint') !== FALSE) {
                // Role already exists, which is fine - log and continue
                self::debugLog('VIT ID createUserSession - Role ' . $roleId . ' already assigned to user_id ' . $ufMatchId . ', skipping');
              } else {
                // Unexpected error, log and rethrow
                Civi::log()->error('VIT ID createUserSession - Error inserting role ' . $roleId . ' for user_id ' . $ufMatchId . ': ' . $errorMessage);
                throw $e;
              }
            }
          }

          // Verify roles were assigned
          $result = CRM_Core_DAO::executeQuery(
            "SELECT role_id FROM civicrm_user_role WHERE user_id = %1",
            [1 => [$ufMatchId, 'Integer']]
          );
          $assignedRoles = [];
          while ($result->fetch()) {
            $assignedRoles[] = $result->role_id;
          }
          self::debugLog('VIT ID createUserSession - Successfully assigned roles: ' . json_encode($assignedRoles));

        } catch (\Exception $e) {
          Civi::log()->error('VIT ID createUserSession - Error assigning roles: ' . $e->getMessage() . ' | Contact ID: ' . $contactId . ' | UFMatch ID: ' . $ufMatchId);
          throw $e;
        }
      } else {
        self::debugLog('VIT ID createUserSession - No roles to assign or UFMatch ID missing');
      }

      // Set session
      $session = \CRM_Core_Session::singleton();
      
      // CiviCRM 6.7.2 with Redis session handler compatibility
      // Redis session handler automatically manages session initialization
      $sessionHandler = ini_get('session.save_handler');
      
      if (self::isDebugEnabled()) {
        $sessionSavePath = session_save_path();
        self::debugLog('VIT ID createUserSession - Session handler: ' . $sessionHandler . ', Save path: ' . ($sessionSavePath ?: 'default'));
        
        if ($sessionHandler === 'redis' && $sessionSavePath) {
          self::debugLog('VIT ID createUserSession - Using Redis session handler: ' . $sessionSavePath);
        }
        
        $currentSessionId = session_id();
        if (!empty($currentSessionId)) {
          self::debugLog('VIT ID createUserSession - Session ID: ' . $currentSessionId);
        }
      }
      
      // Set session variables
      $session->set('userID', $contactId);
      $session->set('ufID', $ufID);
      
      // Mark this as VIT ID login
      $session->set('vitid_auth0_login', TRUE);

      // Store contact details in session
      if (!empty($contact['display_name'])) {
        $session->set('userDisplayName', $contact['display_name']);
      }
      
      // Verify session data was set correctly
      if (self::isDebugEnabled() && isset($_SESSION['CiviCRM'])) {
        $sessionId = session_id();
        self::debugLog('VIT ID createUserSession - Session ID: ' . ($sessionId ?: 'NULL') . ', CiviCRM session keys: ' . json_encode(array_keys($_SESSION['CiviCRM'])));
      }

      // Verify session was set correctly (always log this, not just debug mode)
      $verifyUserID = $session->get('userID');
      $verifyUfID = $session->get('ufID');
      
      if ($verifyUserID != $contactId || $verifyUfID != $ufID) {
        Civi::log()->error('VIT ID createUserSession - Session verification failed! Expected userID: ' . $contactId . ', got: ' . ($verifyUserID ?? 'NULL') . ' | Expected ufID: ' . $ufID . ', got: ' . ($verifyUfID ?? 'NULL'));
        throw new \Exception('Session variables were not set correctly.');
      }
      
      // Always log successful session creation (not just debug mode)
      Civi::log()->info('VIT ID createUserSession - Session created successfully - Contact ID: ' . $contactId . ', UFMatch ID: ' . $ufMatchId . ', ufID: ' . $ufID . ', Roles: ' . json_encode($civiRoles ?? []));

      // Note: In CiviCRM Standalone, setting session variables (userID, ufID) is sufficient
      // The setUserContext() method doesn't exist in CRM_Utils_System_Standalone
      // Session is already properly initialized above

      return TRUE;

    } catch (\Exception $e) {
      // Provide detailed error logging to identify which operation failed
      $errorMessage = $e->getMessage();
      $errorDetails = 'Failed to create user session';
      
      // Identify the specific operation that failed based on error message
      if (stripos($errorMessage, 'contact not found') !== FALSE) {
        $errorDetails = 'Failed to create user session: Contact validation failed';
      } elseif (stripos($errorMessage, 'User') !== FALSE || 
                 stripos($errorMessage, 'UFMatch') !== FALSE || 
                 stripos($errorMessage, 'already exists') !== FALSE ||
                 stripos($errorMessage, 'duplicate') !== FALSE ||
                 stripos($errorMessage, 'unique constraint') !== FALSE) {
        $errorDetails = 'Failed to create user session: User creation/retrieval failed';
      } elseif (stripos($errorMessage, 'role') !== FALSE) {
        $errorDetails = 'Failed to create user session: Role assignment failed';
      } elseif (stripos($errorMessage, 'session') !== FALSE) {
        $errorDetails = 'Failed to create user session: Session variable setting failed';
      }
      
      // Log detailed error information
      Civi::log()->error('VIT ID createUserSession - ' . $errorDetails . ' | Contact ID: ' . $contactIdForError . ' | Error: ' . $errorMessage);
      
      // In debug mode, also log the stack trace
      if (self::isDebugEnabled()) {
        Civi::log()->debug('VIT ID createUserSession - Stack trace: ' . $e->getTraceAsString());
      }
      
      return FALSE;
    }
  }

  /**
   * Validate user data from Auth0.
   *
   * @param array $userData User data from Auth0
   * @return array ['valid' => bool, 'error' => string]
   */
  public static function validateUser($userData) {
    self::debugLog('VIT ID validateUser - Received user data keys: ' . json_encode(array_keys($userData)));
    self::debugLog('VIT ID validateUser - CiviCRM ID: ' . ($userData['civicrm_id'] ?? 'NOT SET'));
    self::debugLog('VIT ID validateUser - Auth0 roles: ' . json_encode($userData['roles'] ?? []));
    
    // Check if civicrm_id is present
    if (empty($userData['civicrm_id'])) {
      Civi::log()->warning('VIT ID validateUser - No civicrm_id found in user data');
      return [
        'valid' => FALSE,
        'error' => 'Your VIT ID account is not linked to a CiviCRM contact. Please contact your administrator.',
      ];
    }

    // Verify contact exists
    try {
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', $userData['civicrm_id'])
        ->execute()
        ->first();

      if (empty($contact)) {
        Civi::log()->warning('VIT ID validateUser - Contact ID ' . $userData['civicrm_id'] . ' not found in CiviCRM');
        return [
          'valid' => FALSE,
          'error' => 'The CiviCRM contact linked to your VIT ID account was not found. Please contact your administrator.',
        ];
      }
      
      self::debugLog('VIT ID validateUser - Contact validated: ' . $contact['display_name']);

    } catch (\Exception $e) {
      Civi::log()->error('Contact validation error: ' . $e->getMessage());
      return [
        'valid' => FALSE,
        'error' => 'An error occurred while validating your account. Please try again or contact your administrator.',
      ];
    }

    // Check if user has valid role mappings
    $auth0Roles = $userData['roles'] ?? [];
    self::debugLog('VIT ID validateUser - Checking roles: ' . json_encode($auth0Roles));
    
    if (!self::hasValidRoles($auth0Roles)) {
      Civi::log()->warning('VIT ID validateUser - No valid role mappings found for roles: ' . json_encode($auth0Roles));
      return [
        'valid' => FALSE,
        'error' => 'You don\'t have the required permissions to access CiviCRM. Please contact your administrator.',
      ];
    }
    
    Civi::log()->info('VIT ID validateUser - User validation successful');
    return ['valid' => TRUE, 'error' => NULL];
  }
}
