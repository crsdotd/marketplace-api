<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundResponded extends Notification
{
    use Queueable;

    public function __construct(public Refund $refund, public string $action) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $messages = [
            'seller_approved' => 'Seller menyetujui permintaan refund kamu. Dana akan segera dikembalikan.',
            'seller_rejected' => 'Seller menolak permintaan refund kamu. Kamu bisa eskalasi ke admin.',
        ];

        return [
            'type'           => 'refund_responded',
            'title'          => $this->action === 'seller_approved'
                ? 'Refund Disetujui Seller'
                : 'Refund Ditolak Seller',
            'message'        => $messages[$this->action] ?? 'Status refund kamu telah diperbarui.',
            'refund_id'      => $this->refund->id,
            'transaction_id' => $this->refund->transaction_id,
            'status'         => $this->refund->status,
            'seller_note'    => $this->refund->seller_note,
            'action_url'     => "/api/v1/refunds/{$this->refund->id}",
        ];
    }
}
