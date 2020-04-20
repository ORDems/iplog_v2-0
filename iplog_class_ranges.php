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
 

  public function setIpRange($range,$type,$source,$netName) {
    db_insert(self::IPRANGESTBL)
      ->fields(array(
        'ip' => $range, 
        'type' => $type,
        'source' => $source,
        'netname' => $netName,
        ))
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
      $ranges[$bid]['source'] = $record['source'];
      $ranges[$bid]['netName'] = $record['netname'];
    } while (TRUE);   
    return $ranges;
  }
  
  public function resetIpRanges($type) {
    db_delete(self::IPRANGESTBL)
      ->condition('type', $type)
      ->execute();
  }
  
  public function firstDecIp($ipRange) {
    $rangeParts = explode('-',$ipRange);
    $subBlocks = explode('.',$rangeParts[0]);
    $ip = 0;
    foreach ($subBlocks as $subBlock) {
      $ip = $ip*256+$subBlock;
    }
    return $ip;
  }
  
  public function firstDecCidr($cidr) {
    $rangeBase = explode('/',$cidr);
    $subBlocks = explode('.',$rangeBase[0]);
    $ip = 0;
    foreach ($subBlocks as $subBlock) {
      $ip = $ip*256+$subBlock;
    }
    return $ip;
  }
}
