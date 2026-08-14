<?php

namespace App\Notifications;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportExportReady extends Notification
{
    use Queueable;

    public function __construct(private readonly ReportExport $export) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Export laporan siap',
            'report_export_id' => $this->export->getKey(),
            'file_name' => $this->export->file_name,
            'download_url' => route('reports.exports.download', $this->export),
        ];
    }
}
