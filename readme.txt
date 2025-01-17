=== Xeroom ===
Xeroom Version: 3.1.4
Contributors: Peter Lloyd
Tags:  Xero, WooCommerce xero link, Woo, WooCommerce xero integration, WooCommerce, invoice, Xeroom, stock management, accounting integration, account synch, orders synch
Requires WordPress: 6.4
Tested WordPress: 6.7.1
Requires PHP:8.1.10
Tested to PHP:8.2.28
Requires WooCommerce: 8.6.0
Tested WooCommerce: 9.5.2
Release date: 15th Janaury, 2025
License: Use is granted under Xeroom End User Licence Agreement.  By installing and using it you agree to the terms and conditions.
License URI: https://www.xeroom.com/terms-conditions/xeroom-eula-v12-may-2020/
More details and support:  www.xeroom.com

== Version 3.1.4 15th January, 2025 ==
Bug Fixes:
1. Invoice Due Date - Getday on null error fixed.
2. 100% Discount Coupons & Zero value orders not posting.
3. Xeroom_root_path - error fixed.
4. Invoice prefix field setting fixed.
5. Success confirmation message fixed.

Enhancements:
1. Inventory log synch file improvements.
2. Payment methods - List only enabled ones not all
3. Coupons - WooCommerce Subscription Recurring % coupons added in addition to Simple coupons.

== Version 3.1.3 1st November, 2024 ==
Bug Fixes:
1. Contact mapping - Was not matching for pulling contact details in Xero

Enhancements:
1. Contact mapping - Add mapping option to map on Xero Contact Account number.
2. Shipping Method - The WooCommerce method used in the order now shows on the invoice or can be a specific default.
3. Xero Invoice Due Date - The Xero default settings can now be used including specific ones by contact.

== Version 3.1.2 12th September, 2024 ==
Bug Fixes:
1. Global Product Synch - WC to Xero not synching for new products.
2. Global Product Synch - Xero to WC not working.
3. eBay and Amazon - Feature setting not saved.
4. Daily limits to no of order sends - Counter sometimes errors and displays a 0 instead of user error message.

Enhancements:
1. Extra Sales Accounts Mappings - Drop down lists of eligible acs added.
2. Log files no limited to 50.
  
== Version 3.1.1 6th August, 2024 ==
Enhancements:
1. Global Inventory Synch - The synch was not cycling through all the batches. This has been fixed and improved with batches processed one/minute and progress shown and log files generated for each batch.
2. Global Product Sync - The same functionality as for inventory synch has been added along with improvements in the log files.
3. Improved log file naming by synch type and batch no. Product synch log file shows the new description and prices that have been synched.
4. Circuit breaker - This limits the total no of invoices and credit notes that can be created in a day with default 50 of each.
5. Product page - Can now filter by products synched with Xero.

== Version 3.1.0 2nd July, 2024 ==
Bug fixes:
1. Bulk resend status bar not showing on some sites.
2. Stripe fees are now passed gross to match the payment and avoid tax being added again which was left unpaid in the Stripe Fee bill.

Enhancements:
1. Set invoice due date to a specific day of the month - Useful for payments on account.


== Version 3.0.9 30th May, 2024 ==
Bug fixes:
1. Multiple 100% discount coupons handling added.
2. On WC Order admin page the Xero status was not showing and creating multiple admin.ajax errors.
3  E_ERROR caused in line 6814 on complex tax

Enhancements:
1. New My Account page format and listing of current licences, status and dates



== Version 3.0.8 30th April, 2024 ==
Bug fixes:
1. The last one of multiple refunds was not creating a Credit Note.
2. Licence ad-hoc switching to Starter for very old subscriptions.

== Version 3.0.7 15th April, 2024 ==
Bug fix:
1. New products giving "The description field is mandatory for each line item" preventing creation.

== Version 3.0.6 12th April, 2024 ==
Inventory Synch on Order Fixes:
1. New products added in WC are now created in Xero as untracked instead of tracked for ALL inventory synch settings. 
2. Certain untracked products not converted to tracked and description were overwitten when Inventory Synch on Order set to None.
3. Existing tracked products in Xero will not fail when synch set to None or WooCommerce only when set to Xero being the Master. 

