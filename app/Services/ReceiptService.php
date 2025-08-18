<?php

namespace App\Services;

use App\Models\Sale;

class ReceiptService
{
    public function generateReceipt($sale)
    {
        $receiptContent = $this->generateThermalReceipt($sale);
        
        $fileName = 'receipt_' . $sale['invoice_number'] . '.txt';
        $filePath = storage_path('app/receipts/' . $fileName);
        
        // Create receipts directory if it doesn't exist
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        
        file_put_contents($filePath, $receiptContent);
        
        return $filePath;
    }

   private function generateThermalReceipt($sale)
    {
        $width = 32; // Standard thermal printer width
        $receipt = "";
        
        // Header
        $receipt .= $this->centerText("HT TECH", $width) . "\n";
        $receipt .= $this->centerText("Computer & Electronics", $width) . "\n";
        $receipt .= $this->centerText("Karachi, Pakistan", $width) . "\n";
        $receipt .= str_repeat("=", $width) . "\n";
        
        // Invoice details
        $receipt .= "Invoice: " . $sale['invoice_number'] . "\n";
        $receipt .= "Date: " . date('d/m/Y H:i', strtotime($sale['created_at'])) . "\n";

        if (!empty($sale['customer_name'])) {
            $receipt .= "Customer: " . $sale['customer_name'] . "\n";
        }
        if (!empty($sale['customer_phone'])) {
            $receipt .= "Phone: " . $sale['customer_phone'] . "\n";
        }
        $receipt .= str_repeat("-", $width) . "\n";
        
        // Items
        $receipt .= "Item" . str_repeat(" ", $width-14) . "Qty Price\n";
        $receipt .= str_repeat("-", $width) . "\n";
        
        foreach ($sale['items'] as $item) {
            $name  = $this->truncateText($item['name'], $width - 12);
            $qty   = $item['quantity'];
            $price = number_format($item['price'], 0);
            $total = number_format($item['quantity'] * $item['price'], 0);
            
            $receipt .= $name . "\n";
            $receipt .= str_repeat(" ", $width - strlen($qty . " x " . $price . " = " . $total)) . 
                    $qty . " x " . $price . " = " . $total . "\n";
        }
        
        $receipt .= str_repeat("-", $width) . "\n";
        
        // Totals
        $subtotal = $sale['total_amount'] + $sale['discount_amount'] - $sale['tax_amount'];
        $receipt .= "Subtotal:" . str_repeat(" ", $width - 9 - strlen(number_format($subtotal, 0))) . 
                number_format($subtotal, 0) . "\n";
        
        if ($sale['discount_amount'] > 0) {
            $receipt .= "Discount:" . str_repeat(" ", $width - 9 - strlen(number_format($sale['discount_amount'], 0))) . 
                    number_format($sale['discount_amount'], 0) . "\n";
        }
        
        $receipt .= "Tax (16%):" . str_repeat(" ", $width - 10 - strlen(number_format($sale['tax_amount'], 0))) . 
                number_format($sale['tax_amount'], 0) . "\n";
        
        $receipt .= str_repeat("=", $width) . "\n";
        $receipt .= "TOTAL:" . str_repeat(" ", $width - 6 - strlen(number_format($sale['total_amount'], 0))) . 
                number_format($sale['total_amount'], 0) . "\n";
        $receipt .= str_repeat("=", $width) . "\n";
        
        // Payment method
        $receipt .= "Payment: " . ucfirst($sale['payment_method']) . "\n";
        $receipt .= str_repeat("-", $width) . "\n";
        
        // Footer
        $receipt .= $this->centerText("Thank you for shopping!", $width) . "\n";
        $receipt .= $this->centerText("Visit us again!", $width) . "\n";
        $receipt .= "\n\n\n"; // Feed paper
        
        
        return $receipt;
    }


    private function centerText($text, $width)
    {
        $len = strlen($text);
        if ($len >= $width) return $text;
        
        $spaces = ($width - $len) / 2;
        return str_repeat(" ", floor($spaces)) . $text . str_repeat(" ", ceil($spaces));
    }

    private function truncateText($text, $maxLength)
    {
        return strlen($text) > $maxLength ? substr($text, 0, $maxLength - 3) . "..." : $text;
    }
}