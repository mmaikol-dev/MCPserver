<?php

namespace App\Mcp\Tools;

use App\Models\SheetOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ViewOrderTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
View and search orders in the logistics system.

This tool can:
- View a single order by order number
- Search multiple orders by various criteria (client name, status, merchant, date range, etc.)
- List recent orders
- Filter and sort results

Use this tool when users want to:
- Find specific orders
- See order details
- Search for orders by customer name, product, status, etc.
- List orders from a specific merchant or time period
MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $orderNo = $request->get('order_no');

            // If order_no is provided, return single order
            if ($orderNo) {
                return $this->viewSingleOrder($orderNo, $request);
            }

            // Otherwise, search for multiple orders
            return $this->searchOrders($request);

        } catch (\Exception $e) {
            $errorData = ['error' => $e->getMessage()];
            $request->merge(['_response_data' => $errorData]);

            return Response::error('Failed to retrieve orders: '.$e->getMessage());
        }
    }

    /**
     * View a single order by order number
     */
    protected function viewSingleOrder(string $orderNo, Request $request): Response
    {
        $order = SheetOrder::where('order_no', $orderNo)->first();

        if (! $order) {
            return Response::error(
                "Order '{$orderNo}' not found. Please check the order number and try again."
            );
        }

        $responseData = [
            'message' => 'Order found',
            'type' => 'single',
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'order_date' => $order->order_date,
                'client_name' => $order->client_name,
                'phone' => $order->phone,
                'alt_no' => $order->alt_no,
                'address' => $order->address,
                'city' => $order->city,
                'country' => $order->country,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'amount' => $order->amount,
                'status' => $order->status,
                'merchant' => $order->merchant,
                'order_type' => $order->order_type,
                'delivery_date' => $order->delivery_date,
                'instructions' => $order->instructions,
                'agent' => $order->agent,
                'store_name' => $order->store_name,
                'sheet_name' => $order->sheet_name,
                'created_at' => $order->created_at?->toDateTimeString(),
                'updated_at' => $order->updated_at?->toDateTimeString(),
            ],
        ];

        $request->merge(['_response_data' => $responseData]);

        return Response::text(json_encode($responseData));
    }

    /**
     * Search for multiple orders based on criteria
     */
    protected function searchOrders(Request $request): Response
    {
        $query = SheetOrder::query();

        // Apply filters
        if ($request->has('client_name')) {
            $query->where('client_name', 'like', '%'.$request->get('client_name').'%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('merchant')) {
            $query->where('merchant', 'like', '%'.$request->get('merchant').'%');
        }

        if ($request->has('product_name')) {
            $query->where('product_name', 'like', '%'.$request->get('product_name').'%');
        }

        if ($request->has('city')) {
            $query->where('city', 'like', '%'.$request->get('city').'%');
        }

        if ($request->has('order_type')) {
            $query->where('order_type', $request->get('order_type'));
        }

        if ($request->has('agent')) {
            $query->where('agent', 'like', '%'.$request->get('agent').'%');
        }

        if ($request->has('phone')) {
            $query->where(function ($q) use ($request) {
                $phone = $request->get('phone');
                $q->where('phone', 'like', '%'.$phone.'%')
                    ->orWhere('alt_no', 'like', '%'.$phone.'%');
            });
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->get('date_to'));
        }

        // Amount range filters
        if ($request->has('min_amount')) {
            $query->where('amount', '>=', $request->get('min_amount'));
        }

        if ($request->has('max_amount')) {
            $query->where('amount', '<=', $request->get('max_amount'));
        }

        // Sort order
        $sortBy = $request->get('sort_by', 'order_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Limit results (default 10, max 50)
        $limit = min($request->integer('limit', 10), 50);
        $orders = $query->limit($limit)->get();

        if ($orders->isEmpty()) {
            return Response::error(
                'No orders found matching your criteria. Try different search parameters.'
            );
        }

        $ordersData = $orders->map(function ($order) {
            return [
                'order_no' => $order->order_no,
                'order_date' => $order->order_date,
                'client_name' => $order->client_name,
                'phone' => $order->phone,
                'city' => $order->city,
                'product_name' => $order->product_name,
                'quantity' => $order->quantity,
                'amount' => $order->amount,
                'status' => $order->status,
                'merchant' => $order->merchant,
                'order_type' => $order->order_type,
                'delivery_date' => $order->delivery_date,
            ];
        })->toArray();

        // Build search summary
        $filters = [];
        if ($request->has('client_name')) {
            $filters[] = "client: {$request->get('client_name')}";
        }
        if ($request->has('status')) {
            $filters[] = "status: {$request->get('status')}";
        }
        if ($request->has('merchant')) {
            $filters[] = "merchant: {$request->get('merchant')}";
        }
        if ($request->has('product_name')) {
            $filters[] = "product: {$request->get('product_name')}";
        }
        if ($request->has('city')) {
            $filters[] = "city: {$request->get('city')}";
        }
        if ($request->has('date_from') || $request->has('date_to')) {
            $filters[] = "date range: {$request->get('date_from', 'any')} to {$request->get('date_to', 'any')}";
        }

        $responseData = [
            'message' => 'Orders found',
            'type' => 'multiple',
            'count' => $orders->count(),
            'filters' => implode(', ', $filters) ?: 'none',
            'orders' => $ordersData,
            'total_amount' => $orders->sum('amount'),
        ];

        $request->merge(['_response_data' => $responseData]);

        return Response::text(json_encode($responseData));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            // Single order view
            'order_no' => $schema->string()
                ->description('Specific order number to view (if provided, shows only this order)')
                ->nullable(),

            // Search filters
            'client_name' => $schema->string()
                ->description('Search by customer name (partial match)')
                ->nullable(),

            'status' => $schema->string()
                ->description('Filter by order status (e.g., Pending, Processing, Delivered, Cancelled)')
                ->nullable(),

            'merchant' => $schema->string()
                ->description('Filter by merchant name (partial match)')
                ->nullable(),

            'product_name' => $schema->string()
                ->description('Filter by product name (partial match)')
                ->nullable(),

            'city' => $schema->string()
                ->description('Filter by city (partial match)')
                ->nullable(),

            'order_type' => $schema->string()
                ->description('Filter by order type (e.g., Online, Retail, Wholesale)')
                ->nullable(),

            'agent' => $schema->string()
                ->description('Filter by agent name (partial match)')
                ->nullable(),

            'phone' => $schema->string()
                ->description('Search by phone number (searches both main and alternative numbers)')
                ->nullable(),

            // Date range
            'date_from' => $schema->string()
                ->description('Start date for order date range (YYYY-MM-DD)')
                ->nullable(),

            'date_to' => $schema->string()
                ->description('End date for order date range (YYYY-MM-DD)')
                ->nullable(),

            // Amount range
            'min_amount' => $schema->number()
                ->description('Minimum order amount')
                ->nullable(),

            'max_amount' => $schema->number()
                ->description('Maximum order amount')
                ->nullable(),

            // Sorting and limiting
            'sort_by' => $schema->string()
                ->description('Field to sort by (order_date, amount, client_name, status)')
                ->default('order_date')
                ->nullable(),

            'sort_order' => $schema->string()
                ->description('Sort order: asc or desc')
                ->default('desc')
                ->nullable(),

            'limit' => $schema->integer()
                ->description('Maximum number of results to return (default 10, max 50)')
                ->default(10)
                ->nullable(),
        ];
    }
}