== Version 3.0.5 10th April, 2024 ==
Fixed additional issues arising from WC 8.7.0 structure changes:
1. Update composer php version compatability for php7.4 support.
2. "No items to sell" issue when posting order for first time for new products when inventory synch set to none.
3. Too many redirects error on batch leading to multiple postings.
4. Get contactid error when guest checkout leading to failed postings.
5. Batch process progress bar missing - note doesn't always refresh so appears to hang - this will be fixed.


== Version 3.0.4 4th April, 2024 ==
Bug Fixes:
1. Duplicate empty orders being created under WooCommerce 8.7.0
2. Xero Validation Exemption error on processing refunds

== Version 3.0.3 29th March, 2024 ==
Bug Fixes:
1. Guzzle library updated.
2. Fatal error when saving credentials 36613 in Xero AccountingAPI.php
3. Extra check for Xero class to avoid not found error.

Enhancements:
1. Multiple refunds including partial amounts to create CN correctly.
2. Option to send/not send payment when refunding before creating CN.
2. Debug column headings show order and invoice nos for pdf invoices and other order no modifiers.
3. Cart Coupons allocated prorata to respecitve Product Accounts.
4. Multiple coupons handled and automatically create codes in Xero.
5. EAS EU compliance plugin tax rates handling added.

== Version 3.0.2 5th March, 2024 ==
1.Updated PSR HTTP library

== Version 3.0.1 14th February, 2024 ==
Enhancements:
1. Refunds and cancellations - handling of all cases and check for existing CN
2. WooCommerce REST API sunset check.

Bug Fixes:
1. Uncaught Valueerror - Inventory and Product Synch Tabsnot displaying detail in php8.0 
2. New Products – Hard post of product data to the product code in Xero failing.

== Version 3.0.0 31th January, 2024 ==
Enhancements:
1. New Versions - Refactored to provide Starter and Premium versions with upgrade option for new Starter customers.  Existing users are converted to Premium.
2. Cancelled orders - Will generate a Credit Note in Xero for Unpaid invoices.
3. WC Deposits and Partial Payments Plugin - Compatability added for this so that deposits and part payments get posted onto the invoice in Xero.
4. Paid in Xero - User agent added for webhook calls to avoid getting blocked by WP Engine. 
5. Coupon - Now posted to product accounts (allocated if multiple products) instead of coupon account.

Bug Fixes:
1. Refund error - HPOS Call to member function get_function() null.
2. Malformed UTF-8 characters, possibly incorrectly encoded.


== Version 2.7.3 18th December, 2023 ==
Bug Fix:
For non-instant payments when an invoice is voided in Xero then the status of the order in WC changes to "Processing".

== Version 2.7.2 9th December, 2023 ==
Enhancements:
1. Create Credit Notes for partial integer quantities eg 0.3 of a unit refund - this is delayed due to a bug.

Bug Fixes:
1. Xeroom debug - Data Tables Warning Ajax error.
2. Paid in Xero - Status flag colour not changing sometimes.
3. Uncaught Error: Cannot use object of type stdClass.

== Version 2.7.1 31st October, 2023 ==
Enhancements:
1. Paid in Xero - Rewritten to give more robust performance and avoid webhook diablement by Xero with failed responses.
2. Skip order sending for eBay and Amazon orders - Bypass sending for shops with feeds that go direct to Xero.
3. Invoice Reference Custom Meta field box added - To pick up any field in orders_meta and postmeta tables eg PO nos for certain plugins.

Bug Fixes:
1. Completed order moved back to Processing status.
2. Webhook error - Uncaught ArgumentCountError: Too few arguments.
3. Complex tax setting - Save looses Stripe fee setting.
4. Send invoice manually when paid by Stripe
5. Error Index.php is not a known wordpress plugin.


== Version 2.7.0 19th September, 2023 ==
Enhancements:
1. WooCommerce High Performance Order Storage (HPOS) Compliant

Bug Fixes:
1. Stripe fees gave an GetContact ID error on checkout.
2. Server specific GuzzleHttp\json_encode() error.
3. Expired Xero token not getting flushed. Shows up in php 8.0
4. Order notes not updating immediately after order sends.
5. Paid in Xero blue flag showing for all Stripe orders.
6. Critical uncaught guzzle error - Invalid argument exception - Json not catering for special characters.
7. Unsupported operand type error in WC_Order_Item_Meta 


