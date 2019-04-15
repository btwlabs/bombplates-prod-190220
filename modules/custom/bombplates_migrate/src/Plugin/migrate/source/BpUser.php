<?php

/**
 * @file
 *  Contains \Drupal\bombplates_migrate\Plugin\migrate\source\BpUser
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract users from Bombplates d6 database
 *
 * @MigrateSource(
 *   id = "bombplates_user"
 * )
 */
class BpUser extends DrupalSqlBase {

  /**
   * Profile fields (machine_name => fid) from old database
   *
   * @var array
   */
  protected $profileFields;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $conf, $p_id, $p_def, \Drupal\migrate\Plugin\MigrationInterface $m, \Drupal\Core\State\StateInterface $s, \Drupal\Core\Entity\EntityManagerInterface $em) {
    parent::__construct($conf, $p_id, $p_def, $m, $s, $em);
    $this->profileFields = FALSE;
  } // __construct

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u', array_keys($this->baseFields()))
      ->condition('u.uid', 0, '>');
  } // query

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['roles'] = $this->t('Roles');
    $fields['missed_payments'] = $this->t('Missed Payments');
    $fields['pw_sync_key'] = $this->t('Password Sync Key');
    $fields['websites'] = $this->t('Websites');
    $fields['subdomain'] = $this->t('Subdomain');
    $fields['trial_ends'] = $this->t('Trial Ends');
    $fields['band_name'] = $this->t('BandName');
    $fields['artists_referred'] = $this->t('Artists Referred');
    $fields['referral_entered'] = $this->t('Referral Entered');
    $fields['suspended'] = $this->t('Suspended');
    $fields['billing_status'] = $this->t('Billing Status');
    $fields['last_payment'] = $this->t('Last Payment');
    $fields['next_payment'] = $this->t('Next Payment');
    $fields['stripe_customer'] = $this->t('Stripe Customer ID');
    $fields['stripe_subscription'] = $this->t('Stripe Subscription ID');
    $fields['accounts_managed'] = $this->t('Accounts Managed');
    $fields['arb_subscription_id'] = $this->t('Authorize.net ARB Subscription');
    return $fields;
  } // fields

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $uid = $row->getSourceProperty('uid');
    $db = $this->getDatabase();
    if (!isset($this->profileFields) || !$this->profileFields) {
      $this->initProfileFields();
    }
    foreach ($this->fieldMapping() AS $profile_name => $mapping) {
      if (isset($this->profileFields["profile_$profile_name"])) {
        $fid = $this->profileFields["profile_$profile_name"];
        $results = $db->query(
          'SELECT value FROM {profile_values} WHERE uid=:uid AND fid=:fid',
          [':uid' => $uid, ':fid' => $fid]
        );
        foreach ($results AS $raw) {
          $value = isset($mapping['function']) ? $this->{$mapping['function']}($raw->value) : $raw->value;
          $row->setSourceProperty($mapping['field'], $value);
        }
      } // if fid
    } // foreach mapping fieldMapping
    $query = 'SELECT n.title
      FROM {node} n RIGHT JOIN {users} u ON n.uid = u.uid
      WHERE n.type=:type AND n.status=1 AND n.uid=:uid
      ORDER BY n.nid DESC
      LIMIT 1
    ';
    $results = $db->query($query, [':type' => 'authorize_net_arb_subscription', ':uid' => $uid]);
    foreach ($results AS $raw) {
      $row->setSourceProperty('arb_subscription_id', $raw->title);
    }
    $roles_map = $this->roleMapping();
    $query = 'SELECT rid FROM {users_roles} WHERE uid=:uid';
    $results = $db->query($query, [':uid' => $uid]);
    $roles = [];
    foreach ($results AS $raw) {
      if (isset($roles_map[$raw->rid])) {
        $roles = array_merge($roles, $roles_map[$raw->rid]);
      }
    } // foreach raw in result
    $roles = array_unique($roles);
    if (empty($roles) || !in_array('bombplate_pre_launch', $roles)) {
      $roles[] = 'bombplate_account';
    }
    $row->setSourceProperty('roles', $roles);
    return parent::prepareRow($row);
  } // prepareRow

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  } // getIds

  /**
   * {@inheritdoc}
   */
  public function bundleMigrationRequired() {
    return FALSE;
  } // bundleMigrationRequired

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return 'user';
  } // entityTypeId

  /**
   * Returns the user base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = [
      'uid' => $this->t('User Id'),
      'name' => $this->t('User Name'),
      'pass' => $this->t('Password Hash'),
      'mail' => $this->t('Email Address'),
      'mode' => $this->t('Comment Mode'),
      'sort' => $this->t('Comment Sorting'),
      'threshold' => $this->t('Comment Threshold'),
      'theme' => $this->t('Theme'),
      'signature' => $this->t('Signature'),
      'created' => $this->t('Join Date'),
      'access' => $this->t('Last Access'),
      'login' => $this->t('Last Login'),
      'status' => $this->t('Status'),
      'timezone' => $this->t('Timezone'),
      'language' => $this->t('Preferred language'),
      'picture' => $this->t('Avatar'),
      'init' => $this->t('Initial Email'),
      'data' => $this->t('Data'),
      'signature_format' => $this->t('Signature Format'),
      'timezone_name' => $this->t('Timezone Name'),
      'salt' => $this->t('Password Salt'),
    ];
    return $fields;
  } // baseFields

  /**
   * Return a mapping of field names matched to their new name and the function to convert data
   *
   * @return array
   *    Keys are d6 field names. Values are arrays with d8 field name and (optional) function name
   */
  protected function fieldMapping() {
    return [
      'missed_payments' => ['field' => 'missed_payments'],
      'pw_integrate_key' => ['field' => 'pw_sync_key'],
      'websites' => ['field' => 'websites', 'function' => 'convertLineBreaks'],
      'subdomain' => ['field' => 'subdomain'],
      'trial_ends' => ['field' => 'trial_ends', 'function' => 'convertDate'],
      'band_name' => ['field' => 'band_name'],
      'artists_referred' => ['field' => 'artists_referred'],
      'referral_entered' => ['field' => 'referral_entered'],
      'suspended' => ['field' => 'suspended'],
      'billing_status' => ['field' => 'billing_status'],
      'last_payment' => ['field' => 'last_payment', 'function' => 'convertDate'],
      'next_payment' => ['field' => 'next_payment', 'function' => 'convertDate'],
      'stripe_customer' => ['field' => 'stripe_customer'],
      'stripe_subscription' => ['field' => 'stripe_subscription'],
      'accounts_managed' => ['field' => 'accounts_managed', 'function' => 'convertUids'],
    ];
  } // fieldMapping

  /**
   * Return a mapping of d6 to d8 role IDs
   *
   * @return array
   *    keys are d6 role IDs. Values are an array of d8 ones
   */
  protected function roleMapping() {
    return [
      9 => ['account_manager'],
      10 => ['billing'],
      5 => ['customer', 'bombplate_account'],
      4 => ['bombplate_pre_launch'],
      3 => ['administrator'],
    ];
  } // roleMapping

  /**
   * Convert a timestamp int a date field for storage
   *
   * @param string $raw
   *    A unix timestamp
   * @return string
   *    Date in the expected format
   */
  protected function convertDate($raw) {
    return date('Y-m-j\TH:i:s', (int)$raw);
  } // convertDate

  /**
   * Convert a comma-separated list of uids into entity references for storage
   *
   * @param $raw
   *    Comma-separated list of ints
   * @return array
   *    of integers
   */
  protected function convertUids($raw) {
    return explode(',', $raw);
  } // convertUids

  /**
   * Explode a field by linebreaks
   *
   * @param $raw
   *    Newline separated list of websites
   * @param array
   *    Of urls
   */
  protected function convertLineBreaks($raw) {
    return explode("\n", $raw);
  } // convertLineBreaks

  /**
   * Initialize $profileFields
   *
   * @return array
   *    The new value of $profileFields
   */
  protected function initProfileFields() {
    $this->profileFields = [];
    $records = $this->getDatabase()->query('SELECT name, fid FROM {profile_fields} WHERE name IS NOT NULL');
    foreach ($records AS $record) {
      $this->profileFields[$record->name] = $record->fid;
    } // foreach record in records
    return $this->profileFields;
  } // initProfileFields

} // BpUser
