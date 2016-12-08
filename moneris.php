<?php

require_once 'moneris.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function moneris_civicrm_config(&$config) {
  _moneris_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function moneris_civicrm_xmlMenu(&$files) {
  _moneris_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function moneris_civicrm_install() {
  return _moneris_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function moneris_civicrm_uninstall() {
  return _moneris_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function moneris_civicrm_enable() {
  return _moneris_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function moneris_civicrm_disable() {
  return _moneris_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function moneris_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _moneris_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function moneris_civicrm_managed(&$entities) {
  $entities[] = array(
    'module' => 'ca.civicrm.moneris',
    'name' => 'Moneris',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Moneris',
      'title' => 'Moneris Credit Card',
      'description' => 'Moneris credit card payment processor.',
      'class_name' => 'Payment_Moneris',
      'billing_mode' => 'form',
      'user_name_label' => 'User Name',
      'password_label' => 'API Token',
      'url_site_default' => 'https://www3.moneris.com/',
      'url_recur_default' => 'https://www3.moneris.com/',
      'url_site_test_default' => 'https://esqa.moneris.com/',
      'url_recur_test_default' => 'https://esqa.moneris.com/',
      'is_recur' => 1,
      'payment_type' => 1,
    ),
  );
  return _moneris_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function moneris_civicrm_caseTypes(&$caseTypes) {
  _moneris_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function moneris_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _moneris_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function _moneris_civicrm_nscd_fid() {
  $codeVer = CRM_Utils_System::version();
  return (version_compare($codeVer, '4.4') < 0) ? 'next_sched_contribution' : 'next_sched_contribution_date';
}

/*
 * The contribution itself doesn't tell you which payment processor it came from
 * So we have to dig back via the contribution_recur_id that it is associated with.
 */
function _moneris_civicrm_get_payment_processor_id($contribution_recur_id) {
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $contribution_recur_id,
  );
  $result = civicrm_api('ContributionRecur', 'getsingle', $params);
  if (empty($result['payment_processor_id'])) {
    return FALSE;
    // TODO: log error
  }
  return $result['payment_processor_id'];
}

function _moneris_civicrm_is_moneris($payment_processor_id) {
  $params = array(
    'version' => 3,
    'sequential' => 1,
    'id' => $payment_processor_id,
  );
  $result = civicrm_api('PaymentProcessor', 'getsingle', $params);
  if (empty($result['class_name'])) {
    return FALSE;
    // TODO: log error
  }
  return ('Payment_Moneris' == $result['class_name']) ? 'Payment_Moneris' : '';
}

/*
 * hook_civicrm_pre
 *
 * Handle special cases of creating contributions records (regular and recurring) when using Moneris
 *
 * 1. CiviCRM assumes all recurring contributions need to be confirmed using the IPN mechanism. 
 *    This is not true for Moneris recurring contributions, because I'm testing with a capture first.
 *    So when creating a contribution that is part of a recurring series, test for status = 2, and set to status = 1 instead.
 *    Do this for the initial and recurring contribution record.
 *    The (subsequent) recurring contributions' status id is set explicitly in the job that creates it, and doesn't need this modification.
 *
 * TODO: update this code with constants for the various id values of 1 and 2.
 * TODO: CiviCRM should have nicer ways to handle this.
 */

function moneris_civicrm_pre($op, $objectName, $objectId, &$params) {
  // since this function gets called a lot, quickly determine if I care about the record being created
  if (('create' == $op) && ('Contribution' == $objectName || 'ContributionRecur' == $objectName) && !empty($params['contribution_status_id'])) {
    // watchdog('moneris_civicrm','hook_civicrm_pre for Contribution <pre>@params</pre>',array('@params' => print_r($params));
    // figure out the payment processor id, not nice
    $payment_processor_id = ('ContributionRecur' == $objectName) ? $params['payment_processor_id'] :
                              (!empty($params['payment_processor']) ? $params['payment_processor'] :
                                (!empty($params['contribution_recur_id']) ? _moneris_civicrm_get_payment_processor_id($params['contribution_recur_id']) :
                                 0)
                              );
    if (_moneris_civicrm_is_moneris($payment_processor_id)) {
      switch ($objectName) {
        case 'Contribution': // cc contribution, test if it's been set to status 2 on a recurring contribution
          if ((2 == $params['contribution_status_id']) && !empty($params['contribution_recur_id'])) {
            $params['contribution_status_id'] = 1;
          }
          break;
        case 'ContributionRecur':
          // calculate the date of the next schedule contribution
          $params['contribution_status_id'] = 5;
          // $params['trxn_id'] = NULL;
          //TODO: next 20th of the month
          // $next = strtotime('+'.$params['frequency_interval'].' '.$params['frequency_unit']);
          // the next scheduled contribution date field name is civicrm version dependent
          // $field_name = _moneris_civicrm_nscd_fid();
          // $params[$field_name] = date('YmdHis',$next);
          break;
      }
    }
  }
}

