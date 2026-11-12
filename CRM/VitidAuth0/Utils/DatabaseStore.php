<?php

use Auth0\SDK\Contract\StoreInterface;

/**
 * Auth0 SDK transient storage (state, nonce, max_age, PKCE code verifier) kept in
 * the civicrm_vitid_auth0_state table, scoped to one browser.
 *
 * The SDK uses fixed key names for every login, so each row is stored as
 * "<browser id>:<sdk key>". The browser id is a random value kept in the
 * CiviCRM session, which already carries the post-login return URL across the
 * round trip to Auth0. Two people logging in at the same time therefore never
 * read, overwrite or delete each other's values.
 *
 * A login that comes back to a different hostname than it started on has a
 * different session, finds none of its values, and fails the SDK's state check;
 * the user is sent back to the login page to start again.
 */
class CRM_VitidAuth0_Utils_DatabaseStore implements StoreInterface {

  /**
   * CiviCRM session key holding this browser's id.
   */
  const SESSION_KEY = 'vitid_auth0_browser_id';

  /**
   * How long a value lives; an abandoned login's rows are deleted after this.
   */
  const TTL_SECONDS = 3600;

  /**
   * @var string "<browser id>:"
   */
  private $prefix;

  /**
   * @var bool
   */
  private $deferring = FALSE;

  /**
   * @var array
   */
  private $deferred = [];

  public function __construct() {
    $session = CRM_Core_Session::singleton();
    $browserId = $session->get(self::SESSION_KEY);
    if (!is_string($browserId) || !preg_match('/^[a-f0-9]{32}$/', $browserId)) {
      $browserId = bin2hex(random_bytes(16));
      $session->set(self::SESSION_KEY, $browserId);
    }
    $this->prefix = $browserId . ':';
  }

  /**
   * Defer saving state changes to destination to improve performance during blocks of changes.
   *
   * @param bool $deferring
   */
  public function defer(bool $deferring): void {
    $this->deferring = $deferring;

    if (!$deferring && !empty($this->deferred)) {
      foreach ($this->deferred as $key => $value) {
        $this->persist($key, $value);
      }
      $this->deferred = [];
    }
  }

  /**
   * Remove a value from the store.
   *
   * @param string $key key to delete
   */
  public function delete(string $key): void {
    unset($this->deferred[$key]);

    try {
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_vitid_auth0_state WHERE `key` = %1",
        [1 => [$this->prefix . $key, 'String']]
      );
    }
    catch (Exception $e) {
      Civi::log()->error('VIT ID DatabaseStore: Error deleting key ' . $key . ': ' . $e->getMessage());
    }
  }

  /**
   * Get a value from the store by a given key.
   *
   * @param string $key     key to get
   * @param mixed  $default return value if key not found
   *
   * @return mixed
   */
  public function get(string $key, $default = NULL) {
    if ($this->deferring && array_key_exists($key, $this->deferred)) {
      return $this->deferred[$key];
    }

    try {
      $result = CRM_Core_DAO::executeQuery(
        "SELECT `value` FROM civicrm_vitid_auth0_state WHERE `key` = %1 AND `expire` >= %2",
        [
          1 => [$this->prefix . $key, 'String'],
          2 => [time(), 'Integer'],
        ]
      );

      if ($result->fetch()) {
        return unserialize($result->value, ['allowed_classes' => FALSE]);
      }
    }
    catch (Exception $e) {
      Civi::log()->error('VIT ID DatabaseStore: Error getting key ' . $key . ': ' . $e->getMessage());
    }

    return $default;
  }

  /**
   * Remove all of this browser's stored values.
   */
  public function purge(): void {
    $this->deferred = [];
    try {
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_vitid_auth0_state WHERE `key` LIKE %1",
        [1 => [$this->prefix . '%', 'String']]
      );
    }
    catch (Exception $e) {
      Civi::log()->error('VIT ID DatabaseStore: Error purging values: ' . $e->getMessage());
    }
  }

  /**
   * Set a value on the store.
   *
   * @param string $key   key to set
   * @param mixed  $value value to set
   */
  public function set(string $key, $value): void {
    if ($this->deferring) {
      $this->deferred[$key] = $value;
      return;
    }

    $this->persist($key, $value);
  }

  /**
   * Persist a value to the database, first deleting any expired rows.
   *
   * @param string $key
   * @param mixed $value
   */
  private function persist(string $key, $value): void {
    try {
      CRM_Core_DAO::executeQuery(
        "DELETE FROM civicrm_vitid_auth0_state WHERE `expire` < %1",
        [1 => [time(), 'Integer']]
      );

      CRM_Core_DAO::executeQuery(
        "INSERT INTO civicrm_vitid_auth0_state (`key`, `value`, `expire`) VALUES (%1, %2, %3)
         ON DUPLICATE KEY UPDATE `value` = %2, `expire` = %3",
        [
          1 => [$this->prefix . $key, 'String'],
          2 => [serialize($value), 'String'],
          3 => [time() + self::TTL_SECONDS, 'Integer'],
        ]
      );
    }
    catch (Exception $e) {
      Civi::log()->error('VIT ID DatabaseStore: Error setting key ' . $key . ': ' . $e->getMessage());
    }
  }

}
