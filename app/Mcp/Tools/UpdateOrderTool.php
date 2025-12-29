<?php

namespace App\Mcp\Tools;

use App\Models\SheetOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateOrderTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Update an existing order in the logistics system.

Use this tool to modify order details such as status, delivery information,
product details, or any other order fields. You must provide the order number
to identify which order to update.
MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $orderNo = $request->string('order_no');

            // Find the order
            $order = SheetOrder::where('order_no', $orderNo)->first();

            if (! $order) {
                return Response::error(
                    "Order '{$orderNo}' not found. Please check the order number and try again."
                );
            }

            // Prepare update data - only update fields that are provided
            $updateData = [];

            $fields = [
                'order_date', 'amount', 'client_name', 'address', 'phone',
                'alt_no', 'country', 'city', 'product_name', 'quantity',
                'status', 'agent', 'delivery_date', 'instructions', 'cc_email',
                'merchant', 'order_type', 'store_name', 'code',
            ];

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->get($field);

                    // Convert to appropriate types
                    if ($field === 'amount') {
                        $updateData[$field] = (float) $value;
                    } elseif ($field === 'quantity') {
                        $updateData[$field] = (int) $value;
                    } else {
                        $updateData[$field] = $value;
                    }
                }
            }

            if (empty($updateData)) {
                return Response::error(
                    'No fields to update were provided. Please specify at least one field to change.'
                );
            }

            // Store original values for comparison
            $originalValues = [];
            foreach (array_keys($updateData) as $field) {
                $originalValues[$field] = $order->{$field};
            }

            // Update the order
            $order->update($updateData);
            $order->refresh();

            // Build changes summary
            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $oldValue = $originalValues[$field];
                if ($oldValue != $newValue) {
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                    ];
                }
            }

            $responseData = [
                'message' => 'Order updated successfully',
                'order' => [
                    'id' => $order->id,
                    'order_no' => $order->order_no,
                    'client_name' => $order->client_name,
                    'amount' => $order->amount,
                    'product_name' => $order->product_name,
                    'quantity' => $order->quantity,
                    'city' => $order->city,
                    'status' => $order->status,
                    'merchant' => $order->merchant,
                    'delivery_date' => $order->delivery_date,
                ],
                'changes' => $changes,
                'changes_count' => count($changes),
            ];

            // Store data for retrieval
            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to update order: '.$e->getMessage());
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_no' => $schema->string()
                ->description('The order number to update (REQUIRED)')
                ->required(),

            'order_date' => $schema->string()
                ->description('Order date in YYYY-MM-DD format')
                ->nullable(),

            'amount' => $schema->number()
                ->description('Total order amount')
                ->nullable(),

            'client_name' => $schema->string()
                ->description('Customer full name')
                ->nullable(),

            'address' => $schema->string()
                ->description('Delivery address')
                ->nullable(),

            'phone' => $schema->string()
                ->description('Customer phone number')
                ->nullable(),

            'alt_no' => $schema->string()
                ->description('Alternative phone number')
                ->nullable(),

            'country' => $schema->string()
                ->description('Country name')
                ->nullable(),

            'city' => $schema->string()
                ->description('City name')
                ->nullable(),

            'product_name' => $schema->string()
                ->description('Name of the product')
                ->nullable(),

            'quantity' => $schema->integer()
                ->description('Product quantity')
                ->min(1)
                ->nullable(),

            'status' => $schema->string()
                ->description('Order status (e.g., Pending, Processing, Delivered, Cancelled)')
                ->nullable(),

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
                ->description('Merchant or store name')
                ->nullable(),

            'order_type' => $schema->string()
                ->description('Type of order (e.g., Online, Retail, Wholesale)')
                ->nullable(),

            'store_name' => $schema->string()
                ->description('Physical store name')
                ->nullable(),

            'code' => $schema->string()
                ->description('Special order or reference code')
                ->nullable(),
        ];
    }
}
