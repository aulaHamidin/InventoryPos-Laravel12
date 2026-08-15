const ESC = 0x1b;
const GS = 0x1d;

export function encodeEscPos(text, encoder = new TextEncoder()) {
    const body = encoder.encode(text);
    const bytes = new Uint8Array(2 + body.length + 5);
    bytes.set([ESC, 0x40], 0);
    bytes.set(body, 2);
    bytes.set([0x0a, 0x0a, GS, 0x56, 0x00], 2 + body.length);
    return bytes;
}

export function chunkBytes(bytes, chunkSize = 128) {
    if (!Number.isInteger(chunkSize) || chunkSize <= 0) throw new Error('INVALID_CHUNK_SIZE');
    const chunks = [];
    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
        chunks.push(bytes.slice(offset, offset + chunkSize));
    }
    return chunks;
}
