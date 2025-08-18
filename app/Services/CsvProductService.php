<?php

namespace App\Services;

use App\Models\Product;
use League\Csv\Reader;
use League\Csv\Writer;
class CsvProductService
{
    private $csvPath;
     private $salesCsvPath;
    private $salesItemsCsvPath;

    public function __construct()
    {
        $this->csvPath = storage_path('app/products.csv');

         $this->salesCsvPath = storage_path('app/sales.csv');
        $this->salesItemsCsvPath = storage_path('app/sales_items.csv');
        
        $this->initializeCsvFiles();
    }

public function loadProductsFromCsv()
{
    if (!file_exists($this->csvPath)) {
        throw new \Exception('CSV file not found');
    }

    $csv = \League\Csv\Reader::createFromPath($this->csvPath, 'r');
    $csv->setHeaderOffset(null); // don't set yet

    // Get all rows
    $records = iterator_to_array($csv->getRecords());

    // Remove leading empty rows
    while (!empty($records) && empty(array_filter(reset($records), fn($v) => trim($v) !== ''))) {
        array_shift($records); // drop the first row if it's empty
    }

    if (empty($records)) {
        throw new \Exception("CSV file has no valid header/data");
    }

    // First row is now header
    $header = array_map('trim', array_shift($records));

    $products = [];
    foreach ($records as $row) {
        // Skip completely empty rows
        if (empty(array_filter($row, fn($v) => trim($v) !== ''))) {
            continue;
        }

        $record = array_combine($header, $row);

        $products[] = [
            'name' => trim($record['Name'] ?? ''),
            'model' => trim($record['Model'] ?? ''),
            'specifications' => trim($record['Specifications'] ?? ''),
            'purchase_price' => floatval($record['Purchase Price'] ?? 0),
            'selling_price' => floatval($record['Selling Price'] ?? 0),
            'warranty_period' => intval($record['Warranty Period'] ?? 0),
            'warranty_type' => trim($record['Warranty Type'] ?? 'none'),
            'quantity' => intval($record['quantity'] ?? 0),
            'categories' => trim($record['Categories'] ?? ''),
            'brands' => trim($record['Brands'] ?? ''),
            'description' => trim(strip_tags($record['Description'] ?? '')),
            'weight' => floatval($record['Weight'] ?? 0),
            'supplier_invoice_no' => trim($record['Supplier Invoice No'] ?? ''),
        ];
    }

    return $products;
}

    public function searchProducts($query)
    {
        $products = $this->loadProductsFromCsv();
        
        if (empty($query)) {
            return $products;
        }

        return array_filter($products, function($product) use ($query) {
            return stripos($product['name'], $query) !== false ||
                   stripos($product['model'], $query) !== false ||
                   stripos($product['categories'], $query) !== false ||
                   stripos($product['brands'], $query) !== false;
        });
    }

     private function initializeCsvFiles()
    {
        // Initialize sales.csv if it doesn't exist
        if (!file_exists($this->salesCsvPath)) {
            $writer = Writer::createFromPath($this->salesCsvPath, 'w+');
            $writer->insertOne([
                'invoice_number',
                'customer_name',
                'customer_phone',
                'subtotal',
                'discount_amount',
                'tax_amount',
                'total_amount',
                'payment_method',
                'sale_date',
                'sale_time',
                'created_at',
                'note'
            ]);
        }

        // Initialize sales_items.csv if it doesn't exist
        if (!file_exists($this->salesItemsCsvPath)) {
            $writer = Writer::createFromPath($this->salesItemsCsvPath, 'w+');
            $writer->insertOne([
                'invoice_number',
                'item_name',
                'item_model',
                'quantity',
                'unit_price',
                'total_price',
                'created_at'
            ]);
        }
    }