== Version 2.6.9 7th July, 2023 ==
Enhancements:
1. Option added to post shipping address even if the same on all orders.

Bug Fixes:
1. Send Order Manually fixed.
2. Fixed Coupon double-taxation error fixed.
3. Product Global Synch - Critical uncaught type error in Xero-product-to-woo synch fixed.
4. Global Product Synch - Amended to synch for all and not just tracked products and for missing products.
 
== Version 2.6.8 17th June, 2023 ==
Enhancements:
1. Sales accounts added to drop-down list in product account selection. Now showing Sales, Revenue and Current Asset Xero accounts for selection.

Bug Fixes:
1. Paid in Xero - not working due to bug in WordPress.
2. Some general settings lost when invoice synch page saved.
3. Global Inventory Synch - Array warning message fixed.
4. Xero tenant missing parameter fatal error.
5. Order send critical uncaught error strpos - fixed.

== Version 2.6.7 30th May, 2023 ==
Enahncements:
1. Xero Posting Performance Improvements - Posting process rewritten to make posting faster and avoid Xero API limits being hit for large orders.
2. Connection to Xero improved look - Shows Xero organisation connected.
3. Enable coupons to work with WC Subscriptions

Fixes:
1. Credit notes getting set to zero value due to issue with associated negative taxation.

== Version 2.6.6b 12th February, 2023 ==
Enhancements:
1. Handle use of Oauth2 Signature class method by other themes.
Fix:
1. E_WARNING: Undefined array key "debug_mode" in xeroom_sync.php
 
== Version 2.6.6a 12th February, 2023 ==
Bug fix for Paid in Xero flag getting set for all orders. 

== Version 2.6.6 12th February, 2023 ==
Enhancements:
1. Stripe Fees Posting - Stripe fees can be posted to a dedicated Xero account with payment for them posting to the Stripe Bank account.  This makes the clearing operation very easy as well as automatic reconciliation rules if a Stripe bank feed is used.
2. Global Inventory Synch - Operation improved and a check added for the log directory being present without which it will not run. Other bugs fixed and fully retested.
3. Global Product Synch - Improvements in the operation so that any products created in Xero are a default of no cost and are untracked. Fix for error if product didn't exist WC to Xero synch. Other bugs fixes and fully retested.
4. "Other Income" type accounts added to Shipping Revenues drop-down list.  
5. WooCommerce Google Ads and Listings plugin - Compatability added to prevent a critical error.

== Version 2.6.5 23th January, 2023 ==
Enhancements:
1. Bulk Send of Unsent Orders - Automatic retry every 5 mins for 5 tries for failed orders
2. No Send of Zero Value Orders - Added to Bulk Send (in addition to automatic from front end or in the order)
3. WC Points & Rewards Plugin Coupon fails due to code >30 characters - Compatability added
4. Autocompletion of orders added with options for virtual and downloadable only
5. Filter orders in the order screen display by Xero status
6. WC POS Plugin E_Error in indexInit - Compatability added
7. Check for WooCommerce plugin being present and active when using Xeroom

Bug Fixes:
1. Tax settings dropdown lists were empty
2. PHP Fatal Error could not open file - phpspreadsheet and Excel libraries updated
3. Order completion on payment and virtual products not working under php8.1 - Add workaround for WP Cron not working properly
4. Cron Event List save error
5. Tax settings drop-down lists not populating.

== Version 2.6.4 4th January, 2023 ==
Internal release - rolled in to 2.6.5

== Version 2.6.3 12th December, 2022 ==
Enhancements:
1. Xero codes can now be set for coupons to avoid loads of single use codes apearing in Xero.
2. Option to not send orders to Xero that have zero value - eg if paid by rewards or coupons.
3. Bulk Send of Unsent Orders - Option to not Send orders during checkout but on a 5 min schedule to avoid any delays eg for large orders. 
4. Global Product Synch - Ability to update Price only or Price and Description
5. Global Product Synch - Ability to select products to synch product data for. 

