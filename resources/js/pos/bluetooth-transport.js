import { printerProfiles, selectProfile } from './bluetooth-profiles.js';
import { chunkBytes, encodeEscPos } from './escpos-encoder.js';
import { formatReceipt } from './receipt-formatter.js';

export function bluetoothSupported(bluetooth = globalThis.navigator?.bluetooth) {
    return Boolean(bluetooth?.requestDevice);
}

export async function printReceiptBluetooth(receipt, options = {}) {
    const bluetooth = options.bluetooth ?? globalThis.navigator?.bluetooth;
    if (!bluetoothSupported(bluetooth)) throw new Error('BLUETOOTH_UNSUPPORTED');

    const device = await bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: printerProfiles.map((profile) => profile.service),
    });
    const server = await device.gatt.connect();
    if (!server.connected) throw new Error('BLUETOOTH_DISCONNECTED');

    const discovered = options.serviceUuids ?? await discoverServiceUuids(server);
    const profile = selectProfile(discovered);
    if (!profile) throw new Error('PRINTER_PROFILE_MISMATCH');

    const service = await server.getPrimaryService(profile.service);
    const characteristic = await service.getCharacteristic(profile.characteristic);
    const payload = encodeEscPos(formatReceipt(receipt, profile), options.encoder);

    for (const chunk of chunkBytes(payload, profile.chunkSize)) {
        if (!server.connected) throw new Error('BLUETOOTH_DISCONNECTED');
        if (typeof characteristic.writeValueWithoutResponse === 'function') {
            await characteristic.writeValueWithoutResponse(chunk);
        } else if (typeof characteristic.writeValue === 'function') {
            await characteristic.writeValue(chunk);
        } else {
            throw new Error('PRINTER_CHARACTERISTIC_NOT_WRITABLE');
        }
    }

    return { deviceName: device.name ?? null, profile: profile.id, bytesWritten: payload.length };
}

async function discoverServiceUuids(server) {
    if (typeof server.getPrimaryServices !== 'function') return [];
    return (await server.getPrimaryServices()).map((service) => service.uuid);
}