    public function saveSale($saleData)
    {
        try {
            // Save main sale record
            $writer = Writer::createFromPath($this->salesCsvPath, 'a+');
            $saleDate = date('Y-m-d', strtotime($saleData['created_at']));
            $saleTime = date('H:i:s', strtotime($saleData['created_at']));
            
            
            $writer->insertOne([
                $saleData['invoice_number'],
                $saleData['customer_name'] ?? '',
                $saleData['customer_phone'] ?? '',
                $saleData['subtotal'],
                $saleData['discount_amount'],
                $saleData['tax_amount'],
                $saleData['total_amount'],
                $saleData['payment_method'],
                $saleDate,
                $saleTime,
                $saleData['created_at'],
                $saleData['note'] ?? ''
            ]);

            // Save sale items
            $itemsWriter = Writer::createFromPath($this->salesItemsCsvPath, 'a+');
            foreach ($saleData['items'] as $item) {
                $itemsWriter->insertOne([
                    $saleData['invoice_number'],
                    $item['name'],
                    $item['model'] ?? '',
                    $item['quantity'],
                    $item['price'],
                    $item['quantity'] * $item['price'],
                    $saleData['created_at']
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to save sale to CSV: ' . $e->getMessage());
        }
    }

    public function getAllSales()
    {
        if (!file_exists($this->salesCsvPath)) {
            return [];
        }

        $csv = Reader::createFromPath($this->salesCsvPath, 'r');
        $csv->setHeaderOffset(0);
        
        $sales = [];
        foreach ($csv as $record) {
            $sale = [
                'invoice_number' => $record['invoice_number'],
                'customer_name' => $record['customer_name'],
                'customer_phone' => $record['customer_phone'],
                'subtotal' => floatval($record['subtotal']),
                'discount_amount' => floatval($record['discount_amount']),
                'tax_amount' => floatval($record['tax_amount']),
                'total_amount' => floatval($record['total_amount']),
                'payment_method' => $record['payment_method'],
                'sale_date' => $record['sale_date'],
                'sale_time' => $record['sale_time'],
                'created_at' => $record['created_at'],
                'note' => $record['note'] ?? '',
            ];
            
            // Load items for this sale
            $sale['items'] = $this->getSaleItems($record['invoice_number']);
            $sales[] = $sale;
        }

        // Sort by created_at descending
        usort($sales, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $sales;
    }

    public function getSaleByInvoice($invoiceNumber)
    {
        $sales = $this->getAllSales();
        
        foreach ($sales as $sale) {
            if ($sale['invoice_number'] === $invoiceNumber) {
                return $sale;
            }
        }
        
        return null;
    }

    public function getSaleItems($invoiceNumber)
    {
        if (!file_exists($this->salesItemsCsvPath)) {
            return [];
        }

        $csv = Reader::createFromPath($this->salesItemsCsvPath, 'r');
        $csv->setHeaderOffset(0);
        
        $items = [];
        foreach ($csv as $record) {
            if ($record['invoice_number'] === $invoiceNumber) {
                $items[] = [
                    'name' => $record['item_name'],
                    'model' => $record['item_model'],
                    'quantity' => intval($record['quantity']),
                    'price' => floatval($record['unit_price']),
                    'total_price' => floatval($record['total_price'])
                ];
            }
        }
        
        return $items;
    }

    public function getDailySales($date)
    {
        $allSales = $this->getAllSales();
        
        return array_filter($allSales, function($sale) use ($date) {
            return $sale['sale_date'] === $date;
        });
    }

    public function getSalesSummary($startDate = null, $endDate = null)
    {
        $allSales = $this->getAllSales();
        
        if ($startDate || $endDate) {
            $allSales = array_filter($allSales, function($sale) use ($startDate, $endDate) {
                $saleDate = $sale['sale_date'];
                
                if ($startDate && $saleDate < $startDate) {
                    return false;
                }
                
                if ($endDate && $saleDate > $endDate) {
                    return false;
                }
                
                return true;
            });
        }

        $totalSales = count($allSales);
        $totalAmount = array_sum(array_column($allSales, 'total_amount'));
        $totalTax = array_sum(array_column($allSales, 'tax_amount'));
        $totalDiscount = array_sum(array_column($allSales, 'discount_amount'));

        return [
            'total_sales' => $totalSales,
            'total_amount' => $totalAmount,
            'total_tax' => $totalTax,
            'total_discount' => $totalDiscount,
            'average_sale' => $totalSales > 0 ? $totalAmount / $totalSales : 0
        ];
    }

    public function exportSales($startDate = null, $endDate = null)
    {
        $sales = $this->getAllSales();
        
        if ($startDate || $endDate) {
            $sales = array_filter($sales, function($sale) use ($startDate, $endDate) {
                $saleDate = $sale['sale_date'];
                
                if ($startDate && $saleDate < $startDate) {
                    return false;
                }
                
                if ($endDate && $saleDate > $endDate) {
                    return false;
                }
                
                return true;
            });
        }

        $exportPath = storage_path('app/exports/sales_export_' . date('Y-m-d_H-i-s') . '.csv');
        
        // Create exports directory if it doesn't exist
        if (!file_exists(dirname($exportPath))) {
            mkdir(dirname($exportPath), 0777, true);
        }

        $writer = Writer::createFromPath($exportPath, 'w+');
        
        // Write headers
        $writer->insertOne([
            'Invoice Number',
            'Customer Name',
            'Customer Phone',
            'Items',
            'Subtotal',
            'Discount',
            'Tax',
            'Total Amount',
            'Payment Method',
            'Sale Date',
            'Sale Time'
        ]);

        // Write data
        foreach ($sales as $sale) {
            $itemsText = '';
            foreach ($sale['items'] as $item) {
                $itemsText .= $item['name'] . ' (Qty: ' . $item['quantity'] . ', Price: ' . $item['price'] . '); ';
            }
            
            $writer->insertOne([
                $sale['invoice_number'],
                $sale['customer_name'],
                $sale['customer_phone'],
                rtrim($itemsText, '; '),
                $sale['subtotal'],
                $sale['discount_amount'],
                $sale['tax_amount'],
                $sale['total_amount'],
                $sale['payment_method'],
                $sale['sale_date'],
                $sale['sale_time']
            ]);
        }

        return $exportPath;
    }
}