Bug Fixes:
1. Fix for tax on flat rate shipping when set to none.
2. Fix for shipping value of 0.
3. Fix for Undefined array key "Code"error.
4. Fix for IndexInit Uncaught Error.
5. Fix for invoice sent status being set when sending fails
6. Fix for Illegal string offset 'UnitPrice'
7. Fix for Global Inventory Synch cron job not showing completed and correct log file opening.


== Version 2.6.2 20th November, 2022 ==
Enhancements:
1. Global Inventory Synch - If Xero creates an error whilst processing then the whole job is not done leaving some or all of the inventory not updated.  
Often Xero does not return any info on the error or gives very generic error that doesn't enable the issue to be troubleshooted eg "The total cost pool of tracked inventory items cannot be reduced to less than zero."
We have created a debugging tool that will reduce the batch size of SKU's being synched to 10.  In tandem with this we have added an SKU blacklist that will not be synched thus enabling the error causing product to be isolated.     
2. Global Product Synch - Synch from WC to Xero now added and option to update prices only or price and description.  NB This is still in beta phase so please test before using.   
3. Order no added to debug screen - Useful where custom order nos are being used instead of the default Post_ID for order no.

Bug Fixes:
1. Tax on shipping led to the differences posted as "Rounding" - This was due to change in WC 7.0.
2. Orders with free shipping were not being posted.

== Version 2.6.1 17th October, 2022 ==
Enhancements:
1. PHP 8 compatibility – Now works with php version 8.17 as well as php 7.4.
2. Current liability type accounts – Added to the drop-down lists of possible Xero accounts for setup.
3  Expense type accounts – Added to the rounding account setup selection list.
4. Invoice Number and Reference Options – These have been extended and refined.
5. Invoice Reference – This can take the order number plus the payment reference and customer name as well as a prefix or any combination of them.
6. WooCommerce Order Status Manager Plugin – We have added compatibility.
7. Account Funds Plugin - Prepayment or bulk funds can be added which appears on the selected account in Xero available to be spent. 
8. Customer Account Numbers - A field is added to the WordPress user or metakey can be used to capture and added to Xero contact details and on invoices.
9. Invoice Paid Synch from Xero - Complete orders for virtual products only.

Bug fixes:
1. Fix to show current liability accounts in product sales accounts.
2. Fix for Send on Completion trigger bug.
3. Fix for Composer Stringeable error which can only be used on php 8.
4. Fix for rounding with coupons where the coupon in Xero was not replicating exactly resulting in a “rounding error”.
5. Invoice number and reference errors fixed.

== Version 2.5.2 24th August, 2022 ==
1. Fix for showing current liability accounts in product sales accounts
2. Fix for Send on Completion.

== Version 2.5.1 20th August, 2022 ==
1. Fix Xero product description to allow >50 characters.
2. Fix for sales account types and not just revenue to show in drop down lists.
3. Fix for invoice send triggers not working.
4. Improve processing speed during checkout.

== Version 2.5.0 13th August, 2022 ==
1. Ability to turn off the transfer of order notes to invoices.
2. Product based mapping in WC of accounts for Cost of Goods Sold and Inventory Asset Accounts added. Multiple IAA now handled.
3. Installation setup procedure simplified with populated drop-down Xero account lists for each setting and taxes selections. 
4. Fix for when paying invoice in Xero to send payment to WooCommerce & option to turn of autocompletion.
5. Drop-down selection lists added for product account mappings. 
6. Cut product desc length to meet Xero 50 character limit for name.

== Version 2.4.2 12th June, 2022 ==
1. Fix for WP Engine hosting critical error on intialisation.
2. Fix to stop bills being created in Xero on inventory adjustment.

== Version 2.4.1.2 30th May, 2022 ==
1. Fix for critical Guzzle error conflicts with other plugins.

== Version 2.4.1.1 13th May, 2022 ==
1. Fix for Xero tracking attribute not displaying on invoice.
2. Fix for Send On Hold setting not saving.

== Version 2.4.1 8th May, 2022 ==
1. Missing Excel directory under library added - Will fix the global product synch error
2. Mapping of individual product to Xero sales accounts added - This is in addition to mapping by product category.

