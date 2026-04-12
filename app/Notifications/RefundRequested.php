<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundRequested extends Notification
{
    use Queueable;

    public function __construct(public Refund $refund) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'refund_requested',
            'title'          => 'Permintaan Refund Masuk',
            'message'        => "Buyer mengajukan refund untuk transaksi {$this->refund->transaction->transaction_code}.",
            'reason'         => $this->refund->reason,
            'refund_id'      => $this->refund->id,
            'transaction_id' => $this->refund->transaction_id,
            'transaction_code' => $this->refund->transaction->transaction_code,
            'refund_amount'  => $this->refund->refund_amount,
            'buyer_name'     => $this->refund->buyer->name,
            'action_url'     => "/api/v1/refunds/{$this->refund->id}",
        ];
    }
}
