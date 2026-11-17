const API_URL = 'http://localhost:3000/api';

// 1. Fetch & Render Data Tamu
async function loadData() {
    try {
        const res = await fetch(`${API_URL}/tamu`);
        const data = await res.json();
        
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        let hadir = 0;
        let sent = 0;

        data.forEach(tamu => {
            if(tamu.status_kehadiran === 'hadir') hadir++;
            if(tamu.status_undangan === 'terkirim') sent++;

            // Status Badge UI
            let waBadge = '<span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs"><i class="fas fa-clock mr-1"></i>Belum</span>';
            if(tamu.status_undangan === 'terkirim') waBadge = '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs"><i class="fas fa-check mr-1"></i>Terkirim</span>';
            if(tamu.status_undangan === 'gagal') waBadge = '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs"><i class="fas fa-times mr-1"></i>Gagal</span>';
            
            let hadirBadge = '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Belum Konfirmasi</span>';
            if(tamu.status_kehadiran === 'hadir') hadirBadge = '<span class="px-2 py-1 bg-green-100 text-green-700 rounded font-semibold text-xs"><i class="fas fa-check-circle mr-1"></i>Hadir</span>';
            if(tamu.status_kehadiran === 'tidak_hadir') hadirBadge = '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs"><i class="fas fa-times-circle mr-1"></i>Tidak Hadir</span>';

            tbody.innerHTML += `
                <tr class="border-b hover:bg-slate-50 transition">
                    <td class="p-4 font-semibold text-slate-800">${tamu.nama}</td>
                    <td class="p-4 text-slate-600">${tamu.no_wa}</td>
                    <td class="p-4"><span class="bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded border border-blue-100">${tamu.kategori}</span></td>
                    <td class="p-4">${waBadge}</td>
                    <td class="p-4">${hadirBadge}</td>
                    <td class="p-4 text-right">
                        <button onclick="kirimWa(${tamu.id})" class="text-white bg-green-500 hover:bg-green-600 px-3 py-1.5 rounded text-sm shadow-sm transition" title="Kirim Pesan Otomatis via WA">
                            <i class="fab fa-whatsapp"></i> Send WA
                        </button>
                    </td>
                </tr>
            `;
        });

        // Update Dashboard Stats
        document.getElementById('statTotal').innerText = data.length;
        document.getElementById('statSent').innerText = sent;
        document.getElementById('statHadir').innerText = hadir;

    } catch(err) {
        console.error('Error loading data', err);
        alert("Gagal terhubung ke Backend (API). Pastikan Node.js Server sudah jalan.");
    }
}

// 2. Tambah Tamu (Form Submit)
document.getElementById('addForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // UI Loading state
    const btnSubmit = e.target.querySelector('button[type="submit"]');
    const originalText = btnSubmit.innerText;
    btnSubmit.innerText = 'Menyimpan...';
    btnSubmit.disabled = true;

    const data = {
        nama: document.getElementById('addNama').value,
        no_wa: document.getElementById('addWa').value,
        kategori: document.getElementById('addKat').value
    };

    try {
        await fetch(`${API_URL}/tamu`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        // Hide modal & reset form
        document.getElementById('addModal').classList.add('hidden');
        document.getElementById('addForm').reset();
        
        // Refresh Tabel
        loadData();
    } catch(err) {
        alert('Gagal menambah tamu. Periksa koneksi backend.');
    } finally {
        btnSubmit.innerText = originalText;
        btnSubmit.disabled = false;
    }
});

// 3. Import CSV
document.getElementById('importForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fileInput = document.getElementById('fileCsv');
    if(fileInput.files.length === 0) return alert('Pilih file CSV dulu');

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    // UI Loading state
    const btnSubmit = e.target.querySelector('button[type="submit"]');
    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengimport...';
    btnSubmit.disabled = true;

    try {
        const res = await fetch(`${API_URL}/import`, {
            method: 'POST',
            body: formData // Fetch API automatically sets correct multipart headers
        });
        
        const result = await res.json();
        alert(result.message || 'Import berhasil!');
        
        document.getElementById('importModal').classList.add('hidden');
        document.getElementById('importForm').reset();
        loadData();
    } catch(err) {
        alert('Gagal import data');
        console.error(err);
    } finally {
        btnSubmit.innerHTML = 'Upload & Import';
        btnSubmit.disabled = false;
    }
});

// 4. Kirim Undangan via WA Bot (Baileys)
async function kirimWa(id) {
    if(!confirm('Kirim pesan undangan digital via WhatsApp bot sekarang?')) return;
    
    // Bisa tambahkan loading overlay global di sini
    
    try {
        const res = await fetch(`${API_URL}/send-wa`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        
        if(res.ok) {
            alert('Sukses! Pesan undangan telah terkirim via WhatsApp Bot.');
        } else {
            alert('Gagal: ' + result.error);
        }
        
        // Update tabel (status Terkirim/Gagal)
        loadData();
    } catch(err) {
        alert('Kesalahan jaringan saat memanggil API WhatsApp.');
    }
}

// Inisialisasi: Load data saat pertama buka
loadData();
