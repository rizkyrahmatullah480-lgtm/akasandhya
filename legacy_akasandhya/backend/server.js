const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const multer = require('multer');
const csv = require('csv-parser');
const fs = require('fs');
const db = require('./db');
const { connectToWhatsApp, sendWhatsAppMessage } = require('./wa');

const app = express();
app.use(cors());
app.use(bodyParser.json());

// Jalankan Koneksi WhatsApp Bot
console.log("Memulai inisialisasi WhatsApp Bot...");
connectToWhatsApp();

// API 1: Mengambil Data Tamu
app.get('/api/tamu', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT * FROM tamu ORDER BY id DESC');
        res.json(rows);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// API 2: Menambahkan Tamu Baru
app.post('/api/tamu', async (req, res) => {
    const { nama, no_wa, kategori } = req.body;
    try {
        await db.query('INSERT INTO tamu (nama, no_wa, kategori) VALUES (?, ?, ?)', [nama, no_wa, kategori]);
        res.json({ message: 'Data tamu berhasil ditambahkan' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// API 3: Mengirim Pesan Undangan Otomatis via WA Bot
app.post('/api/send-wa', async (req, res) => {
    const { id } = req.body;
    try {
        const [rows] = await db.query('SELECT * FROM tamu WHERE id = ?', [id]);
        if (rows.length === 0) return res.status(404).json({ error: 'Data tamu tidak ditemukan' });
        
        const tamu = rows[0];
        
        // Ganti URL localhost ini ke URL domain asli Anda saat sudah di-hosting
        const linkUndangan = `http://localhost/akasandhya/frontend/index.html?to=${encodeURIComponent(tamu.nama)}`;
        
        const pesanTeks = `Assalamu’alaikum Wr. Wb.

Yth. Bapak/Ibu/Saudara/i *${tamu.nama}*,

Dengan memohon rahmat dan ridho Allah SWT, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri acara **Akhirusannah Angkatan Akasandhya**.

Berikut tautan undangan digital Anda:
👉 ${linkUndangan}

Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir untuk memberikan doa restu.

Wassalamu’alaikum Wr. Wb.

*Pesan ini dikirim secara otomatis oleh Sistem Undangan Akasandhya*`;

        await sendWhatsAppMessage(tamu.no_wa, pesanTeks);
        
        // Update status di DB
        await db.query('UPDATE tamu SET status_undangan = ? WHERE id = ?', ['terkirim', id]);
        
        res.json({ message: 'Undangan berhasil dikirim' });
    } catch (err) {
        console.error("Gagal mengirim WA:", err);
        await db.query('UPDATE tamu SET status_undangan = ? WHERE id = ?', ['gagal', id]);
        res.status(500).json({ error: 'Gagal mengirim pesan WhatsApp: ' + err.message });
    }
});

// API 4: Konfirmasi Kehadiran (RSVP) & Ucapan
app.post('/api/rsvp', async (req, res) => {
    const { nama, ucapan, kehadiran } = req.body;
    try {
        // Cek apakah tamu sudah ada di DB berdasarkan namanya
        const [rows] = await db.query('SELECT id FROM tamu WHERE nama = ? LIMIT 1', [nama]);
        
        if(rows.length > 0) {
            // Jika ada, update datanya
            await db.query('UPDATE tamu SET ucapan = ?, status_kehadiran = ? WHERE nama = ?', [ucapan, kehadiran, nama]);
        } else {
            // Jika tidak ada di DB (tamu tak terdaftar), simpan saja ke DB dengan no_wa kosong
            await db.query('INSERT INTO tamu (nama, no_wa, kategori, status_kehadiran, ucapan) VALUES (?, ?, ?, ?, ?)', [nama, '-', 'Tamu Umum', kehadiran, ucapan]);
        }
        res.json({ message: 'Terima kasih atas konfirmasi dan ucapan Anda!' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// API 5: Mengambil Daftar Ucapan untuk ditampilkan di Frontend
app.get('/api/ucapan', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT nama, ucapan, created_at FROM tamu WHERE ucapan IS NOT NULL AND ucapan != "" ORDER BY created_at DESC');
        res.json(rows);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// API 6: Import Data dari CSV
const upload = multer({ dest: 'uploads/' });
app.post('/api/import', upload.single('file'), (req, res) => {
    if(!req.file) return res.status(400).json({error: 'File CSV tidak ditemukan'});
    
    const results = [];
    fs.createReadStream(req.file.path)
      .pipe(csv())
      .on('data', (data) => results.push(data))
      .on('end', async () => {
        try {
            for(let t of results) {
                if(t.nama && t.no_wa) {
                    await db.query('INSERT INTO tamu (nama, no_wa, kategori) VALUES (?, ?, ?)', [t.nama, t.no_wa, t.kategori || 'Tamu Undangan']);
                }
            }
            // Hapus file sementara
            fs.unlinkSync(req.file.path);
            res.json({ message: 'Import data CSV berhasil' });
        } catch(err) {
            res.status(500).json({ error: err.message });
        }
      });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`\n🚀 Server Backend Node.js telah berjalan di http://localhost:${PORT}`);
});
