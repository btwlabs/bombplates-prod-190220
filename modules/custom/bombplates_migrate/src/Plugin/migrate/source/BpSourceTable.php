<?php

/**
 * @file
 *  Contains \Drupal\bombplates_migrate\Plugin\migrate\source\BpSourceTable
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Extract data from a whitelisted d6 table
 *
 * @MigrateSource(
 *   id = "bombplates_source_table"
 * )
 */
class BpSourceTable extends DrupalSqlBase {

  /**
   * Whitelist of tables and fields
   *
   * @var array
   */
  protected $tables;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $conf, $p_id, $p_def, MigrationInterface $m, StateInterface $s, EntityManagerInterface $em) {
    $this->tables = [
      'account_commands' => [
        'key' => ['cid' => ['type' => 'integer']],
        'fields' =>  [
          'cid' => $this->t('Command ID'),
          'server' => $this->t('Hosting Server'),
          'command' => $this->t('Shell command run'),
          'time_sent' => $this->t('Timestamp of command run'),
        ],
      ],
    ];
    if (!isset($conf['table']) || !$this->getTableFields($conf['table'])) {
      throw new \Drupal\migrate\MigrateException(sprintf('Invalid source table "%s" specified', $conf['table']));
    }
    parent::__construct($conf, $p_id, $p_def, $m, $s, $em);
  } // __construct

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select($this->configuration['table'], 't')
      ->fields('t');
  } // query

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return $this->getTableFields($this->configuration['table']);
  } // fields

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->getTableFields($this->configuration['table'], TRUE);
  } // getIds

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    return TRUE;
  } // prepareRow

  /**
   * Return relevant fields for whichever table we're using
   *
   * @param string $table
   *    Name of the table to pull from.
   * @param boolean $key
   *    Return primary key instead of full field list
   * @return mixed
   *    Array per fields() or getIds() or FALSE on failure
   */
  protected function getTableFields($table, $key = FALSE) {
    return isset($this->tables[$table]) ? ($key ? $this->tables[$table]['key'] : $this->tables[$table]['fields']) : FALSE;
  } // getTableFields

} // BpSourceTable
