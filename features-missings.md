# 🧾 Updated Missing Features for a Full Accounting System

This document outlines the remaining features missing from the current system to achieve a complete, enterprise-ready **Accrual, Double-Entry Accounting System**. 

While the core double-entry bookkeeping engine, Chart of Accounts integration, and standard financial statements (Trial Balance, P&L, Balance Sheet) are now fully implemented, the following advanced operational modules remain outstanding:

---

## 1. Accounts Payable (AP) & Vendor Bills Accrual
* **Current Status:** Purchase transactions are recorded as immediate cash payments. The system does not support booking an unpaid vendor invoice and tracking liabilities over time.
* **What's Missing:**
  * **Vendor Bills Resource:** An independent document entry to book vendor invoices (increasing inventory assets and accounts payable liabilities) without immediately releasing cash.
  * **Vendor Bill Payments Flow:** A workflow allowing partial or full payments of booked vendor bills.
  * **Vendor Aging Report:** A report detailing outstanding payables aged by intervals (0-30, 31-60, 61-90, 90+ days).
  * **Debit Notes (Supplier Credits):** Entry for vendor returns, offsetting outstanding accounts payable.

## 2. Advanced Accounts Receivable (AR) & Credit Management
* **Current Status:** Customer payments can be posted, and outstanding credit balances are tracked.
* **What's Missing:**
  * **Customer Statement Generator:** A tool to generate and export running-balance statements showing invoices, payments, and credits over a selected date range to send to customers.
  * **Credit Notes / Customer Refunds:** Formal credit note documents for sales returns, reducing Accounts Receivable and Sales Revenue.

## 3. Inventory Costing Methods (FIFO / WAC)
* **Current Status:** Stock levels are adjusted, and Cost of Goods Sold (COGS) is computed based on a static product cost field.
* **What's Missing:**
  * **Costing Flow Logic:** Automated stock valuation tracking using Weighted Average Costing (WAC) or First-In, First-Out (FIFO) when purchasing batches at varying costs.
  * **Automated COGS Recalculation:** Dynamically posting the exact cost value of inventory sold at checkout based on the costing method.
  * **Inventory Valuation Report:** Detailed asset valuation report showing current quantity, cost-basis, and total value of stock.

## 4. Advanced Taxation & VAT Reporting
* **Current Status:** Tax amounts are calculated and posted to the Sales Tax Payable or Purchase Tax Paid general ledger accounts.
* **What's Missing:**
  * **Multi-Tax Rates Config:** Configuration of regional tax zones, zero-rated items, tax-exempt goods, or compounding taxes.
  * **Tax Return Filings Summary:** A reporting interface matching input tax vs. output tax for filing periodic tax returns.

## 5. Bank Reconciliation
* **Current Status:** Cash registers and bank accounts are tracked manually through daybook entries and journal lines.
* **What's Missing:**
  * **Statement Imports:** Uploading bank statements in standard formats (CSV, QIF, OFX).
  * **Reconciliation Dashboard:** A matching interface comparing internal ledger lines with statement records and automatically adjusting transaction variances.

## 6. Segmental Reporting & Budgets
* **Current Status:** None.
* **What's Missing:**
  * **Cost Centers / Departmental Tags:** Ability to assign classes, departments, projects, or branches to journal lines. This allows generating segmental Profit & Loss statements (e.g. tracking profitability per branch).
  * **Expense Budgeting:** Assigning monthly budget limits to expense accounts and comparing them with actual spending (Budget vs. Actual report).

## 7. Fixed Asset Register & Depreciation
* **Current Status:** None.
* **What's Missing:**
  * **Asset Register:** Management of non-inventory assets (e.g. computer equipment, vehicles, building improvements).
  * **Depreciation Engine:** Configuring depreciation rules (straight-line or declining balance) and running periodic depreciation posts to the general ledger.

## 8. Multi-Currency Support
* **Current Status:** Hardcoded to base currency (NPR / Nepalese Rupee).
* **What's Missing:**
  * **Foreign Currency Invoicing & Payments:** Processing sales, purchases, bank accounts, and invoices in different currencies.
  * **Exchange Rate Revaluation:** Period-end adjustments calculating realized and unrealized foreign exchange gains or losses.

## 9. Period Locking & Fiscal Closings
* **Current Status:** None.
* **What's Missing:**
  * **Hard Lock Dates:** Restricting users from creating, editing, or deleting journal entries prior to a specific date (e.g., locking a closed month or quarter).
  * **Year-End Closing Run:** Automating the transfer of temporary income and expense account balances to Retained Earnings and initializing the next fiscal year ledger.
