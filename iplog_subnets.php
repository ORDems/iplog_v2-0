<?php
/*
 * Name: iplog_subnets.php   V2.0  11/15/19
 */

require_once "iplog_class_log.php";
require_once "iplog_class_ranges.php";

use Drupal\iplog\IpLog;
use Drupal\iplog\IpRanges;

/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * nlp_404_tracker
 *
 * @return string - HTML for display.
 */

define('DD_CIDR_FILE','knownipaddrs');
define('DD_BLOCKED_FILE','blockedipaddrs');

function iplog_subnets_form($form, &$form_state) {
  //iplog_debug_msg('formstateform',$form_state);
  if(!isset($form_state['iplog']['reenter'])) {
    $form_state['iplog']['page'] = 'function';
    $form_state['iplog']['reenter'] = TRUE;
  }
  $page = $form_state['iplog']['page'];
  switch ($page) {
    
    case 'function':
      $functions = array(
        'refreshIP'=>'Refresh Known IP adddesses',
        'exportIP' => 'Export known IP addresses',
        'ipLog' => 'Display IP Log',
        'iplogrefresh' => 'Remove unknown from log',
        'refreshBlockedIP' => 'Refresh blocked IP',
        'exportBlockedIP' => 'Export blocked IP',
      );
      $form['function'] = array(
        '#type' => 'radios',
        '#title' => t('Select an IP function.'),
        '#options' => $functions,
        '#description' => t('Select one of the IP management functions.'),
        //'#access' => $admin,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#id' => 'Submit',
      );
      break;
    
    case 'refreshIP':
      $form['knownIPFile'] = array(
          '#type' => 'file',
          '#title' => t('A file with IP addresses to mark as known and safe.'),
          '#size' => 75,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#id' => 'Submit',
      );
      break;
    
    case 'exportIP':
      $uri = $form_state['iplog']['exporturi'];
      $url = file_create_url($uri);
      $output = '<a href="'.$url.'">Right-click to download the known IP address file.  </a>';
      $form['filelink'] = array (
        '#type' => 'markup',
        '#markup' => $output,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Continue'),
        '#id' => 'Submit',
      );
      $form_state['iplog']['page'] = 'function';
      break;
    
    case 'ipLog':
      $ipLogObj = new IpLog();
      $ipLog = $ipLogObj->getIpLog();
      //iplog_debug_msg('iplog',$ipLog);
      $output = '';
      foreach ($ipLog as $ipKey => $ipRecord) {
        $output .= $ipKey.', '.$ipRecord['ipAddr'].', '.$ipRecord['hits'].', '.$ipRecord['orgId']."<br>";
      }
      $form['iplog'] = array (
        '#type' => 'markup',
        '#markup' => $output,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Continue'),
        '#id' => 'Submit',
      );
      
      $form_state['iplog']['page'] = 'function';
      break;
      
    case 'refreshBlockedIP':
      $form['blockedIPFile'] = array(
          '#type' => 'file',
          '#title' => t('A file with IP addresses to block.'),
          '#size' => 75,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#id' => 'Submit',
      );
      break;
      
    case 'exportBlockedIP':
      $uri = $form_state['iplog']['exporturi'];
      $url = file_create_url($uri);
      $output = '<a href="'.$url.'">Right-click to download the blocked IP address file.  </a>';
      $form['filelink'] = array (
        '#type' => 'markup',
        '#markup' => $output,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Continue'),
        '#id' => 'Submit',
      );
      $form_state['iplog']['page'] = 'function';
      break;
  }
  return $form;
}

