<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/
return array (
  0 => array (
    'module' => 'ca.civicrm.moneris',
    'name' => 'Moneris',
    'entity' => 'PaymentProcessorType',
    'params' => array(
      'version' => 3,
      'name' => 'Moneris',
      'title' => 'Moneris',
      'description' => 'Moneris',
      'class_name' => 'Payment_Moneris',
      'payment_type' => 1,
      'billing_mode' => 'form',
    ),
  ),
);
