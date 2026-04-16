<?php

namespace App\Notifications;

use App\Models\BarterRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BarterRequestReceived extends Notification
{
    use Queueable;

    public function __construct(public BarterRequest $barter) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'barter_request_received',
            'title'           => 'Permintaan Tukar Tambah Masuk',
            'message'         => "{$this->barter->buyer->name} ingin menukar \"{$this->barter->offer_item_name}\" dengan produk \"{$this->barter->product->title}\".",
            'barter_id'       => $this->barter->id,
            'product_id'      => $this->barter->product_id,
            'offer_item_name' => $this->barter->offer_item_name,
            'buyer_name'      => $this->barter->buyer->name,
            'action_url'      => "/seller/barter/{$this->barter->id}",
        ];
    }
}
