<?php

/**
 * @file
 *  Contains \Drupal\bombplates_migrate\Plugin\migrate\destination\BpDestinationTable
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\destination;

use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Row;

/**
 * Insert data into a whitelisted table
 *
 * @MigrateDestination(
 *   id = "bombplates_destination_table"
 * )
 */
class BpDestinationTable extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The DB connection
   *
   * @var \Drupal\Core\Databse\Connection $connection
   */
  protected $connection;

  /**
   * Whitelist of tables and fields
   *
   * @var array $tables
   */
  protected $tables;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $conf, $plugin_id, $plugin_definition, MigrationInterface $migration, $connection) {
    $this->connection = $connection;
    $this->tables = [
      'bombplates_account_commands' => [
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
      throw new \Drupal\migrate\MigrateException(sprintf('Invalid destination table "%s" specified', $conf['table']));
    }
    parent::__construct($conf, $plugin_id, $plugin_definition, $migration, $connection);
  } // __construct

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('database')
    );
  } // create

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
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
  public function import(Row $row, array $old_destination_values = []) {
    $field_names = array_keys($this->fields());
    $fields = [];
    foreach ($field_names AS $field_name) {
      $fields[$field_name] = $row->getDestinationProperty($field_name);
    } // foreach field_name in field_names
    return [$this->connection->insert($this->configuration['table'])
      ->fields($fields)
      ->execute()];
  } // import

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

} // BpDestinationTable
