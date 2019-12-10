<?php

/**
 * @file
 * Page callback file for the ip ranges module.
 */

/**
 * Menu callback. Displays banned IP ranges.
 */
function iplog_page() {
  $rows = array();
  $header = array(t('banned IP addresses'), t('list type'), 
     t('source'), t('net name'), t('Operations'));
  $ip_list = iplog_get_ip_list();
  foreach ($ip_list as $ip) {
    $rows[] = array(
      str_replace('-', ' - ',$ip->ip),
      $ip->type,
      $ip->source,
      $ip->netname,
      l(t('delete'), "admin/config/people/ip-ranges/delete/$ip->bid/$ip->ip"),
    );
  }

  $build['iplog_form'] = drupal_get_form('iplog_form');

  $build['iplog_banning_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  return $build;
}

/**
 * Defines the form for banning IP addresses.
 *
 * @ingroup forms
 * @see iplog_form_submit()
 */
function iplog_form($form, $form_state) {
  $form['ip_start'] = array(
    '#prefix' =>  t('<strong>Note that your own IP-Address is currently <i>'.ip_address().'</i>. Be carefull not to lock yourself out!<br />'.l('Click here to whitelist your own IP-address.', 'admin/config/people/ip-ranges/whitelist_own').'</strong>'),
    '#title' => t('IP range start / Single IP-address'),
    '#type' => 'textfield',
    '#size' => 48,
    '#required' => TRUE,
    '#maxlength' => 15,
    '#description' => t('Enter IP-address (100.100.100.100). If range end is specified, it will be used as start of the range, otherwise as a single IP-address.'),
  );
  $form['ip_end'] = array(
    '#title' => t('IP range end (optional)'),
    '#type' => 'textfield',
    '#size' => 48,
    '#required' => FALSE,
    '#maxlength' => 15,
    '#description' => t('If entered, the banned ip will be treated as a range.'),
  );
  $form['type'] = array(
    '#title' => t('List type'),
    '#type' => 'select',
    '#multiple' => FALSE,
    '#options' => array('blacklist' => 'blacklist', 'whitelist' => 'whitelist'),
    '#default_value' => 'blacklist',
    '#required' => TRUE,
    '#description' => t('Choose list type.'),
  );
  $form['source'] = array(
    '#title' => t('The source of IP assignment.)'),
    '#type' => 'textfield',
    '#size' => 32,
    '#required' => FALSE,
    '#maxlength' => 32,
    '#description' => t('One of the regional IP managers, eg RIPE (optional).'),
  );
  $form['netname'] = array(
    '#title' => t('Name of the org using the IP (optional).'),
    '#type' => 'textfield',
    '#size' => 48,
    '#required' => FALSE,
    '#maxlength' => 128,
    '#description' => t('Identifies the org being blocked.'),
  );
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Add'),
  );
  $form['#validate'][] = 'iplog_form_validate';
  $form['#submit'][] = 'iplog_form_submit';
  return $form;
}

/**
 * Form validation handler for iplog_form().
 */
function iplog_form_validate($form, &$form_state) {
  $ip_start = trim($form_state['values']['ip_start']);
  $ip_end = isset($form_state['values']['ip_end']) ? trim($form_state['values']['ip_end']) : FALSE;
  if (filter_var($ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) == FALSE) {
    form_set_error('ip_start', t("IP(/range start) is not a valid IP address."));
  }
  elseif ($ip_end && filter_var($ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) == FALSE) {
    form_set_error('ip_end', t("IP range end is not a valid IP address."));
  }
  elseif ($ip_start == ip_address() || ($ip_end && iplog_check_range($ip_start . '-' . $ip_end, ip_address()))) {
  // This fires if users own IP is in the selected range. Currently we wont do anything in that case.
 }
}

/**
 * Form submission handler for iplog_form().
 */
function iplog_form_submit($form, &$form_state) {
  $ip_start = trim($form_state['values']['ip_start']);
  $ip_start = preg_replace('/\s+/', '', $ip_start);
  $ip = $ip_start;

  if ($form_state['values']['ip_end']) {
    $ip_end = trim($form_state['values']['ip_end']);
    $ip_end = preg_replace('/\s+/', '', $ip_end);
    $ip.='-'.$ip_end;
  }

  $type = $form_state['values']['type'];
  $ip_source = $form_state['values']['source'];
  $ip_netname = $form_state['values']['netname'];
  db_insert('ip_ranges')
    ->fields(array(
      'ip' => $ip, 
      'type' => $type,
      'source' => $ip_source,
      'netname' => $ip_netname,
      ))
    ->execute();

  $list = ($type == 'blacklist') ? 'blacklisted' : 'whitelisted';
  drupal_set_message(t('The IP address %ip has been %list.', array('%ip' => $ip, '%list' => $list)));
  $form_state['redirect'] = 'admin/config/people/ip-ranges';
  return;
}

/**
 * IP deletion confirm page.
 *
 * @see iplog_delete_submit()
 */
function iplog_delete($form, &$form_state, $bid, $ip) {
  $form['ip_range'] = array(
    '#type' => 'value',
    '#value' => $bid,
  );
  return confirm_form($form, t('Are you sure you want to delete ip/range %ip?', array('%ip' => str_replace('-',' - ',$ip))), 'admin/config/people/ip-ranges', t('This action cannot be undone.'), t('Delete'), t('Cancel'));
}

/**
 * Processes iplog_delete form submissions.
 */
function iplog_delete_submit($form, &$form_state) {
  $ip = $form_state['build_info']['args']['1'];
  $bid = $form_state['values']['ip_range'];
  db_delete('iplog')
    ->condition('bid', $bid)
    ->execute();
  watchdog('user', 'Deleted %ip from banned ip-ranges', array('%ip' => $ip));
  drupal_set_message(t('The IP address/range %ip was deleted.', array('%ip' => str_replace('-',' - ',$ip))));
  $form_state['redirect'] = 'admin/config/people/ip-ranges';
}
