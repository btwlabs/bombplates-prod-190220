<?php

/**
 * @file
 *  Contains \Drupal\bombplates_migrate\Plugin\migrate\source\BpTerm
 */

namespace Drupal\bombplates_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract taxonomy terms from Bombplates d6 database
 *
 * @MigrateSource(
 *   id = "bombplates_term"
 * )
 */
class BpTerm extends DrupalSqlBase {

  /**
   * mapping old vocabulary IDs to new ones
   *
   * @var array
   */
  protected $vidMap;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $conf, $p_id, $p_def, \Drupal\migrate\Plugin\MigrationInterface $m, \Drupal\Core\State\StateInterface $s, \Drupal\Core\Entity\EntityManagerInterface $em) {
    parent::__construct($conf, $p_id, $p_def, $m, $s, $em);
    $this->vidMap = FALSE;
  } // __construct

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('term_data', 'td')
      ->fields('td');
  } // query

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'tid' => $this->t('Term ID'),
      'vid' =>  $this->t('Vocabulary ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
    ];
  } // fields

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $do_not_skip = FALSE;
    $vid = $row->getSourceProperty('vid');
    if (!isset($this->vidMap) || !$this->vidMap) {
      $this->initVidMap();
    } // !vidMap
    if (isset($this->vidMap[$vid])) {
      $row->setSourceProperty('vid', $this->vidMap[$vid]);
      $do_not_skip = TRUE;
    }
    $do_not_skip &= parent::prepareRow($row);
    return $do_not_skip;
  } // prepareRow

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tid' => [
        'type' => 'integer',
        'alias' => 'tid',
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
   * Build a map of old vocabulary IDs to new ones
   *
   * @return array
   *    Keys are old vids. Valdues are new ones
   */
  protected function initVidMap() {
    $vidMap = [];
    $results = $this->select('vocabulary', 'v')->fields('v', ['vid', 'name'])->execute();
    foreach ($results AS $record) {
      if ($record['name'] == 'Genre') {
        $vidMap[$record['vid']] = 'genre';
      }
      elseif ($record['name'] == 'Price Level' || $record['name'] == 'Partner Integration Category') {
        $vidMap[$record['vid']] = 'partner_integration_category';
      }
    } // foreach record in results
    $this->vidMap = $vidMap;
    return $vidMap;
  } // initVidMap
} // BpTerm
