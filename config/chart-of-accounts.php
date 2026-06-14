<?php

return [
    'code_step' => 10,

    'categories' => [
        'asset' => [
            'label' => 'Assets',
            'sort' => 1,
            'subtypes' => [
                'cash_equivalents' => [
                    'label' => 'Cash and Cash Equivalents',
                    'description' => 'Cash in hand, bank balances, and highly liquid short-term balances.',
                    'code_start' => 1000,
                    'code_end' => 1099,
                    'accounts' => [
                        ['code' => 1010, 'name' => 'Cash on Hand', 'description' => 'Physical cash held at the business.'],
                        ['code' => 1020, 'name' => 'Petty Cash', 'description' => 'Small cash balance for minor operational expenses.'],
                        ['code' => 1030, 'name' => 'Bank Accounts', 'description' => 'Operating bank balances.'],
                    ],
                ],
                'receivables' => [
                    'label' => 'Receivables',
                    'description' => 'Amounts owed to the business by customers and other parties.',
                    'code_start' => 1100,
                    'code_end' => 1199,
                    'accounts' => [
                        ['code' => 1110, 'name' => 'Accounts Receivable', 'description' => 'Customer balances due for goods or services sold on credit.'],
                        ['code' => 1120, 'name' => 'Notes Receivable', 'description' => 'Promissory notes or other documented amounts due.'],
                    ],
                ],
                'inventory' => [
                    'label' => 'Inventory',
                    'description' => 'Stock held for resale.',
                    'code_start' => 1200,
                    'code_end' => 1299,
                    'accounts' => [
                        ['code' => 1210, 'name' => 'Merchandise Inventory', 'description' => 'Goods available for sale.'],
                    ],
                ],
                'prepaids' => [
                    'label' => 'Prepaid and Deferred Charges',
                    'description' => 'Payments made in advance for future benefits.',
                    'code_start' => 1300,
                    'code_end' => 1399,
                    'accounts' => [
                        ['code' => 1310, 'name' => 'Prepaid Expenses', 'description' => 'Expenses paid before the benefit period.'],
                    ],
                ],
            ],
        ],

        'liability' => [
            'label' => 'Liabilities',
            'sort' => 2,
            'subtypes' => [
                'payables' => [
                    'label' => 'Accounts Payable',
                    'description' => 'Amounts owed to suppliers and creditors.',
                    'code_start' => 2000,
                    'code_end' => 2099,
                    'accounts' => [
                        ['code' => 2010, 'name' => 'Accounts Payable', 'description' => 'Supplier balances due for goods or services purchased on credit.'],
                    ],
                ],
                'accrued_liabilities' => [
                    'label' => 'Accrued Expenses and Liabilities',
                    'description' => 'Expenses incurred but not yet paid.',
                    'code_start' => 2100,
                    'code_end' => 2199,
                    'accounts' => [
                        ['code' => 2110, 'name' => 'Accrued Expenses', 'description' => 'Expenses recognized before cash payment.'],
                    ],
                ],
                'debt' => [
                    'label' => 'Borrowings',
                    'description' => 'Short and long-term financing obligations.',
                    'code_start' => 2200,
                    'code_end' => 2299,
                    'accounts' => [
                        ['code' => 2210, 'name' => 'Loans Payable', 'description' => 'Loan principal outstanding.'],
                        ['code' => 2220, 'name' => 'Line of Credit', 'description' => 'Revolving borrowing facility balances.'],
                    ],
                ],
                'customer_advances' => [
                    'label' => 'Customer Advances',
                    'description' => 'Money received before goods or services are delivered.',
                    'code_start' => 2300,
                    'code_end' => 2399,
                    'accounts' => [
                        ['code' => 2310, 'name' => 'Customer Deposits', 'description' => 'Advance amounts held against future sales.'],
                    ],
                ],
            ],
        ],

        'equity' => [
            'label' => 'Equity',
            'sort' => 3,
            'subtypes' => [
                'capital' => [
                    'label' => 'Contributed Capital',
                    'description' => 'Owner and shareholder capital contributions.',
                    'code_start' => 3000,
                    'code_end' => 3099,
                    'accounts' => [
                        ['code' => 3010, 'name' => 'Owner Capital', 'description' => 'Initial and additional owner investment.'],
                    ],
                ],
                'retained_earnings' => [
                    'label' => 'Retained Earnings',
                    'description' => 'Accumulated profits retained in the business.',
                    'code_start' => 3100,
                    'code_end' => 3199,
                    'accounts' => [
                        ['code' => 3110, 'name' => 'Retained Earnings', 'description' => 'Net income retained from prior periods.'],
                    ],
                ],
                'drawings' => [
                    'label' => 'Contra Equity',
                    'description' => 'Owner withdrawals and other equity reductions.',
                    'code_start' => 3900,
                    'code_end' => 3999,
                    'accounts' => [
                        ['code' => 3910, 'name' => 'Owner Drawings', 'description' => 'Amounts withdrawn by owners for personal use.'],
                    ],
                ],
            ],
        ],

        'revenue' => [
            'label' => 'Revenue',
            'sort' => 4,
            'subtypes' => [
                'product_sales' => [
                    'label' => 'Product Sales',
                    'description' => 'Income from the sale of inventory or merchandise.',
                    'code_start' => 4000,
                    'code_end' => 4099,
                    'accounts' => [
                        ['code' => 4010, 'name' => 'Sales Revenue', 'description' => 'Primary product sales income.'],
                    ],
                ],
                'service_revenue' => [
                    'label' => 'Service Revenue',
                    'description' => 'Income earned from services.',
                    'code_start' => 4100,
                    'code_end' => 4199,
                    'accounts' => [
                        ['code' => 4110, 'name' => 'Service Revenue', 'description' => 'Revenue from services rendered.'],
                    ],
                ],
                'other_revenue' => [
                    'label' => 'Other Operating Revenue',
                    'description' => 'Ancillary operating income not from primary sales.',
                    'code_start' => 4200,
                    'code_end' => 4299,
                    'accounts' => [
                        ['code' => 4210, 'name' => 'Other Revenue', 'description' => 'Miscellaneous operating income.'],
                    ],
                ],
            ],
        ],

        'expense' => [
            'label' => 'Expenses',
            'sort' => 5,
            'subtypes' => [
                'cogs' => [
                    'label' => 'Cost of Goods Sold',
                    'description' => 'Direct cost of inventory sold.',
                    'code_start' => 5000,
                    'code_end' => 5099,
                    'accounts' => [
                        ['code' => 5010, 'name' => 'Cost of Sales', 'description' => 'Direct inventory cost recognized on sale.'],
                    ],
                ],
                'operating_expenses' => [
                    'label' => 'Operating Expenses',
                    'description' => 'Day-to-day operating costs.',
                    'code_start' => 5100,
                    'code_end' => 5199,
                    'accounts' => [
                        ['code' => 5110, 'name' => 'Office Expenses', 'description' => 'Supplies, utilities, and office running costs.'],
                        ['code' => 5120, 'name' => 'Administrative Expenses', 'description' => 'General administration costs.'],
                    ],
                ],
                'marketing' => [
                    'label' => 'Marketing Expenses',
                    'description' => 'Advertising and promotion costs.',
                    'code_start' => 5200,
                    'code_end' => 5299,
                    'accounts' => [
                        ['code' => 5210, 'name' => 'Advertising', 'description' => 'Paid advertising and promotional spend.'],
                    ],
                ],
                'finance_tax' => [
                    'label' => 'Finance and Tax Expenses',
                    'description' => 'Interest, bank charges, and tax-related costs.',
                    'code_start' => 5300,
                    'code_end' => 5399,
                    'accounts' => [
                        ['code' => 5310, 'name' => 'Bank Charges', 'description' => 'Bank and payment processing fees.'],
                        ['code' => 5320, 'name' => 'Interest Expense', 'description' => 'Borrowing cost recognized in the period.'],
                    ],
                ],
            ],
        ],
    ],
];