function iplog_subnets_form_submit($form, &$form_state) {
  $form_state['iplog']['reenter'] = true;
  $form_state['rebuild'] = true;
  $ipLogObj = new IpLog();
  $page = $form_state['iplog']['page'];
  switch ($page) {
    case 'function':
      if(!isset($form_state['input']['function'])) {break;}
      $function = $form_state['input']['function'];
      switch ($function) {
        
        case 'refreshIP':
          $form_state['iplog']['page'] = $function;
          break;
        
        case 'exportIP':
          $temp_dir = 'public://temp';
          $cdate = date('Y-m-d-H-i-s',time());
          $uri = $temp_dir.'/'.DD_CIDR_FILE.'-'.$cdate.'.csv';
          $fileObj = file_save_data('', $uri, FILE_EXISTS_REPLACE);
          $fileObj->status = 0;
          file_save($fileObj);
          $fh = fopen($uri,"w");
          $subnets = $ipLogObj->getIpAddrs();
          foreach ($subnets as $cidr => $name) {
            $cidrRecord = array();
            $cidrRecord[0] = $cidr;
            $cidrRecord[1] = $name['orgId'];
            $cidrRecord[2] = $name['type'];
            fputcsv($fh, $cidrRecord);
          }
          fclose($fh);
          $form_state['iplog']['exporturi'] = $uri;
          $form_state['iplog']['page'] = $function;
          break;
          
        case 'ipLog':
          $form_state['iplog']['page'] = $function;
          break;
        
        case 'iplogrefresh':
          $ipLogObj = new IpLog();
          $ipLog = $ipLogObj->getIpLog();
          //iplog_debug_msg('iplog',$ipLog);
          foreach ($ipLog as $ipKey => $ipRecord) {
            if($ipRecord['orgId'] == 'unknown' OR $ipRecord['orgId'] == 'user') {
              $ipLogObj->deleteIpLogEntry($ipKey);
            }
          }
          $form_state['iplog']['page'] = 'function';
          break;
        
        case 'refreshBlockedIP':
          $form_state['iplog']['page'] = $function;
          break;
        
        case 'exportBlockedIP':
          $ipRangesObj = new IpRanges();
          $temp_dir = 'public://temp';
          $cdate = date('Y-m-d-H-i-s',time());
          $uri = $temp_dir.'/'.DD_BLOCKED_FILE.'-'.$cdate.'.csv';
          $fileObj = file_save_data('', $uri, FILE_EXISTS_REPLACE);
          $fileObj->status = 0;
          file_save($fileObj);
          $fh = fopen($uri,"w");
          $ipRanges = $ipRangesObj->getIpRanges();
          //iplog_debug_msg('subnets',$subnets);
          foreach ($ipRanges as $bid => $blocked) {
            $blockedRecord = array();
            $blockedRecord[0] = $bid;
            $blockedRecord[1] = $blocked['type'];
            $blockedRecord[2] = $blocked['ipRange'];
            $blockedRecord[3] = $blocked['source'];
            $blockedRecord[4] = $blocked['netName'];
            fputcsv($fh, $blockedRecord);
          }
          fclose($fh);
          $form_state['iplog']['exporturi'] = $uri;
          $form_state['iplog']['page'] = $function;
          break;
      }
      break;
    
    case 'refreshIP':
      //iplog_debug_msg('files',$_FILES['files']);
      $knownIPFile = $_FILES['files']['name']['knownIPFile'];
      $knownIPFileTmp = $_FILES['files']['tmp_name']['knownIPFile'];
      if (empty($knownIPFile)) {
        form_set_error('turffile', 'A file is required.');
        return;
      }
      $fh = fopen($knownIPFileTmp, "r");
      if (empty($fh)) {
        form_set_error('$knownIPFileTmp', 'Failed to open File.');
        return FALSE;
      }
      
      $ipLogObj = new IpLog();
      
      $ipRangesObj = new IpRanges();

      //iplog_debug_msg('whitelist',$whitelistRecord);
      $subnets = array();
      do {
        $cidrRecord = fgetcsv($fh);
        //iplog_debug_msg('cidrrecord',$cidrRecord);
        if(empty($cidrRecord)) {break;}
        $cidr = iplog_sanitize_string($cidrRecord[0]);
        $name = iplog_sanitize_string($cidrRecord[1]);
        $type = NULL;
        if(isset($cidrRecord[2])) {
          $type = iplog_sanitize_string($cidrRecord[2]);
        }
        $subnets[$cidr]['name'] = $name;
        $subnets[$cidr]['type'] = $type;
      } while (TRUE);
      //iplog_debug_msg('subnets',$subnets);
      $ipLogObj->resetIpAddrs();
      $ipRangesObj->resetIpRanges('whitelist');
      
      foreach ($subnets as $cidr => $record) {
        $type = $record['type'];
        $ipLogObj->setIpAddr($cidr,$record['name'],$type);
        if($type == 'whitelist') {
          $highLow = $ipLogObj->cidr_conv($cidr);
          $range = $highLow[0].'-'.$highLow[1];
          $ipRangesObj->setIpRange($range,$type,NULL,$record['name']);
        }
      }
      $form_state['iplog']['page'] = 'function';
      break;
      
    case 'refreshBlockedIP':
      $blockedIPFile = $_FILES['files']['name']['blockedIPFile'];
      $blockedIPFileTmp = $_FILES['files']['tmp_name']['blockedIPFile'];
      if (empty($blockedIPFile)) {
        form_set_error('turffile', 'A file is required.');
        return;
      }
      $fh = fopen($blockedIPFileTmp, "r");
      if (empty($fh)) {
        form_set_error('$blockedIPFileTmp', 'Failed to open File.');
        return FALSE;
      }
      
      
      $ipRangesObj = new IpRanges();
      $ipRangesObj->resetIpRanges('blacklist');
      
      
      
      $newBlocked = array();
      $newIndex = 0;
      do {
        $rangeRecord = fgetcsv($fh);
        //iplog_debug_msg('rangerecord',$rangeRecord);
        if(empty($rangeRecord)) {break;}
        $type = iplog_sanitize_string($rangeRecord[1]);
        $ip= iplog_sanitize_string($rangeRecord[2]);
        $source = NULL;
        if(!empty($rangeRecord[3])) {
          $source = iplog_sanitize_string($rangeRecord[3]);
        }
        $netName = NULL;
        if(!empty($rangeRecord[4])) {
          $netName = iplog_sanitize_string($rangeRecord[4]);
        }
        if($type=='blacklist') {
          $range = explode('-',$ip);
          $cidrRange = explode('/',$ip);
          if(isset($cidrRange[1])) {
            list($low, $high) = $ipLogObj->cidr_conv($ip);
          } elseif (isset($range[1])) {
            list($low, $high) = $range;
          } else {
            //iplog_debug_msg('huh?','');
            continue;
          }
        } else {
          continue;
        }
        
        // Check if we have a duplicate range in the input file.
        $dup = FALSE;
        foreach ($newBlocked as $ipDef) {
          if($ipDef['low'] == $low AND $ipDef['high'] == $high) {
            $dup = TRUE;
          }
        }
        
        If(!$dup) {
          $newBlocked[$newIndex]['low'] = $low;
          $newBlocked[$newIndex]['high'] = $high;
          $newBlocked[$newIndex]['source'] = $source;
          $newBlocked[$newIndex]['netname'] = $netName;
          $newIndex++;
        }
      } while (TRUE);
      
      
      
      
      
      //iplog_debug_msg('newblocked',$newBlocked);
      foreach ($newBlocked as $range) {
        $ipRange = $range['low'].'-'.$range['high'];
        $ipRangesObj->setIpRange($ipRange,'blacklist',$range['source'],$range['netname']);
      }
      $form_state['iplog']['page'] = 'function';
      break;
  }
}

function iplog_subnets() {
  $form = drupal_get_form('iplog_subnets_form');
  return $form;
}
