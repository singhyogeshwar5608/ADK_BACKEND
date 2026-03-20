<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        protected array $product,
        protected string $eventType
    ) {
    }

    public static function fromModel(Product $product, string $eventType): self
    {
        return new self($product->toArray(), $eventType);
    }

    public function broadcastOn(): array
    {
        return [new Channel('members.products')];
    }

    public function broadcastAs(): string
    {
        return 'products.' . $this->eventType;
    }

    public function broadcastWith(): array
    {
        return [
            'product' => $this->product,
        ];
    }
}
