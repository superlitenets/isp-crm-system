<?php

namespace App;

class Inventory {
    private \PDO $db;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    // ==================== CATEGORIES ====================
    
    public function getCategories(): array {
        $stmt = $this->db->query("SELECT * FROM equipment_categories ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCategory(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM equipment_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addCategory(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO equipment_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$data['name'], $data['description'] ?? null]);
        return (int) $this->db->lastInsertId();
    }
    
    public function updateCategory(int $id, array $data): bool {
        $stmt = $this->db->prepare("UPDATE equipment_categories SET name = ?, description = ? WHERE id = ?");
        return $stmt->execute([$data['name'], $data['description'] ?? null, $id]);
    }
    
    public function deleteCategory(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM equipment_categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ==================== EQUIPMENT ====================
    
    public function getEquipment(array $filters = []): array {
        $sql = "SELECT e.*, c.name as category_name 
                FROM equipment e 
                LEFT JOIN equipment_categories c ON e.category_id = c.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND e.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['condition'])) {
            $sql .= " AND e.condition = ?";
            $params[] = $filters['condition'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (e.name ILIKE ? OR e.serial_number ILIKE ? OR e.brand ILIKE ? OR e.model ILIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getEquipmentById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name as category_name 
            FROM equipment e 
            LEFT JOIN equipment_categories c ON e.category_id = c.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getAvailableEquipment(): array {
        $stmt = $this->db->query("
            SELECT e.*, c.name as category_name 
            FROM equipment e 
            LEFT JOIN equipment_categories c ON e.category_id = c.id 
            WHERE e.status = 'available'
            ORDER BY c.name, e.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function addEquipment(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO equipment (category_id, name, brand, model, serial_number, mac_address, 
                purchase_date, purchase_price, warranty_expiry, condition, status, location, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['category_id'] ?: null,
            $data['name'],
            $data['brand'] ?? null,
            $data['model'] ?? null,
            $data['serial_number'] ?? null,
            $data['mac_address'] ?? null,
            $data['purchase_date'] ?: null,
            $data['purchase_price'] ?: null,
            $data['warranty_expiry'] ?: null,
            $data['condition'] ?? 'new',
            $data['status'] ?? 'available',
            $data['location'] ?? null,
            $data['notes'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }
    
    public function updateEquipment(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE equipment SET 
                category_id = ?, name = ?, brand = ?, model = ?, serial_number = ?, mac_address = ?,
                purchase_date = ?, purchase_price = ?, warranty_expiry = ?, condition = ?, 
                status = ?, location = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['category_id'] ?: null,
            $data['name'],
            $data['brand'] ?? null,
            $data['model'] ?? null,
            $data['serial_number'] ?? null,
            $data['mac_address'] ?? null,
            $data['purchase_date'] ?: null,
            $data['purchase_price'] ?: null,
            $data['warranty_expiry'] ?: null,
            $data['condition'] ?? 'new',
            $data['status'] ?? 'available',
            $data['location'] ?? null,
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    public function updateEquipmentStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE equipment SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    public function deleteEquipment(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM equipment WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ==================== ASSIGNMENTS (to employees) ====================
    
    public function getAssignments(array $filters = []): array {
        $sql = "SELECT a.*, e.name as equipment_name, e.serial_number, e.brand, e.model,
                       emp.first_name, emp.last_name, u.name as assigned_by_name
                FROM equipment_assignments a
                JOIN equipment e ON a.equipment_id = e.id
                JOIN employees emp ON a.employee_id = emp.id
                LEFT JOIN users u ON a.assigned_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND a.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['active_only']) && $filters['active_only']) {
            $sql .= " AND a.status = 'assigned'";
        }
        
        $sql .= " ORDER BY a.assigned_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAssignment(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT a.*, e.name as equipment_name, e.serial_number,
                   emp.first_name, emp.last_name
            FROM equipment_assignments a
            JOIN equipment e ON a.equipment_id = e.id
            JOIN employees emp ON a.employee_id = emp.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function assignToEmployee(array $data): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO equipment_assignments (equipment_id, employee_id, assigned_date, assigned_by, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['equipment_id'],
                $data['employee_id'],
                $data['assigned_date'] ?? date('Y-m-d'),
                $data['assigned_by'] ?? null,
                $data['notes'] ?? null
            ]);
            $assignmentId = (int) $this->db->lastInsertId();
            
            $this->updateEquipmentStatus($data['equipment_id'], 'assigned');
            
            $this->db->commit();
            return $assignmentId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function returnFromEmployee(int $assignmentId, ?string $returnDate = null): bool {
        $this->db->beginTransaction();
        try {
            $assignment = $this->getAssignment($assignmentId);
            if (!$assignment) {
                throw new \Exception("Assignment not found");
            }
            
            $stmt = $this->db->prepare("
                UPDATE equipment_assignments 
                SET return_date = ?, status = 'returned' 
                WHERE id = ?
            ");
            $stmt->execute([$returnDate ?? date('Y-m-d'), $assignmentId]);
            
            $this->updateEquipmentStatus($assignment['equipment_id'], 'available');
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    // ==================== LOANS (to customers) ====================
    
    public function getLoans(array $filters = []): array {
        $sql = "SELECT l.*, e.name as equipment_name, e.serial_number, e.brand, e.model,
                       c.name as customer_name, c.phone as customer_phone,
                       u.name as loaned_by_name
                FROM equipment_loans l
                JOIN equipment e ON l.equipment_id = e.id
                JOIN customers c ON l.customer_id = c.id
                LEFT JOIN users u ON l.loaned_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND l.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['active_only']) && $filters['active_only']) {
            $sql .= " AND l.status = 'on_loan'";
        }
        
        $sql .= " ORDER BY l.loan_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getLoan(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT l.*, e.name as equipment_name, e.serial_number,
                   c.name as customer_name, c.phone as customer_phone
            FROM equipment_loans l
            JOIN equipment e ON l.equipment_id = e.id
            JOIN customers c ON l.customer_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function loanToCustomer(array $data): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO equipment_loans (equipment_id, customer_id, loan_date, expected_return_date, 
                    loaned_by, deposit_amount, deposit_paid, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['equipment_id'],
                $data['customer_id'],
                $data['loan_date'] ?? date('Y-m-d'),
                $data['expected_return_date'] ?: null,
                $data['loaned_by'] ?? null,
                $data['deposit_amount'] ?? 0,
                $data['deposit_paid'] ?? false,
                $data['notes'] ?? null
            ]);
            $loanId = (int) $this->db->lastInsertId();
            
            $this->updateEquipmentStatus($data['equipment_id'], 'on_loan');
            
            $this->db->commit();
            return $loanId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function returnFromCustomer(int $loanId, ?string $returnDate = null): bool {
        $this->db->beginTransaction();
        try {
            $loan = $this->getLoan($loanId);
            if (!$loan) {
                throw new \Exception("Loan not found");
            }
            
            $stmt = $this->db->prepare("
                UPDATE equipment_loans 
                SET actual_return_date = ?, status = 'returned' 
                WHERE id = ?
            ");
            $stmt->execute([$returnDate ?? date('Y-m-d'), $loanId]);
            
            $this->updateEquipmentStatus($loan['equipment_id'], 'available');
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    // ==================== FAULTS ====================
    
    public function getFaults(array $filters = []): array {
        $sql = "SELECT f.*, e.name as equipment_name, e.serial_number, e.brand, e.model,
                       u.name as reported_by_name
                FROM equipment_faults f
                JOIN equipment e ON f.equipment_id = e.id
                LEFT JOIN users u ON f.reported_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['equipment_id'])) {
            $sql .= " AND f.equipment_id = ?";
            $params[] = $filters['equipment_id'];
        }
        if (!empty($filters['repair_status'])) {
            $sql .= " AND f.repair_status = ?";
            $params[] = $filters['repair_status'];
        }
        if (!empty($filters['severity'])) {
            $sql .= " AND f.severity = ?";
            $params[] = $filters['severity'];
        }
        
        $sql .= " ORDER BY f.reported_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getFault(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT f.*, e.name as equipment_name, e.serial_number
            FROM equipment_faults f
            JOIN equipment e ON f.equipment_id = e.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function reportFault(array $data): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO equipment_faults (equipment_id, reported_date, reported_by, 
                    fault_description, severity, repair_status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['equipment_id'],
                $data['reported_date'] ?? date('Y-m-d'),
                $data['reported_by'] ?? null,
                $data['fault_description'],
                $data['severity'] ?? 'minor',
                'pending'
            ]);
            $faultId = (int) $this->db->lastInsertId();
            
            $this->updateEquipmentStatus($data['equipment_id'], 'faulty');
            
            $this->db->commit();
            return $faultId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function updateFault(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE equipment_faults SET 
                fault_description = ?, severity = ?, repair_status = ?,
                repair_date = ?, repair_cost = ?, repair_notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['fault_description'],
            $data['severity'] ?? 'minor',
            $data['repair_status'] ?? 'pending',
            $data['repair_date'] ?: null,
            $data['repair_cost'] ?: null,
            $data['repair_notes'] ?? null,
            $id
        ]);
    }
    
    public function markFaultRepaired(int $faultId, array $data): bool {
        $this->db->beginTransaction();
        try {
            $fault = $this->getFault($faultId);
            if (!$fault) {
                throw new \Exception("Fault not found");
            }
            
            $stmt = $this->db->prepare("
                UPDATE equipment_faults SET 
                    repair_status = 'repaired', repair_date = ?, 
                    repair_cost = ?, repair_notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['repair_date'] ?? date('Y-m-d'),
                $data['repair_cost'] ?? null,
                $data['repair_notes'] ?? null,
                $faultId
            ]);
            
            $this->updateEquipmentStatus($fault['equipment_id'], 'available');
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    // ==================== STATISTICS ====================
    
    public function getStats(): array {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment");
        $stats['total_equipment'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment WHERE status = 'available'");
        $stats['available'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment WHERE status = 'assigned'");
        $stats['assigned'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment WHERE status = 'on_loan'");
        $stats['on_loan'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment WHERE status = 'faulty'");
        $stats['faulty'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM equipment_faults WHERE repair_status = 'pending'");
        $stats['pending_repairs'] = $stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COALESCE(SUM(purchase_price), 0) FROM equipment");
        $stats['total_value'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    public function getEquipmentHistory(int $equipmentId): array {
        $history = [];
        
        $stmt = $this->db->prepare("
            SELECT 'assignment' as type, a.assigned_date as date, 
                   CONCAT(emp.first_name, ' ', emp.last_name) as to_name,
                   a.return_date, a.status, a.notes
            FROM equipment_assignments a
            JOIN employees emp ON a.employee_id = emp.id
            WHERE a.equipment_id = ?
            
            UNION ALL
            
            SELECT 'loan' as type, l.loan_date as date,
                   c.name as to_name, l.actual_return_date as return_date, 
                   l.status, l.notes
            FROM equipment_loans l
            JOIN customers c ON l.customer_id = c.id
            WHERE l.equipment_id = ?
            
            UNION ALL
            
            SELECT 'fault' as type, f.reported_date as date,
                   f.fault_description as to_name, f.repair_date as return_date,
                   f.repair_status as status, f.repair_notes as notes
            FROM equipment_faults f
            WHERE f.equipment_id = ?
            
            ORDER BY date DESC
        ");
        $stmt->execute([$equipmentId, $equipmentId, $equipmentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== IMPORT/EXPORT ====================
    
    public function bulkAddEquipment(array $items): array {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($items as $index => $item) {
            try {
                if (empty($item['name'])) {
                    $results['failed']++;
                    $results['errors'][] = "Row " . ($index + 1) . ": Name is required";
                    continue;
                }
                
                $categoryId = null;
                if (!empty($item['category'])) {
                    $categoryId = $this->getCategoryIdByName($item['category']);
                }
                
                $data = [
                    'category_id' => $categoryId,
                    'name' => trim($item['name']),
                    'brand' => trim($item['brand'] ?? ''),
                    'model' => trim($item['model'] ?? ''),
                    'serial_number' => trim($item['serial_number'] ?? ''),
                    'mac_address' => trim($item['mac_address'] ?? ''),
                    'purchase_date' => $this->parseDate($item['purchase_date'] ?? ''),
                    'purchase_price' => $this->parseNumber($item['purchase_price'] ?? ''),
                    'warranty_expiry' => $this->parseDate($item['warranty_expiry'] ?? ''),
                    'condition' => $this->validateCondition($item['condition'] ?? 'new'),
                    'status' => $this->validateStatus($item['status'] ?? 'available'),
                    'location' => trim($item['location'] ?? ''),
                    'notes' => trim($item['notes'] ?? '')
                ];
                
                $this->addEquipment($data);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    public function importFromExcel(string $filePath): array {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        } catch (\Exception $e) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Invalid or corrupted file. Please ensure the file is a valid Excel (.xlsx, .xls) or CSV file.']];
        }
        
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (count($rows) < 2) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['File is empty or has no data rows. The file must have a header row and at least one data row.']];
        }
        
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        
        if (empty(array_filter($headers))) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['No column headers found in the first row. Please ensure the file has column headers.']];
        }
        
        $headerMap = [
            'name' => ['name', 'equipment name', 'item name', 'item'],
            'category' => ['category', 'type', 'equipment type'],
            'brand' => ['brand', 'manufacturer', 'make'],
            'model' => ['model', 'model number', 'model no'],
            'serial_number' => ['serial number', 'serial', 'serial no', 's/n', 'sn'],
            'mac_address' => ['mac address', 'mac', 'mac id'],
            'purchase_date' => ['purchase date', 'date purchased', 'bought on'],
            'purchase_price' => ['purchase price', 'price', 'cost', 'amount'],
            'warranty_expiry' => ['warranty expiry', 'warranty', 'warranty date', 'warranty end'],
            'condition' => ['condition', 'state'],
            'status' => ['status', 'availability'],
            'location' => ['location', 'place', 'stored at'],
            'notes' => ['notes', 'remarks', 'comments', 'description']
        ];
        
        $columnMap = [];
        foreach ($headerMap as $field => $aliases) {
            foreach ($headers as $colIndex => $header) {
                if (in_array($header, $aliases)) {
                    $columnMap[$field] = $colIndex;
                    break;
                }
            }
        }
        
        if (!isset($columnMap['name'])) {
            $foundHeaders = implode(', ', array_filter($headers));
            return ['success' => 0, 'failed' => 0, 'errors' => ["Could not find 'Name' column. Found columns: {$foundHeaders}. Please ensure your file has a column named 'Name', 'Equipment Name', 'Item Name', or 'Item'."]];
        }
        
        $items = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;
            
            $item = [];
            foreach ($columnMap as $field => $colIndex) {
                $item[$field] = $row[$colIndex] ?? '';
            }
            $items[] = $item;
        }
        
        return $this->bulkAddEquipment($items);
    }
    
    public function generateImportTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Equipment Import');
        
        $headers = ['Name', 'Category', 'Brand', 'Model', 'Serial Number', 'MAC Address', 
                    'Purchase Date', 'Purchase Price', 'Warranty Expiry', 'Condition', 
                    'Status', 'Location', 'Notes'];
        
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }
        
        $categories = $this->getCategories();
        $categoryNames = array_map(fn($c) => $c['name'], $categories);
        
        $sheet->setCellValue('A2', 'Fiber Router');
        $sheet->setCellValue('B2', count($categoryNames) > 0 ? $categoryNames[0] : 'Router');
        $sheet->setCellValue('C2', 'TP-Link');
        $sheet->setCellValue('D2', 'AX1800');
        $sheet->setCellValue('E2', 'SN123456789');
        $sheet->setCellValue('F2', 'AA:BB:CC:DD:EE:FF');
        $sheet->setCellValue('G2', date('Y-m-d'));
        $sheet->setCellValue('H2', '150.00');
        $sheet->setCellValue('I2', date('Y-m-d', strtotime('+1 year')));
        $sheet->setCellValue('J2', 'new');
        $sheet->setCellValue('K2', 'available');
        $sheet->setCellValue('L2', 'Main Warehouse');
        $sheet->setCellValue('M2', 'Sample equipment entry');
        
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instructions');
        $instructionSheet->setCellValue('A1', 'Import Instructions');
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        
        $instructions = [
            'A3' => 'Required Fields:',
            'A4' => '- Name: Equipment name (required)',
            'A6' => 'Optional Fields:',
            'A7' => '- Category: Must match existing category name',
            'A8' => '- Brand, Model: Manufacturer details',
            'A9' => '- Serial Number, MAC Address: Unique identifiers',
            'A10' => '- Purchase Date: YYYY-MM-DD format',
            'A11' => '- Purchase Price: Numeric value',
            'A12' => '- Warranty Expiry: YYYY-MM-DD format',
            'A13' => '- Condition: new, good, fair, poor',
            'A14' => '- Status: available, assigned, loaned, maintenance, faulty, retired',
            'A15' => '- Location: Storage location',
            'A16' => '- Notes: Additional information',
            'A18' => 'Available Categories:',
        ];
        
        foreach ($instructions as $cell => $text) {
            $instructionSheet->setCellValue($cell, $text);
        }
        
        $row = 19;
        foreach ($categoryNames as $catName) {
            $instructionSheet->setCellValue('A' . $row++, '- ' . $catName);
        }
        
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }
    
    public function exportEquipment(array $filters = []): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $equipment = $this->getEquipment($filters);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Equipment Export');
        
        $headers = ['ID', 'Name', 'Category', 'Brand', 'Model', 'Serial Number', 'MAC Address',
                    'Purchase Date', 'Purchase Price', 'Warranty Expiry', 'Condition', 
                    'Status', 'Location', 'Notes', 'Created At'];
        
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
        
        $row = 2;
        foreach ($equipment as $item) {
            $sheet->setCellValue('A' . $row, $item['id']);
            $sheet->setCellValue('B' . $row, $item['name']);
            $sheet->setCellValue('C' . $row, $item['category_name'] ?? '');
            $sheet->setCellValue('D' . $row, $item['brand']);
            $sheet->setCellValue('E' . $row, $item['model']);
            $sheet->setCellValue('F' . $row, $item['serial_number']);
            $sheet->setCellValue('G' . $row, $item['mac_address']);
            $sheet->setCellValue('H' . $row, $item['purchase_date']);
            $sheet->setCellValue('I' . $row, $item['purchase_price']);
            $sheet->setCellValue('J' . $row, $item['warranty_expiry']);
            $sheet->setCellValue('K' . $row, $item['condition']);
            $sheet->setCellValue('L' . $row, $item['status']);
            $sheet->setCellValue('M' . $row, $item['location']);
            $sheet->setCellValue('N' . $row, $item['notes']);
            $sheet->setCellValue('O' . $row, $item['created_at']);
            $row++;
        }
        
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        return $spreadsheet;
    }
    
    private function getCategoryIdByName(string $name): ?int {
        $stmt = $this->db->prepare("SELECT id FROM equipment_categories WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([trim($name)]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }
    
    private function parseDate(string $value): ?string {
        if (empty($value)) return null;
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
    
    private function parseNumber(string $value): ?float {
        if (empty($value)) return null;
        $clean = preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($clean) ? (float)$clean : null;
    }
    
    private function validateCondition(string $value): string {
        $valid = ['new', 'good', 'fair', 'poor'];
        $lower = strtolower(trim($value));
        return in_array($lower, $valid) ? $lower : 'new';
    }
    
    private function validateStatus(string $value): string {
        $valid = ['available', 'assigned', 'loaned', 'maintenance', 'faulty', 'retired'];
        $lower = strtolower(trim($value));
        return in_array($lower, $valid) ? $lower : 'available';
    }
}