== Version 2.4.0 29nd March, 2022 ==
1. Fixed cart coupon tax handling by Xero fixed.
2. Fix Xero authorisation Guzzle error and updated Xero library errors.
3. Fix for Send invoice on completion.
4. Address mapping - added county and mapped phone no to phone field instead of mobile.
5. A refund in WC showed a misc ajustment on the credit note, now removed.
6. Guzzle uncaught error on authorising connection to Xero
7. Updated Guzzle library - only versions of php >7.1 are now supported.
8. Improved invoice and reference numbering layout.

== Version 2.3.2.5 11th Feb, 2022 ==
1. Synch with Payments in Xero to complete order and mark as Paid in Xero. 
2. Fix for Paypal gateway cancellation return.
3. Fallback if using Company Name to use First Name Last Name if blank.
4. Fix for broken connection not showing on button.
5. Fix for shipping tax not following product tax rates for wildcard countries.
6. Fix for undefined property php error in bulk send

== Version 2.3.2.3 26th Jan, 2022 ==
1. Translate Xero connection status broken message to be meaningful.
2. Fix invoice reference to use WC Sequential Number plugin.
3. Fix undefined function error during authorisation step to Xero.
4. Support for "Purchase Orders fo WC" gateway plugin added.

== Version 2.3.2.2 7th Jan, 2022 ==
1. Fix guzzle error for sending invoice from Xero.
2. Fix undefined index error in inventory synch WC to Xero.
3. Add delivery address to invoice - not working yet.

== Version 2.3.2.1 28th December, 2021 ==
1. Inventory synch fix for when it reaches zero.
2. Option to add shipping address as a line item.
3. Invoice numbering enhancement - improved layout.
4. Invoice reference enhancement - improved layout and use custom invoice no
5. Fix for send invoice and send payment on completion.
6. Fix for Illegal String Offsets in Tax Rates error on send invoice.
7. Fix for upgrade process and licence check added.

== Version 2.3.2.0 29th November, 2021 ==
1. Xero invoice custom numbering logic rework to work with custom prefix and start no.
2. Add WC Sequential Numbering plugin compatability.
3. Fix for hook to custom meta invoice no for WC PDF plugins. 

== Version 2.3.1.9 16th November, 2021 ==
1. Fix for simple tax setting going missing. 
2. Fix for inventory synch set to none not releasing.
3. Fix for custom invoice start no being ignored.
4. Fix for parsing special characters in order data to Xero. 

== Version 2.3.1.8 8th November, 2021 ==
1. Fix for Array() error.
2. Fix for address not posting for new customer.
3. Fix for Xero invoice sequence.
4. Fix for php getaddresses on null critical error.
5. Fix for invoice # error failed send.
6. Fix for tracking categories not saving.
7. Fix for coupon tax error.
8. Fix for address not sending if contact doesn't exist in Xero.

== Version 2.3.0.4 6th October, 2021 ==
1. Fix for credit card payment gateway conflict.
2. Replace Excel library version for download files compatible with php7.3 and above.
3. Add inventory synch schedule option of every 5 mins.
4. Fix for licence authentication and saving.

== Version 2.3.0.3 12th September, 2021 ==
1. Fix for some payment gateways not showing in Xeroom.
2. Fix for using hyphens in bank accounts.
3. Fix for invalidstate error on connecting to Xero.

== Version 2.3.0.1 24th August, 2021 ==
1. Add Global Product Synch WC to Xero - For initial setup and ongoing creation/maintenance of products in Xero from WC.
2. Email Xero invoices to end customers automatically and by bulk selection.
3. Bug fix for discounts applied manually to WC order.
4. Bug fix for coupons with different tax rates to match WC allocation method.
5. Bug fix for fixed coupon discount being applied twice.
6. Bug fix for Credit Note tax being credited when zero rated item.
7. Bug fix for Credit Note where rounding is on invoice was not on CN.
8. Allow rounding account to be set.
9. Add order notes to invoice face as line item.
10. Mapping products to extra sales accounts - allow lower level children categories to be mapped.
11. Cosmetic and layout improvements. 
12. Xero Address Mapping Use Xero Address- Fallback in case no contact exists.
13. Xero Address Mapping Use Company Name - Fallback in case of blank.
14. Set Xeroom invoice status to Paid if payment made in Xero.
15. Add order send trigger of WC status "on hold".

