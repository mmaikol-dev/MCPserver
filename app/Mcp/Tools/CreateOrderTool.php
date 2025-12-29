<?php

namespace App\Mcp\Tools;

use App\Models\SheetOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateOrderTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Create a new order in the logistics system.

Use this tool to register customer orders including product details,
delivery information, merchant details, and order metadata.
Order numbers are automatically generated based on the merchant's sheet.
MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            // Get merchant name
            $merchantName = $request->string('merchant');

            // Find sheet_id and sheet_name from existing orders for this merchant
            $merchantOrder = SheetOrder::where('merchant', $merchantName)
                ->whereNotNull('sheet_id')
                ->whereNotNull('sheet_name')
                ->latest()
                ->first();

            if (! $merchantOrder) {
                return Response::error(
                    "No sheet configuration found for merchant '{$merchantName}'. ".
                    'Please ensure this merchant has been set up with a sheet_id and sheet_name.'
                );
            }

            $sheetId = $merchantOrder->sheet_id;
            $sheetName = $merchantOrder->sheet_name;

            // Generate next order number
            $orderNo = $this->getNextOrderNumber($sheetName, $sheetId);

            // Create the order
            $order = SheetOrder::create([
                'order_date' => $request->string('order_date'),
                'order_no' => $orderNo,
                'amount' => $request->float('amount'),
                'client_name' => $request->string('client_name'),
                'address' => $request->string('address'),
                'phone' => $request->string('phone'),
                'alt_no' => $request->get('alt_no'),
                'country' => $request->string('country', 'Kenya'),
                'city' => $request->string('city'),
                'product_name' => $request->string('product_name'),
                'quantity' => $request->integer('quantity'),
                'status' => $request->string('status', 'Pending'),
                'agent' => $request->get('agent'),
                'delivery_date' => $request->get('delivery_date'),
                'instructions' => $request->get('instructions'),
                'cc_email' => $request->get('cc_email'),
                'merchant' => $merchantName,
                'order_type' => $request->string('order_type'),
                'sheet_id' => $sheetId,
                'sheet_name' => $sheetName,
                'store_name' => $request->get('store_name'),
                'code' => $request->get('code'),
                'processed' => 0,
            ]);

            return Response::json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'client_name' => $order->client_name,
                    'amount' => $order->amount,
                    'product_name' => $order->product_name,
                    'quantity' => $order->quantity,
                    'city' => $order->city,
                    'merchant' => $order->merchant,
                    'status' => $order->status,
                    'sheet_name' => $order->sheet_name,
                ],
            ]);

        } catch (\Exception $e) {
            return Response::error('Failed to create order: '.$e->getMessage());
        }
    }

    /**
     * Generate the next order number for a given sheet
     */
    protected function getNextOrderNumber(string $sheetName, ?string $sheetId = null): string
    {
        // Build query to find highest order number
        $query = SheetOrder::where('sheet_name', $sheetName);

        if ($sheetId) {
            $query->where('sheet_id', $sheetId);
        }

        // Get the highest order number
        $lastOrder = $query->orderBy('order_no', 'desc')->first();

        if (! $lastOrder || ! $lastOrder->order_no) {
            // First order for this sheet
            return $sheetName.'-001';
        }

        // Extract the numeric part from the last order number
        // Assumes format: SHEETNAME-XXX or similar
        $lastOrderNo = $lastOrder->order_no;

        // Try to extract number from various formats
        if (preg_match('/(\d+)$/', $lastOrderNo, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;

            // Determine padding based on last number
            $padding = strlen($matches[1]);

            // Replace the last number with the incremented one
            $nextOrderNo = preg_replace(
                '/\d+$/',
                str_pad($nextNumber, $padding, '0', STR_PAD_LEFT),
                $lastOrderNo
            );

            return $nextOrderNo;
        }

        // Fallback: append -001 to sheet name
        return $sheetName.'-'.str_pad(1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_date' => $schema->string()
                ->description('Order date in YYYY-MM-DD format')
                ->required(),

            'amount' => $schema->number()
                ->description('Total order amount')
                ->required(),

            'client_name' => $schema->string()
                ->description('Customer full name')
                ->required(),

            'address' => $schema->string()
                ->description('Delivery address')
                ->required(),

            'phone' => $schema->string()
                ->description('Customer phone number')
                ->required(),

            'alt_no' => $schema->string()
                ->description('Alternative phone number')
                ->nullable(),

            'country' => $schema->string()
                ->description('Country name')
                ->default('Kenya'),

            'city' => $schema->string()
                ->description('City name')
                ->required(),

            'product_name' => $schema->string()
                ->description('Name of the product')
                ->required(),

            'quantity' => $schema->integer()
                ->description('Product quantity')
                ->min(1)
                ->required(),

            'status' => $schema->string()
                ->description('Order status')
                ->default('Pending'),

            'agent' => $schema->string()
                ->description('Agent or sales representative name')
                ->nullable(),

            'delivery_date' => $schema->string()
                ->description('Expected delivery date in YYYY-MM-DD format')
                ->nullable(),

            'instructions' => $schema->string()
                ->description('Special delivery or handling instructions')
                ->nullable(),

            'cc_email' => $schema->string()
                ->description('CC email address for notifications')
                ->nullable(),

            'merchant' => $schema->string()
                ->description('Merchant or store name - REQUIRED to generate order number')
                ->required(),

            'order_type' => $schema->string()
                ->description('Type of order (e.g., Online, Retail, Wholesale)')
                ->required(),

            'store_name' => $schema->string()
                ->description('Physical store name')
                ->nullable(),

            'code' => $schema->string()
                ->description('Special order or reference code')
                ->nullable(),
        ];
    }
}
