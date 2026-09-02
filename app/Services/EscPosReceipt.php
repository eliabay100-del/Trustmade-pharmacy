<?php

namespace App\Services;

use App\Models\Order;

/**
 * Builds a raw ESC/POS byte string for 80mm thermal printers.
 * The HTML receipt is what cashiers actually print from the browser
 * (Windows POS-80 driver). This download is for a raw USB/network
 * printer that wants ESC/POS instead of a Windows driver.
 */
class EscPosReceipt
{
    private const WIDTH = 42;

    public function forOrder(Order $order): string
    {
        $ESC = "\x1B";
        $GS = "\x1D";

        $out = $ESC . '@';
        $out .= $ESC . 'a' . chr(1);
        $out .= $ESC . '!' . chr(0x18);
        $out .= $this->line('PMAS');
        $out .= $ESC . '!' . chr(0);
        $out .= $this->line($order->branch->name ?? 'Pharmacy');
        if ($order->branch?->address) {
            $out .= $this->line($order->branch->address);
        }
        if ($order->branch?->phone) {
            $out .= $this->line($order->branch->phone);
        }
        $out .= $this->line(str_repeat('-', self::WIDTH));

        $out .= $ESC . 'a' . chr(0);
        $out .= $this->line('Order #' . $order->id);
        $out .= $this->line($order->created_at->format('Y-m-d H:i'));
        $out .= $this->line('Cashier: ' . ($order->user->name ?? '—'));
        $out .= $this->line('Customer: ' . ($order->customer->name ?? 'Walk-in'));
        $out .= $this->line(str_repeat('-', self::WIDTH));

        foreach ($order->items as $item) {
            $name = $item->purchaseBatch->drug->name ?? 'Item';
            $qty = (int) $item->quantity_issued;
            $total = number_format((float) $item->total_amount, 2);
            $out .= $this->line($name);
            $out .= $this->cols($qty . ' x ' . number_format((float) $item->selling_price_per_unit, 2), $total);
        }

        $out .= $this->line(str_repeat('-', self::WIDTH));
        $out .= $ESC . '!' . chr(0x08);
        $out .= $this->cols('TOTAL', number_format((float) $order->total_amount, 2));
        $out .= $ESC . '!' . chr(0);

        foreach ($order->payments as $payment) {
            $out .= $this->cols($payment->methodLabel(), number_format((float) $payment->amount, 2));
        }

        $out .= $this->line(str_repeat('-', self::WIDTH));
        $out .= $ESC . 'a' . chr(1);
        $out .= $this->line('Thank you');
        $out .= "\n\n\n";
        $out .= $GS . 'V' . chr(66) . chr(3);

        return $out;
    }

    private function line(string $text): string
    {
        return substr($text, 0, self::WIDTH) . "\n";
    }

    private function cols(string $left, string $right): string
    {
        $space = self::WIDTH - strlen($left) - strlen($right);
        if ($space < 1) {
            $left = substr($left, 0, self::WIDTH - strlen($right) - 1);
            $space = 1;
        }

        return $left . str_repeat(' ', $space) . $right . "\n";
    }
}
