export const printerProfiles = [
    { id: 'nordic-uart', name: 'Nordic UART', service: '6e400001-b5a3-f393-e0a9-e50e24dcca9e', characteristic: '6e400002-b5a3-f393-e0a9-e50e24dcca9e', columns: 32, chunkSize: 128 },
    { id: 'hm10-ffe0', name: 'HM-10 / FFE0', service: '0000ffe0-0000-1000-8000-00805f9b34fb', characteristic: '0000ffe1-0000-1000-8000-00805f9b34fb', columns: 32, chunkSize: 128 },
    { id: 'ble-18f0', name: '18F0 / 2AF1', service: '000018f0-0000-1000-8000-00805f9b34fb', characteristic: '00002af1-0000-1000-8000-00805f9b34fb', columns: 32, chunkSize: 128 },
];

const normalize = (uuid) => String(uuid).toLowerCase();

export function selectProfile(serviceUuids) {
    const available = new Set((serviceUuids ?? []).map(normalize));
    return printerProfiles.find((profile) => available.has(normalize(profile.service))) ?? null;
}
