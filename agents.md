# 🧾 Simple POS System — Laravel + FilamentPHP + SQLite

This document outlines the structure, features, and implementation plan for a simple POS (Point of Sale) system using **Laravel**, **FilamentPHP**, and **SQLite**.

---

## ⚙️ Overview

The POS system provides a user-friendly interface for managing products, categories, customers, purchases, and sales.  
It supports multi-tab order handling, barcode scanning, multiple payment methods, and printable invoices.

---

## 📋 Core Features

### 🧠 POS Page
- **Multiple Tabs**
  - *Hold Order* — temporarily store an order for later processing.
  - *Resume Order* — restore a held order for checkout.
- **Product Display**
  - Categorized product listing with:
    - Product name  
    - Stock quantity  
    - Price  
    - Barcode (for scanner input)
- **Barcode Scanner Integration**
  - Scan products directly to add them to the cart.
- **Search Box**
  - Real-time search across products by name, code, or category.
- **Cart Functionality**
  - Add multiple products  
  - Modify quantity  
  - Set discounts (per item or entire order)  
  - View total, taxes, and grand total dynamically
- **Payment Handling**
  - **Payment Types:** Paid / Credit  
  - **Payment Methods:** Cash, Cheque, Online Transfer, etc.  
  - **Customer Account Integration:** Track credit sales and outstanding balances.
- **Invoice Printing**
  - Generate and print invoices with store info, items, totals, and payment details.

---

## 💳 Payment Handling

Payments are handled through a unified interface allowing:
- Full or partial payments
- Different methods (cash, cheque, online)
- Tracking of unpaid (credit) sales linked to customers

---

## 🧾 Invoice Printing

Upon completing a sale:
- Generate PDF invoice using `laravel-dompdf` or `barryvdh/laravel-dompdf`
- Include:
  - Store info  
  - Customer info  
  - Item list with quantities and prices  
  - Total, tax, discount, and net payable  
  - Payment method and status  

---
