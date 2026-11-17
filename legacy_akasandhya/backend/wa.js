const { makeWASocket, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');

let sock;

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('auth_info_baileys');
    
    sock = makeWASocket({
        auth: state,
        printQRInTerminal: true,
        defaultQueryTimeoutMs: undefined
    });

    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;
        
        if(qr) {
            console.log('\n======================================================');
            console.log('SCAN QR CODE INI DI WHATSAPP ANDA UNTUK MENGAKTIFKAN BOT');
            console.log('======================================================\n');
        }
        
        if(connection === 'close') {
            const shouldReconnect = lastDisconnect.error?.output?.statusCode !== 401;
            console.log('Koneksi WA terputus. Mencoba menghubungkan kembali...', shouldReconnect);
            if(shouldReconnect) {
                connectToWhatsApp();
            } else {
                console.log('Sesi WA kadaluarsa atau Anda telah Log Out. Silakan hapus folder "auth_info_baileys" dan restart server.');
            }
        } else if(connection === 'open') {
            console.log('✅ WhatsApp Bot Berhasil Terhubung!');
        }
    });

    sock.ev.on('creds.update', saveCreds);
}

async function sendWhatsAppMessage(number, message) {
    if (!sock) {
        throw new Error('WhatsApp belum terhubung ke server');
    }
    
    // Format nomor HP ke format internasional WA (misal: 0812... menjadi 62812...)
    let formattedNumber = number.replace(/\D/g, '');
    if (formattedNumber.startsWith('0')) {
        formattedNumber = '62' + formattedNumber.substring(1);
    }
    
    const jid = formattedNumber + '@s.whatsapp.net';
    await sock.sendMessage(jid, { text: message });
}

module.exports = { connectToWhatsApp, sendWhatsAppMessage };
