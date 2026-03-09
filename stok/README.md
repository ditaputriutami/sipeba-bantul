# Stok Utilities

Folder ini berisi file-file utility dan maintenance untuk pengelolaan stok sistem SIPEBA.

## File yang Tersedia

### fix_sisa_stok.php

**Tujuan:** Memperbaiki data `sisa_stok` pada tabel `penerimaan` untuk sistem FIFO.

**Kapan digunakan:**

- Setelah migrasi data lama
- Jika ada data penerimaan yang sudah disetujui tapi `sisa_stok = 0`

**Akses:** Hanya superadmin

**Cara menggunakan:**

1. Login sebagai superadmin
2. Akses: `http://localhost/SIPEBA-Bantul/stok/fix_sisa_stok.php`
3. Review preview data yang akan diperbaiki
4. Klik tombol "Jalankan Perbaikan"

**SQL yang dijalankan:**

```sql
UPDATE penerimaan
SET sisa_stok = jumlah
WHERE status = 'disetujui'
  AND (sisa_stok = 0 OR sisa_stok IS NULL)
```

---

## Catatan

- File-file di folder ini hanya untuk maintenance/utility
- Akses dibatasi untuk role tertentu (biasanya superadmin)
- Selalu backup database sebelum menjalankan script maintenance
