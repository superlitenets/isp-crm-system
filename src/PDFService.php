<?php
namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFService {
    private $dompdf;
    private $settings;
    
    public function __construct($db = null) {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $this->dompdf = new Dompdf($options);
        
        if ($db) {
            $this->settings = new Settings($db);
        }
    }
    
    public function generateInvoicePDF(array $invoice): string {
        $html = $this->getInvoiceHTML($invoice);
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        return $this->dompdf->output();
    }
    
    public function generateQuotePDF(array $quote): string {
        $html = $this->getQuoteHTML($quote);
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        return $this->dompdf->output();
    }
    
    private function getCompanyInfo(): array {
        return [
            'name' => $this->settings ? $this->settings->get('company_name', 'Your Company') : 'Your Company',
            'address' => $this->settings ? $this->settings->get('company_address', '') : '',
            'phone' => $this->settings ? $this->settings->get('company_phone', '') : '',
            'email' => $this->settings ? $this->settings->get('company_email', '') : '',
            'logo' => $this->settings ? $this->settings->get('company_logo', '') : '',
        ];
    }
    
    private function getDocSettings(): array {
        return [
            'currency' => $this->settings ? $this->settings->get('currency_symbol', 'KES') : 'KES',
            'invoice_footer' => $this->settings ? $this->settings->get('invoice_footer_text', 'Thank you for your business!') : 'Thank you for your business!',
            'quote_footer' => $this->settings ? $this->settings->get('quote_footer_text', 'Thank you for considering our services!') : 'Thank you for considering our services!',
            'show_payment_info' => $this->settings ? $this->settings->get('show_payment_info_on_invoice', '1') === '1' : false,
            'bank_name' => $this->settings ? $this->settings->get('payment_bank_name', '') : '',
            'bank_account' => $this->settings ? $this->settings->get('payment_bank_account', '') : '',
            'bank_branch' => $this->settings ? $this->settings->get('payment_bank_branch', '') : '',
            'mpesa_paybill' => $this->settings ? $this->settings->get('payment_mpesa_paybill', '') : '',
            'mpesa_account' => $this->settings ? $this->settings->get('payment_mpesa_account_name', '') : '',
        ];
    }
    
    private function getInvoiceHTML(array $invoice): string {
        $company = $this->getCompanyInfo();
        $docSettings = $this->getDocSettings();
        $currency = $docSettings['currency'];
        $items = $invoice['items'] ?? [];
        $payments = $invoice['payments'] ?? [];
        
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['description'] ?? '') . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: center;">' . number_format($item['quantity'] ?? 0, 0) . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: right;">' . $currency . ' ' . number_format($item['unit_price'] ?? 0, 2) . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: right;">' . $currency . ' ' . number_format($item['line_total'] ?? 0, 2) . '</td>
            </tr>';
        }
        
        $paymentInfoHtml = '';
        if ($docSettings['show_payment_info'] && (!empty($docSettings['bank_name']) || !empty($docSettings['mpesa_paybill']))) {
            $paymentInfoHtml = '<div class="payment-info-section" style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 10px; border: 1px solid #e5e7eb;">';
            $paymentInfoHtml .= '<h3 style="font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 15px; font-weight: 600;">Payment Methods</h3>';
            $paymentInfoHtml .= '<div style="display: table; width: 100%;">';
            
            if (!empty($docSettings['bank_name'])) {
                $paymentInfoHtml .= '<div style="display: table-cell; width: 50%; vertical-align: top; padding-right: 15px;">';
                $paymentInfoHtml .= '<p style="font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px;">Bank Transfer</p>';
                $paymentInfoHtml .= '<p style="font-size: 11px; color: #6b7280; margin: 2px 0;">Bank: ' . htmlspecialchars($docSettings['bank_name']) . '</p>';
                if (!empty($docSettings['bank_account'])) {
                    $paymentInfoHtml .= '<p style="font-size: 11px; color: #6b7280; margin: 2px 0;">Account: ' . htmlspecialchars($docSettings['bank_account']) . '</p>';
                }
                if (!empty($docSettings['bank_branch'])) {
                    $paymentInfoHtml .= '<p style="font-size: 11px; color: #6b7280; margin: 2px 0;">Branch: ' . htmlspecialchars($docSettings['bank_branch']) . '</p>';
                }
                $paymentInfoHtml .= '</div>';
            }
            
            if (!empty($docSettings['mpesa_paybill'])) {
                $paymentInfoHtml .= '<div style="display: table-cell; width: 50%; vertical-align: top;">';
                $paymentInfoHtml .= '<p style="font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px;">M-Pesa</p>';
                $paymentInfoHtml .= '<p style="font-size: 11px; color: #6b7280; margin: 2px 0;">Paybill/Till: ' . htmlspecialchars($docSettings['mpesa_paybill']) . '</p>';
                if (!empty($docSettings['mpesa_account'])) {
                    $paymentInfoHtml .= '<p style="font-size: 11px; color: #6b7280; margin: 2px 0;">Account: ' . htmlspecialchars($docSettings['mpesa_account']) . '</p>';
                }
                $paymentInfoHtml .= '</div>';
            }
            
            $paymentInfoHtml .= '</div></div>';
        }
        
        $statusColor = match($invoice['status'] ?? 'draft') {
            'paid' => '#10b981',
            'sent' => '#3b82f6',
            'partial' => '#f59e0b',
            'overdue' => '#ef4444',
            default => '#6b7280'
        };
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5; background: #fff; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 28px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
        .company-info { font-size: 11px; color: #6b7280; line-height: 1.6; }
        .invoice-title { font-size: 32px; font-weight: bold; color: #1e40af; letter-spacing: -1px; }
        .invoice-number { font-size: 14px; color: #6b7280; margin-top: 5px; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; text-transform: uppercase; letter-spacing: 0.5px; background: ' . $statusColor . '; }
        .info-section { display: table; width: 100%; margin-bottom: 30px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; }
        .info-box h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 8px; font-weight: 600; }
        .info-box p { font-size: 13px; color: #374151; margin-bottom: 3px; }
        .info-box .name { font-size: 16px; font-weight: 600; color: #111827; }
        .dates-section { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 10px; padding: 20px; margin-bottom: 30px; }
        .dates-table { width: 100%; }
        .dates-table td { padding: 5px 0; font-size: 13px; }
        .dates-table .label { color: #6b7280; width: 50%; }
        .dates-table .value { color: #111827; font-weight: 500; text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items thead { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
        table.items th { padding: 14px 15px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: white; font-weight: 600; }
        table.items th:nth-child(2), table.items th:nth-child(3), table.items th:nth-child(4) { text-align: right; }
        table.items th:nth-child(2) { text-align: center; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
        .totals-section { display: table; width: 100%; }
        .totals-left { display: table-cell; width: 50%; vertical-align: top; }
        .totals-right { display: table-cell; width: 50%; vertical-align: top; }
        .totals-box { background: #f8fafc; border-radius: 10px; padding: 20px; margin-left: auto; width: 280px; }
        .totals-row { display: table; width: 100%; margin-bottom: 8px; }
        .totals-label { display: table-cell; font-size: 13px; color: #6b7280; }
        .totals-value { display: table-cell; text-align: right; font-size: 13px; color: #374151; }
        .totals-row.total { border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 12px; }
        .totals-row.total .totals-label, .totals-row.total .totals-value { font-size: 18px; font-weight: bold; color: #1e40af; }
        .totals-row.balance { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); margin: 15px -20px -20px; padding: 15px 20px; border-radius: 0 0 10px 10px; }
        .totals-row.balance .totals-label, .totals-row.balance .totals-value { color: white; font-size: 16px; font-weight: bold; }
        .notes-section { margin-top: 40px; padding: 20px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; }
        .notes-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #92400e; margin-bottom: 8px; font-weight: 600; }
        .notes-content { font-size: 12px; color: #78350f; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; }
        .footer p { font-size: 11px; color: #9ca3af; }
        .footer .thank-you { font-size: 16px; color: #1e40af; font-weight: 600; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="company-name">' . htmlspecialchars($company['name']) . '</div>
                <div class="company-info">
                    ' . ($company['address'] ? htmlspecialchars($company['address']) . '<br>' : '') . '
                    ' . ($company['phone'] ? 'Tel: ' . htmlspecialchars($company['phone']) . '<br>' : '') . '
                    ' . ($company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '') . '
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">#' . htmlspecialchars($invoice['invoice_number'] ?? '') . '</div>
                <div style="margin-top: 15px;"><span class="status-badge">' . strtoupper($invoice['status'] ?? 'draft') . '</span></div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Bill To</h3>
                <p class="name">' . htmlspecialchars($invoice['customer_name'] ?? 'N/A') . '</p>
                <p>' . htmlspecialchars($invoice['customer_email'] ?? '') . '</p>
                <p>' . htmlspecialchars($invoice['customer_phone'] ?? '') . '</p>
            </div>
            <div class="info-box" style="text-align: right;">
                <div class="dates-section" style="display: inline-block; text-align: left; min-width: 200px;">
                    <table class="dates-table">
                        <tr><td class="label">Issue Date:</td><td class="value">' . date('M d, Y', strtotime($invoice['issue_date'] ?? 'now')) . '</td></tr>
                        <tr><td class="label">Due Date:</td><td class="value">' . date('M d, Y', strtotime($invoice['due_date'] ?? 'now')) . '</td></tr>
                    </table>
                </div>
            </div>
        </div>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="border-radius: 8px 0 0 0;">Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th style="border-radius: 0 8px 0 0;">Amount</th>
                </tr>
            </thead>
            <tbody>
                ' . $itemsHtml . '
            </tbody>
        </table>
        
        <div class="totals-section">
            <div class="totals-left"></div>
            <div class="totals-right">
                <div class="totals-box">
                    <div class="totals-row">
                        <span class="totals-label">Subtotal</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($invoice['subtotal'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label">Tax</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($invoice['tax_amount'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row total">
                        <span class="totals-label">Total</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($invoice['total_amount'] ?? $invoice['total'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label">Amount Paid</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($invoice['amount_paid'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row balance">
                        <span class="totals-label">Balance Due</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($invoice['balance_due'] ?? 0, 2) . '</span>
                    </div>
                </div>
            </div>
        </div>
        
        ' . $paymentInfoHtml . '
        
        ' . (!empty($invoice['notes']) ? '
        <div class="notes-section">
            <div class="notes-title">Notes</div>
            <div class="notes-content">' . nl2br(htmlspecialchars($invoice['notes'])) . '</div>
        </div>' : '') . '
        
        <div class="footer">
            <p class="thank-you">' . htmlspecialchars($docSettings['invoice_footer']) . '</p>
            <p>Generated on ' . date('M d, Y \a\t h:i A') . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    private function getQuoteHTML(array $quote): string {
        $company = $this->getCompanyInfo();
        $docSettings = $this->getDocSettings();
        $currency = $docSettings['currency'];
        $items = $quote['items'] ?? [];
        
        $itemsHtml = '';
        foreach ($items as $item) {
            $itemsHtml .= '<tr>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item['description'] ?? '') . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: center;">' . number_format($item['quantity'] ?? 0, 0) . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: right;">' . $currency . ' ' . number_format($item['unit_price'] ?? 0, 2) . '</td>
                <td style="padding: 12px 15px; border-bottom: 1px solid #eee; text-align: right;">' . $currency . ' ' . number_format($item['line_total'] ?? 0, 2) . '</td>
            </tr>';
        }
        
        $statusColor = match($quote['status'] ?? 'draft') {
            'accepted' => '#10b981',
            'sent' => '#3b82f6',
            'declined' => '#ef4444',
            'expired' => '#f59e0b',
            'converted' => '#8b5cf6',
            default => '#6b7280'
        };
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5; background: #fff; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 28px; font-weight: bold; color: #059669; margin-bottom: 5px; }
        .company-info { font-size: 11px; color: #6b7280; line-height: 1.6; }
        .quote-title { font-size: 32px; font-weight: bold; color: #059669; letter-spacing: -1px; }
        .quote-number { font-size: 14px; color: #6b7280; margin-top: 5px; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; text-transform: uppercase; letter-spacing: 0.5px; background: ' . $statusColor . '; }
        .info-section { display: table; width: 100%; margin-bottom: 30px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; }
        .info-box h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 8px; font-weight: 600; }
        .info-box p { font-size: 13px; color: #374151; margin-bottom: 3px; }
        .info-box .name { font-size: 16px; font-weight: 600; color: #111827; }
        .validity-banner { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #10b981; border-radius: 10px; padding: 15px 20px; margin-bottom: 30px; text-align: center; }
        .validity-text { font-size: 13px; color: #065f46; }
        .validity-date { font-size: 18px; font-weight: bold; color: #059669; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items thead { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        table.items th { padding: 14px 15px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: white; font-weight: 600; }
        table.items th:nth-child(2), table.items th:nth-child(3), table.items th:nth-child(4) { text-align: right; }
        table.items th:nth-child(2) { text-align: center; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
        .totals-section { display: table; width: 100%; }
        .totals-left { display: table-cell; width: 50%; vertical-align: top; }
        .totals-right { display: table-cell; width: 50%; vertical-align: top; }
        .totals-box { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #86efac; border-radius: 10px; padding: 20px; margin-left: auto; width: 280px; }
        .totals-row { display: table; width: 100%; margin-bottom: 8px; }
        .totals-label { display: table-cell; font-size: 13px; color: #6b7280; }
        .totals-value { display: table-cell; text-align: right; font-size: 13px; color: #374151; }
        .totals-row.total { border-top: 2px solid #86efac; padding-top: 12px; margin-top: 12px; }
        .totals-row.total .totals-label, .totals-row.total .totals-value { font-size: 20px; font-weight: bold; color: #059669; }
        .notes-section { margin-top: 30px; padding: 20px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 0 8px 8px 0; }
        .notes-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #065f46; margin-bottom: 8px; font-weight: 600; }
        .notes-content { font-size: 12px; color: #166534; }
        .terms-section { margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px; }
        .terms-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; font-weight: 600; }
        .terms-content { font-size: 11px; color: #475569; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; }
        .footer p { font-size: 11px; color: #9ca3af; }
        .footer .thank-you { font-size: 16px; color: #059669; font-weight: 600; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="company-name">' . htmlspecialchars($company['name']) . '</div>
                <div class="company-info">
                    ' . ($company['address'] ? htmlspecialchars($company['address']) . '<br>' : '') . '
                    ' . ($company['phone'] ? 'Tel: ' . htmlspecialchars($company['phone']) . '<br>' : '') . '
                    ' . ($company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '') . '
                </div>
            </div>
            <div class="header-right">
                <div class="quote-title">QUOTATION</div>
                <div class="quote-number">#' . htmlspecialchars($quote['quote_number'] ?? '') . '</div>
                <div style="margin-top: 15px;"><span class="status-badge">' . strtoupper($quote['status'] ?? 'draft') . '</span></div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Prepared For</h3>
                <p class="name">' . htmlspecialchars($quote['customer_name'] ?? 'N/A') . '</p>
                <p>' . htmlspecialchars($quote['customer_email'] ?? '') . '</p>
                <p>' . htmlspecialchars($quote['customer_phone'] ?? '') . '</p>
            </div>
            <div class="info-box" style="text-align: right;">
                <h3>Quote Details</h3>
                <p><strong>Issue Date:</strong> ' . date('M d, Y', strtotime($quote['issue_date'] ?? 'now')) . '</p>
                <p><strong>Valid Until:</strong> ' . date('M d, Y', strtotime($quote['expiry_date'] ?? 'now')) . '</p>
            </div>
        </div>
        
        <div class="validity-banner">
            <div class="validity-text">This quotation is valid until</div>
            <div class="validity-date">' . date('F d, Y', strtotime($quote['expiry_date'] ?? 'now')) . '</div>
        </div>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="border-radius: 8px 0 0 0;">Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th style="border-radius: 0 8px 0 0;">Amount</th>
                </tr>
            </thead>
            <tbody>
                ' . $itemsHtml . '
            </tbody>
        </table>
        
        <div class="totals-section">
            <div class="totals-left"></div>
            <div class="totals-right">
                <div class="totals-box">
                    <div class="totals-row">
                        <span class="totals-label">Subtotal</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($quote['subtotal'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label">Tax</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($quote['tax_amount'] ?? 0, 2) . '</span>
                    </div>
                    <div class="totals-row total">
                        <span class="totals-label">Total</span>
                        <span class="totals-value">' . $currency . ' ' . number_format($quote['total'] ?? $quote['total_amount'] ?? 0, 2) . '</span>
                    </div>
                </div>
            </div>
        </div>
        
        ' . (!empty($quote['notes']) ? '
        <div class="notes-section">
            <div class="notes-title">Notes</div>
            <div class="notes-content">' . nl2br(htmlspecialchars($quote['notes'])) . '</div>
        </div>' : '') . '
        
        ' . (!empty($quote['terms']) ? '
        <div class="terms-section">
            <div class="terms-title">Terms & Conditions</div>
            <div class="terms-content">' . nl2br(htmlspecialchars($quote['terms'])) . '</div>
        </div>' : '') . '
        
        <div class="footer">
            <p class="thank-you">' . htmlspecialchars($docSettings['quote_footer']) . '</p>
            <p>Generated on ' . date('M d, Y \a\t h:i A') . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    public function generateReceiptPDF(array $payment): string {
        $html = $this->getReceiptHTML($payment);
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        return $this->dompdf->output();
    }
    
    private function getReceiptHTML(array $payment): string {
        $company = $this->getCompanyInfo();
        $docSettings = $this->getDocSettings();
        $currency = $docSettings['currency'];
        
        $paymentMethodLabel = match($payment['payment_method'] ?? 'cash') {
            'mpesa' => 'M-Pesa',
            'bank' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit_card' => 'Credit Card',
            default => 'Cash'
        };
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5; background: #fff; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 40px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 28px; font-weight: bold; color: #059669; margin-bottom: 5px; }
        .company-info { font-size: 11px; color: #6b7280; line-height: 1.6; }
        .receipt-title { font-size: 32px; font-weight: bold; color: #10b981; letter-spacing: -1px; }
        .receipt-number { font-size: 14px; color: #6b7280; margin-top: 5px; }
        .success-badge { display: inline-block; padding: 8px 20px; border-radius: 25px; font-size: 14px; font-weight: bold; color: white; text-transform: uppercase; letter-spacing: 1px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); margin-top: 10px; }
        .info-section { display: table; width: 100%; margin-bottom: 30px; }
        .info-box { display: table-cell; width: 50%; vertical-align: top; }
        .info-box h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 8px; font-weight: 600; }
        .info-box p { font-size: 13px; color: #374151; margin-bottom: 3px; }
        .info-box .name { font-size: 16px; font-weight: 600; color: #111827; }
        .payment-details { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #10b981; border-radius: 15px; padding: 30px; margin: 30px 0; }
        .payment-details-title { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #065f46; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .payment-row { display: table; width: 100%; margin-bottom: 12px; }
        .payment-label { display: table-cell; width: 40%; font-size: 13px; color: #6b7280; }
        .payment-value { display: table-cell; width: 60%; font-size: 14px; color: #374151; font-weight: 500; }
        .amount-row { border-top: 2px solid #10b981; padding-top: 20px; margin-top: 20px; }
        .amount-label { font-size: 16px; font-weight: bold; color: #065f46; }
        .amount-value { font-size: 28px; font-weight: bold; color: #059669; }
        .invoice-ref { background: #f8fafc; border-radius: 10px; padding: 20px; margin-top: 20px; }
        .invoice-ref-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 10px; font-weight: 600; }
        .invoice-ref-content { font-size: 13px; color: #475569; }
        .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; }
        .footer p { font-size: 11px; color: #9ca3af; }
        .footer .thank-you { font-size: 18px; color: #10b981; font-weight: 600; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="company-name">' . htmlspecialchars($company['name']) . '</div>
                <div class="company-info">
                    ' . ($company['address'] ? htmlspecialchars($company['address']) . '<br>' : '') . '
                    ' . ($company['phone'] ? 'Tel: ' . htmlspecialchars($company['phone']) . '<br>' : '') . '
                    ' . ($company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '') . '
                </div>
            </div>
            <div class="header-right">
                <div class="receipt-title">RECEIPT</div>
                <div class="receipt-number">#' . htmlspecialchars($payment['receipt_number'] ?? 'REC-' . str_pad($payment['id'] ?? 0, 6, '0', STR_PAD_LEFT)) . '</div>
                <div class="success-badge">PAID</div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>Received From</h3>
                <p class="name">' . htmlspecialchars($payment['customer_name'] ?? 'Customer') . '</p>
                ' . (!empty($payment['customer_email']) ? '<p>' . htmlspecialchars($payment['customer_email']) . '</p>' : '') . '
                ' . (!empty($payment['customer_phone']) ? '<p>' . htmlspecialchars($payment['customer_phone']) . '</p>' : '') . '
            </div>
            <div class="info-box" style="text-align: right;">
                <h3>Receipt Details</h3>
                <table style="width: 100%; font-size: 12px;">
                    <tr><td style="text-align: right; color: #6b7280;">Date:</td><td style="text-align: right; padding-left: 10px;">' . date('M d, Y', strtotime($payment['payment_date'] ?? 'now')) . '</td></tr>
                </table>
            </div>
        </div>
        
        <div class="payment-details">
            <div class="payment-details-title">Payment Details</div>
            <div class="payment-row">
                <span class="payment-label">Payment Method</span>
                <span class="payment-value">' . htmlspecialchars($paymentMethodLabel) . '</span>
            </div>
            ' . (!empty($payment['reference']) ? '
            <div class="payment-row">
                <span class="payment-label">Reference Number</span>
                <span class="payment-value">' . htmlspecialchars($payment['reference']) . '</span>
            </div>' : '') . '
            <div class="payment-row amount-row">
                <span class="payment-label amount-label">Amount Received</span>
                <span class="payment-value amount-value">' . $currency . ' ' . number_format($payment['amount'] ?? 0, 2) . '</span>
            </div>
        </div>
        
        ' . (!empty($payment['invoice_number']) ? '
        <div class="invoice-ref">
            <div class="invoice-ref-title">For Invoice</div>
            <div class="invoice-ref-content">
                <strong>' . htmlspecialchars($payment['invoice_number']) . '</strong>
                ' . (!empty($payment['invoice_total']) ? ' - Total: ' . $currency . ' ' . number_format($payment['invoice_total'], 2) : '') . '
            </div>
        </div>' : '') . '
        
        ' . (!empty($payment['notes']) ? '
        <div style="margin-top: 20px; padding: 15px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 0 8px 8px 0;">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #065f46; margin-bottom: 8px; font-weight: 600;">Notes</div>
            <div style="font-size: 12px; color: #166534;">' . nl2br(htmlspecialchars($payment['notes'])) . '</div>
        </div>' : '') . '
        
        <div class="footer">
            <p class="thank-you">Thank you for your payment!</p>
            <p>This receipt confirms payment has been received.</p>
            <p>Generated on ' . date('M d, Y \a\t h:i A') . '</p>
        </div>
    </div>
</body>
</html>';
    }
}
