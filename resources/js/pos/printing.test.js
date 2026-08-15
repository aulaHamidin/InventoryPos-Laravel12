import test from 'node:test';
import assert from 'node:assert/strict';
import { selectProfile } from './bluetooth-profiles.js';
import { bluetoothSupported, printReceiptBluetooth } from './bluetooth-transport.js';
import { chunkBytes, encodeEscPos } from './escpos-encoder.js';
import { formatReceipt } from './receipt-formatter.js';

const receipt = (method) => ({
    invoice_number: 'POS-TEST-001', items: [{ nama: 'Kopi', qty: 2, subtotal: 20000 }],
    subtotal_amount: 20000, discount_amount: 0, total_amount: 20000,
    payment_method: method, payment_method_label: method === 'cash' ? 'Tunai' : method === 'qris' ? 'QRIS Statis' : 'Transfer Bank',
    cash_received: 25000, change: 5000, manual_reference: '=SUM(A1:A2)', date: '15/08/2026 10:00', cashier: 'Owner',
});

test('cash, QRIS, and transfer share one receipt formatter with correct method data', () => {
    const cash = formatReceipt(receipt('cash'));
    const qris = formatReceipt(receipt('qris'));
    const transfer = formatReceipt(receipt('transfer'));
    assert.match(cash, /Tunai/);
    assert.match(cash, /Kembali/);
    assert.match(qris, /QRIS Statis/);
    assert.match(qris, /Dikonfirmasi Manual/);
    assert.match(transfer, /Transfer Bank/);
    assert.match(transfer, /Ref: =SUM/);
});

test('ESC/POS encoding initializes, chunks deterministically, and ends with cut command', () => {
    const bytes = encodeEscPos('TEST');
    assert.deepEqual([...bytes.slice(0, 2)], [0x1b, 0x40]);
    assert.deepEqual([...bytes.slice(-3)], [0x1d, 0x56, 0x00]);
    assert.deepEqual(chunkBytes(bytes, 3).map((chunk) => chunk.length), [3, 3, 3, 2]);
});

test('selects Nordic UART, HM-10, and 18F0 profiles and rejects mismatch', () => {
    assert.equal(selectProfile(['6e400001-b5a3-f393-e0a9-e50e24dcca9e']).id, 'nordic-uart');
    assert.equal(selectProfile(['0000ffe0-0000-1000-8000-00805f9b34fb']).id, 'hm10-ffe0');
    assert.equal(selectProfile(['000018f0-0000-1000-8000-00805f9b34fb']).id, 'ble-18f0');
    assert.equal(selectProfile(['unknown']), null);
});

function bluetoothMock({ connected = true, writeError = null, services = ['0000ffe0-0000-1000-8000-00805f9b34fb'], denial = null } = {}) {
    const writes = [];
    const server = {
        connected,
        getPrimaryServices: async () => services.map((uuid) => ({ uuid })),
        getPrimaryService: async () => ({
            getCharacteristic: async () => ({
                writeValueWithoutResponse: async (chunk) => {
                    if (writeError) throw writeError;
                    writes.push(chunk);
                },
            }),
        }),
    };
    return {
        writes,
        bluetooth: {
            requestDevice: async () => {
                if (denial) throw denial;
                return { name: 'Mock Printer', gatt: { connect: async () => server } };
            },
        },
    };
}

test('Bluetooth transport writes a valid receipt through a matching mock profile', async () => {
    const mock = bluetoothMock();
    const result = await printReceiptBluetooth(receipt('qris'), { bluetooth: mock.bluetooth });
    assert.equal(result.profile, 'hm10-ffe0');
    assert.ok(mock.writes.length > 0);
});

test('unsupported browser, permission denial, disconnect, write failure, and device mismatch are explicit failures', async () => {
    assert.equal(bluetoothSupported(undefined), false);
    await assert.rejects(printReceiptBluetooth(receipt('cash'), { bluetooth: {} }), /BLUETOOTH_UNSUPPORTED/);
    await assert.rejects(printReceiptBluetooth(receipt('cash'), { bluetooth: bluetoothMock({ denial: new Error('NotAllowedError') }).bluetooth }), /NotAllowedError/);
    await assert.rejects(printReceiptBluetooth(receipt('cash'), { bluetooth: bluetoothMock({ connected: false }).bluetooth }), /BLUETOOTH_DISCONNECTED/);
    await assert.rejects(printReceiptBluetooth(receipt('cash'), { bluetooth: bluetoothMock({ writeError: new Error('WRITE_FAILED') }).bluetooth }), /WRITE_FAILED/);
    await assert.rejects(printReceiptBluetooth(receipt('cash'), { bluetooth: bluetoothMock({ services: ['unknown'] }).bluetooth }), /PRINTER_PROFILE_MISMATCH/);
});
