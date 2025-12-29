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
System-managed fields such as IDs and timestamps are handled automatically.
MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $order = SheetOrder::create([
            'order_date' => $request->string('order_date'),
            'order_no' => $request->string('order_no'),
            'amount' => $request->float('amount'),
            'client_name' => $request->string('client_name'),
            'address' => $request->string('address'),
            'phone' => $request->string('phone'),
            'alt_no' => $request->optional('alt_no'),
            'country' => $request->string('country', 'Kenya'),
            'city' => $request->string('city'),
            'product_name' => $request->string('product_name'),
            'quantity' => $request->int('quantity'),
            'status' => $request->string('status', 'Pending'),
            'agent' => $request->optional('agent'),
            'delivery_date' => $request->optional('delivery_date'),
            'instructions' => $request->optional('instructions'),
            'cc_email' => $request->optional('cc_email'),
            'merchant' => $request->string('merchant'),
            'order_type' => $request->string('order_type'),
            'sheet_id' => $request->optional('sheet_id'),
            'sheet_name' => $request->optional('sheet_name'),
            'store_name' => $request->optional('store_name'),
            'code' => $request->optional('code'),
            'processed' => 0,
        ]);

        return Response::json([
            'message' => 'Order created successfully',
            'order_id' => $order->id,
            'order_no' => $order->order_no,
        ]);
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

            'order_no' => $schema->string()
                ->description('Unique order reference number')
                ->required(),

            'amount' => $schema->number()
                ->description('Total order amount')
                ->required(),

            'client_name' => $schema->string()
                ->required(),

            'address' => $schema->string()
                ->required(),

            'phone' => $schema->string()
                ->required(),

            'alt_no' => $schema->string()
                ->nullable(),

            'country' => $schema->string()
                ->default('Kenya'),

            'city' => $schema->string()
                ->required(),

            'product_name' => $schema->string()
                ->required(),

            'quantity' => $schema->integer()
                ->min(1)
                ->required(),

            'status' => $schema->string()
                ->default('Pending'),

            'agent' => $schema->string()
                ->nullable(),

            'delivery_date' => $schema->string()
                ->nullable()
                ->description('Delivery date in YYYY-MM-DD format'),

            'instructions' => $schema->string()
                ->nullable(),

            'cc_email' => $schema->string()
                ->nullable(),

            'merchant' => $schema->string()
                ->required(),

            'order_type' => $schema->string()
                ->required(),

            'sheet_id' => $schema->string()
                ->nullable(),

            'sheet_name' => $schema->string()
                ->nullable(),

            'store_name' => $schema->string()
                ->nullable(),

            'code' => $schema->string()
                ->nullable(),
        ];
    }
}
