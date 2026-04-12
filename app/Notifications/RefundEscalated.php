<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundEscalated extends Notification
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
            'type'           => 'refund_escalated',
            'title'          => 'Refund Dieskalasi ke Admin',
            'message'        => "Buyer mengeskalasi refund transaksi {$this->refund->transaction->transaction_code} setelah ditolak seller.",
            'refund_id'      => $this->refund->id,
            'transaction_id' => $this->refund->transaction_id,
            'refund_amount'  => $this->refund->refund_amount,
            'buyer_name'     => $this->refund->buyer->name,
            'seller_name'    => $this->refund->seller->name,
            'seller_note'    => $this->refund->seller_note,
            'action_url'     => "/api/v1/admin/refunds/{$this->refund->id}",
        ];
    }
}
