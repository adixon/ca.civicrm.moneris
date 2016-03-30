<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'Cron:Job.Monerisrecurringcontributions',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Moneris Recurring Contributions Daily Auto-Generate',
      'description' => 'Auto generate contribution records for Moneris recurring contributions',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'monerisrecurringcontributions',
      'parameters' => '',
      'is_active' => 0,
    ),
  ),
);
