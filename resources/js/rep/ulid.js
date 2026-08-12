// A minimal Crockford-base32 ULID generator — no package, per
// Docs/adr/0002-offline-sync-strategy.md §10, and the client_id/device_id
// columns are genuine char(26) ULID columns per
// Docs/adr/0003-id-strategy.md, not crypto.randomUUID()'s 36-character
// format.
const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export function ulid() {
    let time = Date.now();
    let timeChars = '';
    for (let i = 0; i < 10; i++) {
        timeChars = ENCODING[time % 32] + timeChars;
        time = Math.floor(time / 32);
    }

    const randomBytes = crypto.getRandomValues(new Uint8Array(10)); // 80 bits
    let bits = '';
    for (const byte of randomBytes) {
        bits += byte.toString(2).padStart(8, '0');
    }

    let randomChars = '';
    for (let i = 0; i < 16; i++) {
        randomChars += ENCODING[parseInt(bits.slice(i * 5, i * 5 + 5), 2)];
    }

    return timeChars + randomChars;
}