== Version 2.2.3.2 17th July, 2021 ==
1. Add exact Xero connection URI generated and displayed for customer website.
2. Tracking Categories added - beta version.
3. Xero Connection - Xero token refresh every 15 mins to prevent drops.
4. Global Inventory Synch improvements - any error is displayed in synch tab.
5. Batch Send cron job processes optimised for call rate into XeroAPI and restart every 5 mins.
6. Fix to use hyphens in Xero ac codes.
7. Fix for GST settings not showing if only standard rate taxes set in WC.
8. Fix for manual Payment Send button not working due to Xero API changes.
9. Fix for licence authentication warning message.
10. Fix for GST with coupons being done on after-coupon deduction instead of before.

== Version 2.2.2 8th April, 2021 ==

1. VAT/GST sales taxes revamped to work with simplified dtb processing.
2. Custom shipping code setting not reset.

== Version 2.2.1 25th March, 2021 ==

1. Ability to set custom Shipping Price code and description.
2. Ability to send orders containing SKU codes longer than the 30 character limit set by Xero.
3. Invoice Creation Date and Send Invoices settings preserved on upgrade.
4. Prevent failed status orders from posting. 
5. Bulk send cron job cancel button.
6. Bulk data loader export/import spreadsheet file error fixed.
7. Enable any of the many WooCommerce PDF plugin numbers to be used with Xeroom eg wcpdfinvoices.com
8. Plugin updater added to enable upgrade info and one click upgrades to be done like other plugins.
9. Various bug fixes.  

== Description ==
Xeroom will intelligently push WooCommerce orders and payments into Xero to automatically create sales invoices, credit notes and inventory changes saving hours of reentering data. 
It is an extension of WooCommerce that will take the sales orders and post them to the Xero accounting application creating new sales invoices. 

1. Posts transactions intelligently into the correct nominal accounts in xero.
2. Passes customer name and address details, order lines, shipping, prices, sales taxes, discounts, coupons, product SKU codes, descriptions, inventory, references and payments to Xero.
3. Generates credit notes in Xero for order refunds in Woo.
4. Inventory synchronisation both ways with option to choose which Master.  Global synch and synch by each product on order with logs.
5. Post sales into different product and geography accounts in Xero to provide value added reporting in Xero.
6. Post shipping charges to a separate Xero account.
7. Choice of Xero or WooCommerce as Master for invoice data.
8. Ability to post individual orders from WooCommerce order screen with one-click button.
9. Bulk posting of historic orders - for new installations or migrations.
10. Set sales tax methods to apply in Xero at a simple or complex level where all taxes can be mapped. 
11. Set Autocomplete to post automatically on completion of checkout payment.
12. Full control over the status of created invoices in Xero - ie draft, unpaid or paid.
13. Post payments to different Xero bank accounts according to the checkouts used in WooCommerce.
14. Add prefixes to the Xero order number and Xero reference with option to set start no.
15. Ability to bring the payment reference from the gateway into Xero reference field.
16. Set payment gateways one-by-one to post automatically permitting autocompletion for instant payment and payment-on-account customers. 
17. Uses latest secure OAuth2.0 for connection to Xero.
18. Debug screen to pass all Xero generated errors back and display them for troubleshooting.
19. Displays the status of orders and enable management of posting from WooCommerce order manager. 
19. Batch selection and posting of orders and payments from WooCommerce order dashboard with option to control posting rate to avoid Xero rate limits.
20. Set trigger for posting order - on order manually, creation, processing, completion.
21. Ability to repost an order in case it's posting failed for any reason or for test purposes using a sequenced alphatetical suffix.
22. Ability to post payments to credit type accounts.
23. Ability to set customer name, company name or email to be the main contact name in Xero.
24. Ability to create credit notes on full or partial refunds. 
25. Ability to post payments to Xero current asset or current liability accounts - enables these to be used as clearing account for reconciliations.
26. Ability to set due date for invoice payments.
27. Prevent Xero from generating bills on inventory adjustments.

Xeroom provides far more functionality than currently exists on other similar plugins providing intelligent posting of transactions into Xero's accounts rather than just a flat memo dump of the order thus allowing proper analysis and audit trails.  

== Installation ==

For the full step-by-step procudure please go to the installation page at https://www.xeroom.com/installation-instructions/. If you don't have the time or need help then we offer fixed-price installation services on our shop page. 

 