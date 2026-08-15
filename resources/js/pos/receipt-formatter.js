const money = (value) => `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value ?? 0))}`;

const safe = (value) => String(value ?? '').replace(/[\r\n\t]+/g, ' ').trim();

const row = (left, right, width) => {
    const cleanLeft = safe(left);
    const cleanRight = safe(right);
    const room = Math.max(1, width - cleanRight.length - 1);
    return `${cleanLeft.slice(0, room).padEnd(room)} ${cleanRight.slice(0, width - room - 1)}`;
};

export function formatReceipt(receipt, profile = { columns: 32 }) {
    const width = profile.columns ?? 32;
    const lines = [
        'INVENTORI-Q'.padStart(Math.floor((width + 11) / 2)),
        safe(receipt.invoice_number),
        '-'.repeat(width),
    ];

    for (const item of receipt.items ?? []) {
        lines.push(safe(`${item.nama} x ${item.qty}`).slice(0, width));
        lines.push(row('', money(item.subtotal), width));
    }

    lines.push('-'.repeat(width));
    lines.push(row('Bruto', money(receipt.subtotal_amount), width));
    lines.push(row('Diskon', `-${money(receipt.discount_amount)}`, width));
    lines.push(row('TOTAL', money(receipt.total_amount), width));
    lines.push(row('Metode', safe(receipt.payment_method_label), width));

    if (receipt.payment_method === 'cash') {
        lines.push(row('Tunai', money(receipt.cash_received), width));
        lines.push(row('Kembali', money(receipt.change), width));
    } else {
        lines.push('Dikonfirmasi Manual');
        if (receipt.manual_reference) lines.push(`Ref: ${safe(receipt.manual_reference)}`.slice(0, width));
    }

    if (receipt.requires_refund) {
        lines.push('! REFUND WAJIB !');
        lines.push(row('Refund due', money(receipt.refund_due_amount), width));
    }

    lines.push('-'.repeat(width));
    lines.push(safe(`${receipt.date ?? ''} ${receipt.cashier ?? ''}`).slice(0, width));
    lines.push('Terima kasih');

    return `${lines.join('\n')}\n`;
}

export { money, safe };
