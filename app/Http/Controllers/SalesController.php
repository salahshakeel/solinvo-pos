<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use App\Services\ReceiptService;
use Illuminate\Support\Str;
use App\Services\CsvProductService;
class SalesController extends Controller
{
     private $receiptService;
    private $csvSalesService;

    public function __construct(ReceiptService $receiptService, CsvProductService $csvSalesService)
    {
        $this->csvSalesService = $csvSalesService;
        $this->receiptService = $receiptService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
      $sales  = $this->csvSalesService->getAllSales();
        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'payment_method' => 'required|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $items = $request->items;
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['price'];
        }

        $discountAmount = $request->discount_amount ?? 0;
        $taxAmount = ($subtotal - $discountAmount) * 0.16; // 16% tax
        $totalAmount = $subtotal - $discountAmount + $taxAmount;

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . rand(10000, 99999);

        // Create sale data object
        $saleData = [
            'invoice_number' => $invoiceNumber,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'total_amount' => $totalAmount,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'payment_method' => $request->payment_method,
            'items' => $items,
            'created_at' => now()->toDateTimeString(),
            'subtotal' => $subtotal,
            'note' => $request->note
        ];
        

        try {
            // Save to CSV
            $this->csvSalesService->saveSale($saleData);

            // Generate receipt
            $receiptPath = $this->receiptService->generateReceipt($saleData);

            return response()->json([
                'success' => true,
                'data' => $saleData,
                'receipt_path' => $receiptPath
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save sale: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sale $sale)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        //
    }
}
