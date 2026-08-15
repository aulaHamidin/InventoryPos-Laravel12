<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PosRefundRequired extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $transactionId,
        public readonly string $invoiceNumber,
        public readonly string $amount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Refund POS diperlukan',
            'transaction_id' => $this->transactionId,
            'invoice_number' => $this->invoiceNumber,
            'amount' => $this->amount,
        ];
    }
}
