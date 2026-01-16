
 *** Refactor bonus reward system to fit new tables ***

there are 3 tables
1. Roles of the three tables (one sentence each)
ocus_customer_bonus_items
👉 Calculates bonuses per order product
(what should be earned or reversed at product level)
ocus_customer_reward
👉 Customer bonus ledger + bonus lots with expiry
(what exists in the wallet and how much is still usable)
ocus_customer_reward_allocation
👉 Links spending to specific awarded bonuses
(what paid for what — critical for expiry & returns)

Flow 
mysql> select * from ocus_event where code LIKE '%bonus%';
+----------+-------------------------------+----------------------------------------------------+--------------------------------------------------------------+--------+------------+
| event_id | code                          | trigger                                            | action                                                       | status | sort_order |
+----------+-------------------------------+----------------------------------------------------+--------------------------------------------------------------+--------+------------+
|       57 | bonus_manager_order_complete  | catalog/model/checkout/order/addOrderHistory/after | extension/module/bonus_manager/awardBonusesOnOrderComplete   |      1 |          0 |
|       64 | bonus_manager_return_complete | admin/model/sale/return/addReturnHistory/after     | extension/module/bonus_manager/deductBonusesOnReturnComplete |      1 |          0 |
+----------+-------------------------------+----------------------------------------------------+--------------------------------------------------------------+--------+------------+

 in OC3 event system addOrderhistory triggers  catalog/model/checkout/order/addOrderHistory 
 that triggers catalog/controller/extension/module/bonus_manager/awardBonusesOnOrderComplete
 that in its turn calls 		$this->model_extension_module_bonus_manager->awardBonusesForOrder($order_id);

So to work with new tables, we need to modify the model method awardBonusesForOrder() to:


### Model Updates
- **File**: `catalog/model/extension/module/bonus_manager.php`
- **Changes**:
Award Bonuses on Order Completion:
  1. Modify `awardBonusesForOrder()` method:

Return Product Bonuses on Return Completion:
  2. Modify `returnProductBonuses($return_id)` method:

- **File**: `catalog/model/total/reward.php`
  3. Modify `confirm()` method to allocate spent bonuses correctly using the new allocation table.
Customer chooses to spend bonuses
System checks available balance (remaining)
One SPEND row is created in ocus_customer_reward
System selects AWARD rows (FIFO by expiry)
For each award used:
decrement remaining
create allocation rows
Tables involved
ocus_customer_reward (spend row)
reward_kind = 'spend'
points      = -1167
remaining   = NULL
ocus_customer_reward_allocation
spend_reward_id → award_reward_id
points allocated