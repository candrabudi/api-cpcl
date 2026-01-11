# Shipment Store API Documentation

Dokumentasi ini mencakup dua skenario penggunaan API pembuatan pengiriman (Shipment):
1.  **Mobile Vendor**: Digunakan oleh aplikasi mobile vendor (hanya bisa kirim data vendor sendiri).
2.  **Web Admin/Backoffice**: Digunakan oleh dashboard admin (bisa kirim data atas nama vendor manapun).

---

## 1. Skenario: Mobile Vendor App
Endpoint ini digunakan app mobile. `vendor_id` otomatis diambil dari token login.

**Endpoint:**
`POST /api/mobile/vendor/shipments/store`

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer <VENDOR_TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "cooperative_id": 1,
    "tracking_number": "TRK-MBL-001",
    "notes": "Pengiriman dari Mobile App",
    "items": [
        {
            "procurement_item_id": 105,
            "quantity": 10
        }
    ]
}'
```

**Payload Details:**
*   `cooperative_id`: **Wajib**. ID Koperasi tujuan (Penerima).
*   `items`: Array item yang dikirim.
    *   `procurement_item_id`: ID Item Pengadaan.
    *   `quantity`: Jumlah yang dikirim (Jika tidak diisi, otomatis mengirim semua sisa barang).
*   `tracking_number`: Opsional.
*   `notes`: Catatan Opsional.
*   Field lokasi (`latitude`, `longitude`) dan `area_id` **TIDAK PERLU** dikirim jika tidak ada data GPS.

---

## 2. Skenario: Web Admin / Non-Mobile
Endpoint ini digunakan dashboard admin. `vendor_id` **wajib** dikirim untuk menentukan pengiriman ini milik vendor siapa.

**Endpoint:**
`POST /api/shipments/store`

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/shipments/store" \
  -H "Authorization: Bearer <ADMIN_TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "vendor_id": 5, 
    "cooperative_id": 1,
    "tracking_number": "TRK-ADM-999",
    "notes": "Pengiriman dibuat oleh Admin",
    "items": [
        {
            "procurement_item_id": 105,
            "quantity": 5
        }
    ]
}'
```

**Payload Details:**
*   `vendor_id`: **Wajib**. ID Vendor pemilik barang (Pengirim).
*   `cooperative_id`: **Wajib**. ID Koperasi tujuan (Penerima).
*   `items`: Array item yang dikirim.

---

## Response Success (Contoh)

```json
{
    "status": true,
    "message": "Shipment created",
    "data": {
        "id": 12,
        "vendor_id": 5,
        "cooperative_id": 1,
        "tracking_number": "TRK-MBL-001",
        "status": "pending",
        "cooperative": {
            "id": 1,
            "name": "Koperasi Maju Jaya"
        },
        "items": [
            {
                "id": 88,
                "shipment_id": 12,
                "procurement_item_id": 105,
                "quantity": 10
            }
        ]
    }
}
```
