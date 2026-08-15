import { bluetoothSupported, printReceiptBluetooth } from './pos/bluetooth-transport.js';

window.InventoriQPosPrinter = {
    isSupported: () => bluetoothSupported(),
    print: (receipt) => printReceiptBluetooth(receipt),
};
