<?php
/*
 * Name: iplog_404_tracker.php   V2.0  11/15/19
 */

require_once "iplog_class_log.php";

use Drupal\iplog\IpLog;

function iplog_404_tracker() {
  $ipLogObj = new IpLog();
  $ipLogObj->ipLogError();
  $output = "Thanks for visiting our site.  Your IP has been logged.";
  return $output;
}
