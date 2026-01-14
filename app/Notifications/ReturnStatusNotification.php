<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReturnStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $queue = 'high';

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        protected $return,
        protected $status,
        protected $message = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'return',
            'return_id' => $this->return->id,
            'status' => $this->status,
            'message' => $this->message ?? $this->getDefaultMessage(),
            'order_id' => $this->return->order_id,
            'icon' => $this->getIcon(),
            'link' => $this->getLink(),
        ];
    }

    private function getDefaultMessage(): string
    {
        $messages = [
            'pending' => 'Заявка на возврат создана и ожидает рассмотрения продавцом.',
            'approved' => 'Продавец одобрил возврат. Ожидайте дальнейших инструкций.',
            'in_transit' => 'Возврат находится в пути к продавцу. Ожидайте получения на складе.',
            'in_transit_back_to_customer' => 'Товар возвращается вам. Ожидайте курьера.',
            'received' => 'Товар получен на складе и ожидает проверки.',
            'condition_ok' => 'Состояние товара проверено. Возврат средств будет осуществлен.',
            'condition_bad' => 'Продавец возвращает вам товар. Подтвердите получение через курьера или товар будет утилизирован в течение 24 часов.',
            'refund_initiated' => 'Возврат средств начат. Ожидайте поступления на счет.',
            'completed' => 'Возврат успешно завершен. Средства возвращены на ваш счет.',
            'rejected' => 'Продавец отклонил заявку на возврат.',
            'rejected_by_warehouse' => 'Возврат завершен. Товар утилизирован или возвращен вам.',
        ];

        return $messages[$this->status] ?? 'Статус возврата обновлен.';
    }

    private function getIcon(): string
    {
        $icons = [
            'pending' => ' mdi-file-document-outline',
            'approved' => 'mdi-check-circle-outline',
            'in_transit' => 'mdi-truck-delivery-outline',
            'in_transit_back_to_customer' => 'mdi-truck-delivery-outline',
            'received' => 'mdi-package-variant-closed',
            'condition_ok' => 'mdi-checkbox-marked-circle-outline',
            'condition_bad' => 'mdi-alert-circle-outline',
            'refund_initiated' => 'mdi-cash-refund',
            'completed' => 'mdi-check-all',
            'rejected' => 'mdi-close-circle-outline',
            'rejected_by_warehouse' => 'mdi-check-all',
        ];

        return $icons[$this->status] ?? 'mdi-information-outline';
    }

    private function getLink(): string
    {
        return "/return/{$this->return->id}";
    }
}
