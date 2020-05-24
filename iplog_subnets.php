<?php
/*
 * Name: iplog_subnets.php   V2.0  4/15/20
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

//define('DD_CIDR_FILE','knownipaddrs');
define('DD_KNOWN_FILE','knownipaddrs');
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
      
      $blacklist = iplog_get_ip_list('blacklist');
      foreach ($ipLog as $ipKey => $ipRecord) {
        $handler = '';
        $banned = FALSE;
        if($ipRecord['orgId']=='unknown user' OR $ipRecord['orgId']=='unknown') {
          
          
          foreach ($blacklist as $ip) {
            if (iplog_check_ip($ip->ip, $ipRecord['ipAddr'])) {
              $banned = TRUE;
              break;
            }
          }

          if(!$banned AND module_exists('defense') ) {
            $ipHandler = defense_lookup($ipRecord['ipAddr']);
            //iplog_debug_msg('iphandler',$ipHandler);
            $ip_start = $ipHandler['addresses'][0]['startAddress'];
            $ip_end = $ipHandler['addresses'][0]['endAddress'];
            $cidr = $ipLogObj->ip2cidr($ip_start, $ip_end);
            $handler = ', '.$ipHandler['handler'].', '.$ip_start.'-'.$ip_end.', '.$cidr;
          }
        }
        if(!$banned) {
          $output .= $ipKey.', '.$ipRecord['ipAddr'].', '.$ipRecord['hits'].', '.$ipRecord['orgId'].$handler."<br>";
        }
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
          $ipRangesObj = new IpRanges();
          $ipLogObj = new IpLog();
          $temp_dir = 'public://temp';
          $cdate = date('Y-m-d-H-i-s',time());
          $uri = $temp_dir.'/'.DD_KNOWN_FILE.'-'.$cdate.'.csv';
          $fileObj = file_save_data('', $uri, FILE_EXISTS_REPLACE);
          $fileObj->status = 0;
          file_save($fileObj);
          $fh = fopen($uri,"w");
          
          $hdr = array('BaseIP','IPRange','Owner','Type',);
          fputcsv($fh, $hdr);
          
          $knownIpRanges = $ipLogObj->getIpAddrs();
          $cidrRecords = array();
          foreach ($knownIpRanges as $bid => $ipRange) {
            //$firstDecCidr = $ipRangesObj->firstDecCidr($cidr);
            //$ipRange = $ipLogObj->cidr_conv($cidr);
            $cidrRecords[$bid][0] = $bid;
            $cidrRecords[$bid][1] = $ipRange['ip'];
            $cidrRecords[$bid][2] = $ipRange['orgId'];
            $cidrRecords[$bid][3] = $ipRange['type'];
            //$cidrRecords[$bid][4] = $cidr;
          }
          
          ksort($cidrRecords);
          
          foreach ($cidrRecords as $cidrRecord) {
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
          //$removeables = array('unknown','NL','password guess','unknown user','user');
          $removeables = $ipLogObj->removeables;
          //iplog_debug_msg('iplog',$ipLog);
          foreach ($ipLog as $ipKey => $ipRecord) {
            if(in_array($ipRecord['orgId'], $removeables)) {
            //if($ipRecord['orgId'] == 'unknown' OR $ipRecord['orgId'] == 'NL' 
            //  OR $ipRecord['orgId'] == 'password guess' OR $ipRecord['orgId'] == 'unknown user' 
            //   OR $ipRecord['orgId'] == 'user') {
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
          
          $hdr = array('BaseIP','Type','IPRange','Source','Owner');
          fputcsv($fh, $hdr);
          
          $ipRanges = $ipRangesObj->getIpRanges();
          //iplog_debug_msg('subnets',$subnets);
          
          
          $blockedRecords = array();
          foreach ($ipRanges as $bid => $blocked) {
            if($blocked['type'] == 'blacklist') {
              $firstDecIp = $ipRangesObj->firstDecIp($blocked['ipRange']);
              $blockedRecords[$firstDecIp]['bid'] = $bid;
              $blockedRecords[$firstDecIp]['type'] = $blocked['type'];
              $blockedRecords[$firstDecIp]['ipRange'] = $blocked['ipRange'];
              $blockedRecords[$firstDecIp]['source'] = $blocked['source'];
              $blockedRecords[$firstDecIp]['netName'] = $blocked['netName'];
            }
          }
          
          ksort($blockedRecords);
          
          foreach ($blockedRecords as $blocked) {
            fputcsv($fh, $blocked);
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
      $hdr = fgetcsv($fh);
      
      $ipLogObj = new IpLog();
      
      $ipRangesObj = new IpRanges();

      //iplog_debug_msg('whitelist',$whitelistRecord);
      $subnets = array();
      do {
        $knownIpRecord = fgetcsv($fh);
        //iplog_debug_msg('cidrrecord',$knownIpRecord);
        if(empty($knownIpRecord)) {break;}
        //$bid= iplog_sanitize_string($knownIpRecord[0]);
        $ipRangeRaw = iplog_sanitize_string($knownIpRecord[1]);
        $ipRange = str_replace(' ', '', $ipRangeRaw);
        $bid = $ipRangesObj->firstDecIp($ipRange);
        $name = iplog_sanitize_string($knownIpRecord[2]);
        $type = NULL;
        if(isset($knownIpRecord[3])) {
          $type = iplog_sanitize_string($knownIpRecord[3]);
        }
        $subnets[$bid]['ip'] = $ipRange;
        $subnets[$bid]['name'] = $name;
        $subnets[$bid]['type'] = $type;
      } while (TRUE);
      //iplog_debug_msg('subnets',$subnets);
      $ipLogObj->resetIpAddrs();
      $ipRangesObj->resetIpRanges('whitelist');
      
      foreach ($subnets as $bid => $record) {
        $type = $record['type'];
        $ipLogObj->setIpAddr($bid,$record['ip'],$record['name'],$type);
        if($type == 'whitelist') {
          $ipRangesObj->setIpRange($record['ip'],$type,NULL,$record['name']);
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
      $hdr = fgetcsv($fh);
      
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
          $justIp = str_replace(' ', '', $ip);
          $range = explode('-',$justIp);
          $cidrRange = explode('/',$justIp);
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
