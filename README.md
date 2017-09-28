ca.civicrm.moneris
==================

Replacement to the old core CiviCRM Moneris plugin.

Notes:

1. You don't actually need to get a Moneris merchant account to test it out, but obviously for production you do. The Moneris test environment is located at [https://esqa.moneris.com/](https://esqa.moneris.com/). The credentials and instructions for logging in are on that page. When you use your contributions pages in 'Test Drive' mode, the transactions will be recorded here. Note that this test environment is shared by all developers testing the Moneris API, so don't send through any personal data.

2. The Moneris plug-in requires the default currency above to be set to 'CAD'.

3. You'll need to put in your Store ID and API token as recieved from Moneris in the CiviCRM admin, but the store2 (use store3 for recurring payments) and yesguy strings in the Test section are real valid entries for the Moneris test environment (there are 2 other allowable ones, see their documentation if you want to use a different test store). The test values won't work in production, it uses a different domain.

4. An noted above, it's important to understand that the results of the test environment are shared with other developers, i.e., only use test info, others can see what you post.

5. Another key difference from PayPal is that the test environment is setup to give various error codes depending on the dollar amount you send. See their PHP Transaction Guide if you want to test what happens when someone submits a payment with a maxxed out card, for example, or to simulate the processor being unavailable.

6. Moneris provides a web interface to do lots of administrative stuff (e.g. reversing charges, managing recurring payments), not currently handled by CiviContribute. Lots of contributor information is also sent to Moneris to help you match up data in your accounting.

7. Recurring payments, while functional, is incomplete. The Moneris payment processor in CiviCRM will trigger recurring transactions in Moneris, however that is about it. There is no way, out of the box, for CiviCRM to poll or be notified when a recurrence happens and log the respective transaction as a contribution. As such, you will need to manually monitor your Moneris admin for recurring transactions, and when they occur, you'll need to manually enter the contribution on the CiviCRM admin in order to keep the data in CiviCRM up to date. There is also no way that I am aware of to manage the Moneris end of a recurring contribution. For cancelations, credit card information updates and otherwise, you'll need to use your Moneris admin account. This of course leaves much to be desired, so take that into consideration before choosing Moneris as your payment processor for CiviCRM.
