<?php
/**
 * @file
 * Contains Drupal\iplog\IpRages.
 */
/*
 * Name: iplog_class_ranges.php   V2.0 11/15/19
 *
 */
namespace Drupal\iplog;

class IpRanges{
  
  const IPRANGESTBL = "ip_ranges";
 

  public function setIpRange($range,$type) {
    db_insert(self::IPRANGESTBL)
      ->fields(array('ip' => $range, 'type' => $type))
      ->execute();
  }
  
  public function deleteIpRange($range) {

  }
  
  public function getIpRanges() {
    $query = db_select(self::IPRANGESTBL, 'r')
      ->fields('r');
    $result = $query->execute();
    $ranges = array();
    do {
      $record = $result->fetchAssoc();
      if(empty($record)) {break;}
      $bid = $record['bid'];
      $ranges[$bid]['type'] = $record['type'];
      $ranges[$bid]['ipRange'] = $record['ip'];
    } while (TRUE);   
    return $ranges;
  }
  
}
