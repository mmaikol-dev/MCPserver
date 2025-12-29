<?php

namespace App\Mcp\Tools;

use App\Models\SheetOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteOrderTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
Delete an existing order from the logistics system.

⚠️ CRITICAL: This action is IRREVERSIBLE and permanently removes the order.
Requires both the order number AND a confirmation password for security.

Use this tool only when the user explicitly requests to delete an order
and provides the correct password for confirmation.
MARKDOWN;

    /**
     * The password required for deletion confirmation.
     */
    protected const DELETE_PASSWORD = 'qwerty2025!';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $orderNo = $request->string('order_no');
            $password = $request->string('password');

            // Validate password first
            if ($password !== self::DELETE_PASSWORD) {
                return Response::error(
                    '❌ Invalid password. Deletion cancelled for security reasons. '.
                    'Please provide the correct password to confirm deletion.'
                );
            }

            // Find the order
            $order = SheetOrder::where('order_no', $orderNo)->first();

            if (! $order) {
                return Response::error(
                    "Order '{$orderNo}' not found. Please check the order number and try again."
                );
            }

            // Store order details before deletion for confirmation message
            $deletedOrderDetails = [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'client_name' => $order->client_name,
                'amount' => $order->amount,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'merchant' => $order->merchant,
                'city' => $order->city,
                'order_date' => $order->order_date,
            ];

            // Delete the order
            $order->delete();

            $responseData = [
                'message' => 'Order deleted successfully',
                'deleted' => true,
                'order' => $deletedOrderDetails,
                'warning' => 'This action cannot be undone',
            ];

            // Store data for retrieval
            $request->merge(['_response_data' => $responseData]);

            return Response::text(json_encode($responseData));

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to delete order: '.$e->getMessage());
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
                ->description('The order number to delete (REQUIRED)')
                ->required(),

            'password' => $schema->string()
                ->description('Confirmation password required for deletion (REQUIRED for security)')
                ->required(),
        ];
    }
}
