<?php

namespace App\Notifications;

use App\Models\BarterRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BarterRequestResponded extends Notification
{
    use Queueable;

    public function __construct(
        public BarterRequest $barter,
        public string $action
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $messages = [
            'accepted' => $this->barter->offer_additional_price > 0
                ? "Seller menerima tukar tambah! Kamu perlu membayar selisih Rp " . number_format($this->barter->offer_additional_price, 0, ',', '.') . "."
                : "Seller menerima permintaan tukar tambah kamu!",
            'rejected'          => "Seller menolak permintaan tukar tambah kamu. Alasan: {$this->barter->seller_note}",
            'payment_confirmed' => 'Pembayaran selisih tukar tambah dikonfirmasi. Seller akan segera menghubungi kamu.',
        ];

        $titles = [
            'accepted'          => 'Tukar Tambah Diterima!',
            'rejected'          => 'Tukar Tambah Ditolak',
            'payment_confirmed' => 'Pembayaran Selisih Dikonfirmasi',
        ];

        return [
            'type'       => 'barter_request_responded',
            'title'      => $titles[$this->action] ?? 'Update Tukar Tambah',
            'message'    => $messages[$this->action] ?? 'Status tukar tambah diperbarui.',
            'barter_id'  => $this->barter->id,
            'status'     => $this->barter->status,
            'action_url' => "/purchases/barter/{$this->barter->id}",
        ];
    }
}
