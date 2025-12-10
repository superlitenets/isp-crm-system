<?php

namespace App;

class Accounting {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->initializeTables();
    }
    
    private function initializeTables(): void {
        if (function_exists('initializeAccountingTables')) {
            initializeAccountingTables($this->db);
        } else {
            require_once __DIR__ . '/../config/init_db.php';
            if (function_exists('initializeAccountingTables')) {
                initializeAccountingTables($this->db);
            }
        }
    }
    
    // Settings
    public function getSetting(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM accounting_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }
    
    public function setSetting(string $key, $value): void {
        $stmt = $this->db->prepare("
            INSERT INTO accounting_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $value, $value]);
    }
    
    public function getNextNumber(string $type): string {
        $prefix = $this->getSetting("{$type}_prefix", strtoupper(substr($type, 0, 3)) . '-');
        $number = (int)$this->getSetting("{$type}_next_number", 1001);
        $this->setSetting("{$type}_next_number", $number + 1);
        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
    
    // Tax Rates
    public function getTaxRates(): array {
        return $this->db->query("SELECT * FROM tax_rates WHERE is_active = true ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDefaultTaxRate(): ?array {
        $stmt = $this->db->query("SELECT * FROM tax_rates WHERE is_default = true AND is_active = true LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    // Products/Services
    public function getProducts(): array {
        return $this->db->query("
            SELECT ps.*, tr.name as tax_name, tr.rate as tax_rate 
            FROM products_services ps 
            LEFT JOIN tax_rates tr ON ps.tax_rate_id = tr.id 
            WHERE ps.is_active = true 
            ORDER BY ps.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getProduct(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM products_services WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createProduct(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO products_services (code, name, description, type, unit_price, cost_price, tax_rate_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, true)
        ");
        $stmt->execute([
            $data['code'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['type'] ?? 'service',
            $data['unit_price'] ?? 0,
            $data['cost_price'] ?? 0,
            $data['tax_rate_id'] ?: null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateProduct(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE products_services SET code = ?, name = ?, description = ?, type = ?, 
            unit_price = ?, cost_price = ?, tax_rate_id = ? WHERE id = ?
        ");
        $stmt->execute([
            $data['code'] ?? null,
            $data['name'],
            $data['description'] ?? null,
            $data['type'] ?? 'service',
            $data['unit_price'] ?? 0,
            $data['cost_price'] ?? 0,
            $data['tax_rate_id'] ?: null,
            $id
        ]);
    }
    
    // Vendors
    public function getVendors(): array {
        return $this->db->query("SELECT * FROM vendors WHERE is_active = true ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getVendor(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createVendor(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO vendors (name, contact_person, email, phone, address, city, country, tax_pin, payment_terms, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? 'Kenya',
            $data['tax_pin'] ?? null,
            $data['payment_terms'] ?? 30,
            $data['notes'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateVendor(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, 
            address = ?, city = ?, country = ?, tax_pin = ?, payment_terms = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['contact_person'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? 'Kenya',
            $data['tax_pin'] ?? null,
            $data['payment_terms'] ?? 30,
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    // Invoices
    public function getInvoices(array $filters = []): array {
        $sql = "
            SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND i.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND i.issue_date >= ?";
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND i.issue_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getInvoice(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                   c.address as customer_address, c.account_number as customer_account
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($invoice) {
            $stmt = $this->db->prepare("
                SELECT ii.*, ps.name as product_name, tr.rate as tax_rate 
                FROM invoice_items ii 
                LEFT JOIN products_services ps ON ii.product_id = ps.id
                LEFT JOIN tax_rates tr ON ii.tax_rate_id = tr.id
                WHERE ii.invoice_id = ? ORDER BY ii.sort_order
            ");
            $stmt->execute([$id]);
            $invoice['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $invoice ?: null;
    }
    
    public function createInvoice(array $data): int {
        $invoiceNumber = $data['invoice_number'] ?? $this->getNextNumber('invoice');
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+' . ($data['payment_terms'] ?? 30) . ' days'));
        
        $stmt = $this->db->prepare("
            INSERT INTO invoices (invoice_number, customer_id, order_id, ticket_id, issue_date, due_date, 
                status, subtotal, tax_amount, discount_amount, total_amount, balance_due, currency, notes, terms, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceNumber,
            $data['customer_id'] ?: null,
            $data['order_id'] ?? null,
            $data['ticket_id'] ?? null,
            $data['issue_date'] ?? date('Y-m-d'),
            $dueDate,
            $data['status'] ?? 'draft',
            $data['subtotal'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['currency'] ?? 'KES',
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            $data['created_by'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateInvoice(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE invoices SET customer_id = ?, issue_date = ?, due_date = ?, status = ?,
                subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?, balance_due = ?,
                notes = ?, terms = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['customer_id'] ?: null,
            $data['issue_date'],
            $data['due_date'],
            $data['status'],
            $data['subtotal'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['balance_due'] ?? $data['total_amount'],
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            $id
        ]);
    }
    
    public function addInvoiceItem(int $invoiceId, array $item): int {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, tax_rate_id, tax_amount, discount_percent, line_total, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceId,
            $item['product_id'] ?: null,
            $item['description'],
            $item['quantity'] ?? 1,
            $item['unit_price'],
            $item['tax_rate_id'] ?: null,
            $item['tax_amount'] ?? 0,
            $item['discount_percent'] ?? 0,
            $item['line_total'],
            $item['sort_order'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function deleteInvoiceItems(int $invoiceId): void {
        $stmt = $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
    }
    
    public function recalculateInvoice(int $invoiceId): void {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(line_total), 0) as subtotal, COALESCE(SUM(tax_amount), 0) as tax_amount
            FROM invoice_items WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $total = $totals['subtotal'] + $totals['tax_amount'];
        
        $stmt = $this->db->prepare("
            UPDATE invoices SET subtotal = ?, tax_amount = ?, total_amount = ?, 
                balance_due = ? - amount_paid, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$totals['subtotal'], $totals['tax_amount'], $total, $total, $invoiceId]);
    }
    
    // Quotes
    public function getQuotes(array $filters = []): array {
        $sql = "
            SELECT q.*, c.name as customer_name
            FROM quotes q
            LEFT JOIN customers c ON q.customer_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND q.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY q.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getQuote(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM quotes q
            LEFT JOIN customers c ON q.customer_id = c.id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        $quote = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($quote) {
            $stmt = $this->db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order");
            $stmt->execute([$id]);
            $quote['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $quote ?: null;
    }
    
    public function createQuote(array $data): int {
        $quoteNumber = $data['quote_number'] ?? $this->getNextNumber('quote');
        
        $stmt = $this->db->prepare("
            INSERT INTO quotes (quote_number, customer_id, issue_date, expiry_date, status, 
                subtotal, tax_amount, discount_amount, total_amount, currency, notes, terms, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $quoteNumber,
            $data['customer_id'] ?: null,
            $data['issue_date'] ?? date('Y-m-d'),
            $data['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')),
            $data['status'] ?? 'draft',
            $data['subtotal'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['currency'] ?? 'KES',
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            $data['created_by'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateQuote(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE quotes SET customer_id = ?, issue_date = ?, expiry_date = ?, status = ?,
                subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?, 
                notes = ?, terms = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['customer_id'] ?: null,
            $data['issue_date'] ?? date('Y-m-d'),
            $data['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')),
            $data['status'] ?? 'draft',
            $data['subtotal'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['notes'] ?? null,
            $data['terms'] ?? null,
            $id
        ]);
    }
    
    public function addQuoteItem(int $quoteId, array $item): int {
        $quantity = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        $taxRate = (float)($item['tax_rate'] ?? 0);
        $discount = (float)($item['discount'] ?? 0);
        
        $lineTotal = $quantity * $unitPrice - $discount;
        $taxAmount = $lineTotal * ($taxRate / 100);
        
        $stmt = $this->db->prepare("
            INSERT INTO quote_items (quote_id, product_id, description, quantity, unit_price, 
                discount, tax_rate, tax_amount, line_total, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $quoteId,
            $item['product_id'] ?: null,
            $item['description'] ?? '',
            $quantity,
            $unitPrice,
            $discount,
            $taxRate,
            $taxAmount,
            $lineTotal,
            $item['sort_order'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function deleteQuoteItems(int $quoteId): void {
        $stmt = $this->db->prepare("DELETE FROM quote_items WHERE quote_id = ?");
        $stmt->execute([$quoteId]);
    }
    
    public function recalculateQuote(int $quoteId): void {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(line_total), 0) as subtotal, COALESCE(SUM(tax_amount), 0) as tax_amount
            FROM quote_items WHERE quote_id = ?
        ");
        $stmt->execute([$quoteId]);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $total = $totals['subtotal'] + $totals['tax_amount'];
        
        $stmt = $this->db->prepare("
            UPDATE quotes SET subtotal = ?, tax_amount = ?, total_amount = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$totals['subtotal'], $totals['tax_amount'], $total, $quoteId]);
    }
    
    public function updateQuoteStatus(int $id, string $status): void {
        $stmt = $this->db->prepare("UPDATE quotes SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $id]);
    }
    
    public function convertQuoteToInvoice(int $quoteId): int {
        $quote = $this->getQuote($quoteId);
        if (!$quote) throw new \Exception("Quote not found");
        
        $invoiceId = $this->createInvoice([
            'customer_id' => $quote['customer_id'],
            'subtotal' => $quote['subtotal'],
            'tax_amount' => $quote['tax_amount'],
            'total_amount' => $quote['total_amount'],
            'notes' => $quote['notes'],
            'terms' => $quote['terms'],
            'status' => 'draft'
        ]);
        
        foreach ($quote['items'] as $item) {
            $this->addInvoiceItem($invoiceId, $item);
        }
        
        $stmt = $this->db->prepare("UPDATE quotes SET status = 'converted', converted_to_invoice_id = ? WHERE id = ?");
        $stmt->execute([$invoiceId, $quoteId]);
        
        return $invoiceId;
    }
    
    // Vendor Bills
    public function getBills(array $filters = []): array {
        $sql = "
            SELECT b.*, v.name as vendor_name
            FROM vendor_bills b
            LEFT JOIN vendors v ON b.vendor_id = v.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['vendor_id'])) {
            $sql .= " AND b.vendor_id = ?";
            $params[] = $filters['vendor_id'];
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getBill(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT b.*, v.name as vendor_name, v.email as vendor_email, v.phone as vendor_phone
            FROM vendor_bills b
            LEFT JOIN vendors v ON b.vendor_id = v.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $bill = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($bill) {
            $stmt = $this->db->prepare("SELECT * FROM vendor_bill_items WHERE bill_id = ? ORDER BY sort_order");
            $stmt->execute([$id]);
            $bill['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $bill ?: null;
    }
    
    public function createBill(array $data): int {
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+' . ($data['payment_terms'] ?? 30) . ' days'));
        
        $stmt = $this->db->prepare("
            INSERT INTO vendor_bills (bill_number, vendor_id, purchase_order_id, bill_date, due_date, 
                status, subtotal, tax_amount, total_amount, balance_due, currency, reference, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['bill_number'],
            $data['vendor_id'] ?: null,
            $data['purchase_order_id'] ?? null,
            $data['bill_date'] ?? date('Y-m-d'),
            $dueDate,
            $data['status'] ?? 'unpaid',
            $data['subtotal'] ?? 0,
            $data['tax_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['total_amount'] ?? 0,
            $data['currency'] ?? 'KES',
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['created_by'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function addBillItem(int $billId, array $item): int {
        $quantity = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        $taxRate = (float)($item['tax_rate'] ?? 0);
        $discount = (float)($item['discount'] ?? 0);
        
        $lineTotal = $quantity * $unitPrice - $discount;
        $taxAmount = $lineTotal * ($taxRate / 100);
        
        $stmt = $this->db->prepare("
            INSERT INTO vendor_bill_items (bill_id, product_id, description, quantity, unit_price, 
                discount, tax_rate, tax_amount, line_total, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billId,
            $item['product_id'] ?? null,
            $item['description'] ?? '',
            $quantity,
            $unitPrice,
            $discount,
            $taxRate,
            $taxAmount,
            $lineTotal,
            $item['sort_order'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function recalculateBill(int $billId): void {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(line_total), 0) as subtotal, COALESCE(SUM(tax_amount), 0) as tax_amount
            FROM vendor_bill_items WHERE bill_id = ?
        ");
        $stmt->execute([$billId]);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $total = $totals['subtotal'] + $totals['tax_amount'];
        
        $stmt = $this->db->prepare("
            UPDATE vendor_bills SET subtotal = ?, tax_amount = ?, total_amount = ?, 
                balance_due = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$totals['subtotal'], $totals['tax_amount'], $total, $total, $billId]);
    }
    
    public function updateBill(int $id, array $data): void {
        $stmt = $this->db->prepare("
            UPDATE vendor_bills SET vendor_id = ?, bill_date = ?, due_date = ?, 
                reference = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $data['vendor_id'] ?: null,
            $data['bill_date'] ?? date('Y-m-d'),
            $data['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    // Expenses
    public function getExpenses(array $filters = []): array {
        $sql = "
            SELECT e.*, ec.name as category_name, v.name as vendor_name, emp.name as employee_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            LEFT JOIN employees emp ON e.employee_id = emp.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND e.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        $sql .= " ORDER BY e.expense_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getExpenseCategories(): array {
        return $this->db->query("SELECT * FROM expense_categories WHERE is_active = true ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createExpense(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO expenses (expense_number, category_id, vendor_id, expense_date, amount, tax_amount, 
                total_amount, payment_method, reference, description, status, employee_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['expense_number'] ?? null,
            $data['category_id'] ?: null,
            $data['vendor_id'] ?: null,
            $data['expense_date'] ?? date('Y-m-d'),
            $data['amount'],
            $data['tax_amount'] ?? 0,
            $data['total_amount'] ?? $data['amount'],
            $data['payment_method'] ?? null,
            $data['reference'] ?? null,
            $data['description'] ?? null,
            $data['status'] ?? 'approved',
            $data['employee_id'] ?: null,
            $data['created_by'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    // Customer Payments
    public function getCustomerPayments(array $filters = []): array {
        $sql = "
            SELECT cp.*, c.name as customer_name, i.invoice_number
            FROM customer_payments cp
            LEFT JOIN customers c ON cp.customer_id = c.id
            LEFT JOIN invoices i ON cp.invoice_id = i.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND cp.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND cp.payment_date >= ?";
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND cp.payment_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        $sql .= " ORDER BY cp.payment_date DESC, cp.id DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function recordCustomerPayment(array $data): int {
        $paymentNumber = $data['payment_number'] ?? $this->getNextNumber('payment');
        
        $stmt = $this->db->prepare("
            INSERT INTO customer_payments (payment_number, customer_id, invoice_id, payment_date, amount, 
                payment_method, mpesa_transaction_id, mpesa_receipt, reference, notes, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paymentNumber,
            $data['customer_id'] ?: null,
            $data['invoice_id'] ?: null,
            $data['payment_date'] ?? date('Y-m-d'),
            $data['amount'],
            $data['payment_method'],
            $data['mpesa_transaction_id'] ?? null,
            $data['mpesa_receipt'] ?? null,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['status'] ?? 'completed',
            $data['created_by'] ?? null
        ]);
        
        $paymentId = (int)$this->db->lastInsertId();
        
        // Update invoice if linked
        if (!empty($data['invoice_id'])) {
            $this->applyPaymentToInvoice($data['invoice_id'], $data['amount']);
        }
        
        return $paymentId;
    }
    
    private function applyPaymentToInvoice(int $invoiceId, float $amount): void {
        $stmt = $this->db->prepare("
            UPDATE invoices SET 
                amount_paid = amount_paid + ?,
                balance_due = total_amount - (amount_paid + ?),
                status = CASE 
                    WHEN total_amount <= (amount_paid + ?) THEN 'paid'
                    WHEN (amount_paid + ?) > 0 THEN 'partial'
                    ELSE status
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$amount, $amount, $amount, $amount, $invoiceId]);
    }
    
    // Vendor Payments
    public function recordVendorPayment(array $data): int {
        $paymentNumber = $data['payment_number'] ?? $this->getNextNumber('payment');
        
        $stmt = $this->db->prepare("
            INSERT INTO vendor_payments (payment_number, vendor_id, bill_id, payment_date, amount, 
                payment_method, reference, notes, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paymentNumber,
            $data['vendor_id'] ?: null,
            $data['bill_id'] ?: null,
            $data['payment_date'] ?? date('Y-m-d'),
            $data['amount'],
            $data['payment_method'],
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['status'] ?? 'completed',
            $data['created_by'] ?? null
        ]);
        
        $paymentId = (int)$this->db->lastInsertId();
        
        // Update bill if linked
        if (!empty($data['bill_id'])) {
            $this->applyPaymentToBill($data['bill_id'], $data['amount']);
        }
        
        return $paymentId;
    }
    
    private function applyPaymentToBill(int $billId, float $amount): void {
        $stmt = $this->db->prepare("
            UPDATE vendor_bills SET 
                amount_paid = amount_paid + ?,
                balance_due = total_amount - (amount_paid + ?),
                status = CASE 
                    WHEN total_amount <= (amount_paid + ?) THEN 'paid'
                    WHEN (amount_paid + ?) > 0 THEN 'partial'
                    ELSE status
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$amount, $amount, $amount, $amount, $billId]);
    }
    
    // Dashboard Stats
    public function getDashboardStats(): array {
        $stats = [];
        
        // Receivables
        $stats['total_receivable'] = $this->db->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('sent', 'partial', 'overdue')")->fetchColumn();
        $stats['overdue_receivable'] = $this->db->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('sent', 'partial') AND due_date < CURRENT_DATE")->fetchColumn();
        $stats['invoices_count'] = $this->db->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn();
        
        // Payables
        $stats['total_payable'] = $this->db->query("SELECT COALESCE(SUM(balance_due), 0) FROM vendor_bills WHERE status IN ('unpaid', 'partial')")->fetchColumn();
        $stats['overdue_payable'] = $this->db->query("SELECT COALESCE(SUM(balance_due), 0) FROM vendor_bills WHERE status IN ('unpaid', 'partial') AND due_date < CURRENT_DATE")->fetchColumn();
        $stats['bills_count'] = $this->db->query("SELECT COUNT(*) FROM vendor_bills WHERE status IN ('unpaid', 'partial')")->fetchColumn();
        
        // This month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $stats['month_received'] = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $stmt->execute([$monthStart, $monthEnd]);
        $stats['month_expenses'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    // Chart of Accounts
    public function getChartOfAccounts(): array {
        return $this->db->query("SELECT * FROM chart_of_accounts WHERE is_active = true ORDER BY code")->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Reports
    public function getAgingReport(string $type = 'receivable'): array {
        if ($type === 'receivable') {
            $sql = "
                SELECT i.*, c.name as customer_name,
                    CASE 
                        WHEN i.due_date >= CURRENT_DATE THEN 'current'
                        WHEN i.due_date >= CURRENT_DATE - INTERVAL '30 days' THEN '1-30'
                        WHEN i.due_date >= CURRENT_DATE - INTERVAL '60 days' THEN '31-60'
                        WHEN i.due_date >= CURRENT_DATE - INTERVAL '90 days' THEN '61-90'
                        ELSE '90+'
                    END as aging_bucket
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.balance_due > 0 AND i.status IN ('sent', 'partial', 'overdue')
                ORDER BY i.due_date
            ";
        } else {
            $sql = "
                SELECT b.*, v.name as vendor_name,
                    CASE 
                        WHEN b.due_date >= CURRENT_DATE THEN 'current'
                        WHEN b.due_date >= CURRENT_DATE - INTERVAL '30 days' THEN '1-30'
                        WHEN b.due_date >= CURRENT_DATE - INTERVAL '60 days' THEN '31-60'
                        WHEN b.due_date >= CURRENT_DATE - INTERVAL '90 days' THEN '61-90'
                        ELSE '90+'
                    END as aging_bucket
                FROM vendor_bills b
                LEFT JOIN vendors v ON b.vendor_id = v.id
                WHERE b.balance_due > 0 AND b.status IN ('unpaid', 'partial')
                ORDER BY b.due_date
            ";
        }
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getProfitLossReport(string $fromDate, string $toDate): array {
        $report = ['revenue' => [], 'expenses' => [], 'total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0];
        
        // Revenue from paid invoices
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0) as total 
            FROM invoices 
            WHERE issue_date BETWEEN ? AND ? AND amount_paid > 0
        ");
        $stmt->execute([$fromDate, $toDate]);
        $report['total_revenue'] = (float)$stmt->fetchColumn();
        
        // Expenses
        $stmt = $this->db->prepare("
            SELECT ec.name as category, COALESCE(SUM(e.total_amount), 0) as total
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date BETWEEN ? AND ?
            GROUP BY ec.name
            ORDER BY total DESC
        ");
        $stmt->execute([$fromDate, $toDate]);
        $report['expenses'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $stmt->execute([$fromDate, $toDate]);
        $report['total_expenses'] = (float)$stmt->fetchColumn();
        
        $report['net_profit'] = $report['total_revenue'] - $report['total_expenses'];
        
        return $report;
    }
}
