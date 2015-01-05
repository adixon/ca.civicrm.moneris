<?php

/**
 * Job.MonerisRecurringContributions API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_job_monerisrecurringcontributions_spec(&$spec) {
  $spec['recur_id'] = array(
    'name' => 'recur_id',
    'title' => 'Recurring payment id',
    'type' => 1,
  );
  $spec['scheduled'] = array(
    'name' => 'scheduled',
    'title' => 'Only scheduled contributions.',
    'type' => 1,
  );
}

/**
 * Job.MonerisRecurringContributions API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_monerisrecurringcontributions($params) {
  // TODO: what kind of extra security do we want or need here to prevent it from being triggered inappropriately? Or does it matter?

  // the next scheduled contribution date field name is civicrm version dependent
  define('MONERIS_CIVICRM_NSCD_FID',_moneris_civicrm_nscd_fid());
  // $config = &CRM_Core_Config::singleton();
  // $debug  = false;
  // do my calculations based on yyyymmddhhmmss representation of the time
  // not sure about time-zone issues, may this next line tries to fix that?
  $dtCurrentDay    = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
  $dtCurrentDayStart = $dtCurrentDay."000000";
  $dtCurrentDayEnd   = $dtCurrentDay."235959";
  $expiry_limit = date('ym');
  // restrict this method of recurring contribution processing to only this payment processor
  $args = array(
    1 => array('Payment_Moneris', 'String'),
    2 => array('Payment_Dummy', 'String'),
  );
  // Before triggering payments, we need to do some housekeeping of the civicrm_contribution_recur records.
  // First update the end_date and then the complete/in-progress values.
  // We do this both to fix any failed settings previously, and also
  // to deal with the possibility that the settings for the number of payments (installments) for an existing record has changed.

  // First check for recur end date values on non-open-ended recurring contribution records that are either complete or in-progress
  $select = 'SELECT cr.id, count(c.id) AS installments_done, cr.installments, cr.end_date, NOW() as test_now
      FROM civicrm_contribution_recur cr 
      INNER JOIN civicrm_contribution c ON cr.id = c.contribution_recur_id 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id 
      WHERE 
        (pp.class_name = %1 OR pp.class_name = %2) 
        AND (cr.installments > 0) 
        AND (cr.contribution_status_id IN (1,5)) 
      GROUP BY c.contribution_recur_id';
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  while ($dao->fetch()) {
    // check for end dates that should be unset because I haven't finished
    if ($dao->installments_done < $dao->installments) { // at least one more installment todo
      if (($dao->end_date > 0) && ($dao->end_date <= $dao->test_now)) { // unset the end_date
        $update = 'UPDATE civicrm_contribution_recur SET end_date = NULL WHERE id = %1';
        CRM_Core_DAO::executeQuery($update,array(1 => array($dao->id,'Int')));
      }
    }
    // otherwise, check if my end date should be set to the past because I have finished
    elseif ($dao->installments_done >= $dao->installments) { // I'm done with installments
      if (empty($dao->end_date) || ($dao->end_date >= $dao->test_now)) { 
        // this interval complete, set the end_date to an hour ago
        $update = 'UPDATE civicrm_contribution_recur SET end_date = DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE id = %1';
        CRM_Core_DAO::executeQuery($update,array(1 => array($dao->id,'Int')));
      }
    }
  }
  // Second, make sure any open-ended recurring contributions have no end date set
  $update = 'UPDATE civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      SET
        cr.end_date = NULL 
      WHERE
        cr.contribution_status_id IN (1,5) 
        AND NOT(cr.installments > 0)
        AND (pp.class_name = %1 OR pp.class_name = %2)
        AND NOT(ISNULL(cr.end_date))';
  $dao = CRM_Core_DAO::executeQuery($update,$args);
  
  // Third, we update the status_id of the all in-progress or completed recurring contribution records
  // Unexpire uncompleted cycles
  $update = 'UPDATE civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      SET
        cr.contribution_status_id = 5 
      WHERE
        cr.contribution_status_id = 1 
        AND (pp.class_name = %1 OR pp.class_name = %2)
        AND (cr.end_date IS NULL OR cr.end_date > NOW())';
  $dao = CRM_Core_DAO::executeQuery($update,$args);
  // Expire completed cycles
  $update = 'UPDATE civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      SET
        cr.contribution_status_id = 1 
      WHERE
        cr.contribution_status_id = 5 
        AND (pp.class_name = %1 OR pp.class_name = %2)
        AND (NOT(cr.end_date IS NULL) AND cr.end_date <= NOW())';
  $dao = CRM_Core_DAO::executeQuery($update,$args);

  // Now we're ready to create the payment records
  // Select the ongoing recurring payments for Moneris where the next scheduled contribution date (NSCD) is before the end of of the current day
  $select = 'SELECT cr.*, pp.class_name as pp_class_name, pp.url_site as url_site, pp.is_test 
      FROM civicrm_contribution_recur cr 
      INNER JOIN civicrm_payment_processor pp ON cr.payment_processor_id = pp.id
      WHERE 
        cr.contribution_status_id = 5
        AND (pp.class_name = %1 OR pp.class_name = %2)';
  //      AND pp.is_test = 0
  if (!empty($params['recur_id'])) { // in case the job was called to execute a specific recurring contribution id -- not yet implemented!
    $select .= ' AND cr.id = %3';
    $args[3] = array($params['recur_id'], 'Int');
  }
  else { // if (!empty($params['scheduled'])) { 
    //normally, process all recurring contributions due today or earlier
    $select .= ' AND cr.'.MONERIS_CIVICRM_NSCD_FID.' <= %3';
    $args[3] = array($dtCurrentDayEnd, 'String');
    // ' AND cr.next_sched_contribution >= %2 
    // $args[2] = array($dtCurrentDayStart, 'String');
  }
  $dao = CRM_Core_DAO::executeQuery($select,$args);
  $counter = 0;
  $error_count  = 0;
  $output  = array();
  while ($dao->fetch()) {
    // create the contribution record with status = 1 (= complete)
    // Get the first contribution in this series that matches the same total to help with line items and some other values
    $initial_contribution = array();
    $line_items = array();
    $payment_processor = substr($dao->pp_class_name,8);
    $get = array('version'  => 3, 'contribution_recur_id' => $dao->id, 'total_amount' => $dao->amount, 'options'  => array('sort'  => ' id' , 'limit'  => 1));
    $result = civicrm_api('contribution', 'get', $get);
    if (!empty($result['values'])) {
      $contribution_ids = array_keys($result['values']);
      $get = array('version'  => 3, 'entity_table' => 'civicrm_contribution', 'entity_id' => $contribution_ids[0]);
      $result = civicrm_api('LineItem', 'get', $get);
      if (!empty($result['values'])) {
        foreach($result['values'] as $initial_line_item) {
          $line_item = array();
          foreach(array('price_field_id','qty','line_total','unit_price','label','price_field_value_id','financial_type_id') as $key) {
            $line_item[$key] = $initial_line_item[$key];
          }
          $line_items[] = $line_item;
        }
      }
    }
    $contact_id = $dao->contact_id;
    $total_amount = $dao->amount;
    $hash = md5(uniqid(rand(), true));
    $contribution_recur_id    = $dao->id;
    $source = "$payment_processor Recurring Contribution (id=$contribution_recur_id)"; 
    $receive_date = date("YmdHis"); // i.e. now
    // check if we already have an error
    $errors = array();
    $contribution = array(
      'version'        => 3,
      'contact_id'       => $contact_id,
      'receive_date'       => $receive_date,
      'total_amount'       => $total_amount,
      'payment_instrument_id'  => $dao->payment_instrument_id,
      'contribution_recur_id'  => $contribution_recur_id,
      'trxn_id'        => $hash, /* placeholder: just something unique that can also be seen as the same as invoice_id */
      'invoice_id'       => $hash,
      'source'         => $source,
      'contribution_status_id' => 1, /* requires an administrator to verify against actual payments, but let's expect the best */
      'currency'  => $dao->currency,
      'payment_processor'   => $dao->payment_processor_id,
      'is_test'        => $dao->is_test, /* propagate the is_test value from the parent contribution */
    );
    $get_from_original = array('contribution_campaign_id','amount_level');
    foreach($get_from_original as $field) {
      if (isset($original_contribution[$field])) {
        $contribution[$field] = $original_contribution[$field];
      }
    }
    if (isset($dao->contribution_type_id)) {  // 4.2
       $contribution['contribution_type_id'] = $dao->contribution_type_id;
    }
    else { // 4.3+
       $contribution['financial_type_id'] = $dao->financial_type_id;
    }
    if (count($line_items) > 0) {
      $contribution['skipLineItem'] = 1;
      $contribution[ 'api.line_item.create'] = $line_items;
    }
    if (count($errors)) {
      ++$error_count;
      ++$counter;
    }
    /* create the contribution record */
    $result = civicrm_api('contribution', 'create', $contribution);
    if ($result['is_error']) {
        $errors[] = $result['error_message'];
    }

    //$mem_end_date = $member_dao->end_date;
    // $temp_date = strtotime($dao->next_sched_contribution);
    /* calculate the next collection date. You could use the previous line instead if you wanted to catch up with missing contributions instead of just moving forward from the present */
    $temp_date = time();
    $next_collectionDate = strtotime ("+$dao->frequency_interval $dao->frequency_unit", $temp_date);
    $next_collectionDate = date('YmdHis', $next_collectionDate);

    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur 
         SET ".MONERIS_CIVICRM_NSCD_FID." = %1 
       WHERE id = %2
    ", array(
         1 => array($next_collectionDate, 'String'),
         2 => array($dao->id, 'Int')
       )
    );

    $result = civicrm_api('activity', 'create',
      array(
        'version'       => 3,
        'activity_type_id'  => 6,
        'source_contact_id'   => $contact_id,
        'assignee_contact_id' => $contact_id,
        'subject'       => "Added $payment_processor Payment Recurring Contribution for " . $total_amount,
        'status_id'       => 2,
        'activity_date_time'  => date("YmdHis"),
      )
    );
    if ($result['is_error']) {
      $output[] = ts(
        'An error occurred while creating activity record for contact id %1: %2',
        array(
          1 => $contact_id,
          2 => $result['error_message']
        )
      );
      ++$error_count;
    } else {
      $output[] = ts('Created activity record for contact id %1', array(1 => $contact_id));

    }
    ++$counter;
  }

  // If errors ..
  if ($error_count) {
    return civicrm_api3_create_error(
      ts("Completed, but with %1 errors. %2 records processed.",
        array(
          1 => $error_count,
          2 => $counter
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // If no errors and records processed ..
  if ($counter) {
    return civicrm_api3_create_success(
      ts(
        '%1 contribution record(s) were processed.',
        array(
          1 => $counter
        )
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // No records processed
  return civicrm_api3_create_success(ts('No contribution records were processed.'));

}
