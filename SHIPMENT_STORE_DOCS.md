# Shipment Store API Documentation

Dokumentasi ini mencakup dua skenario penggunaan API pembuatan pengiriman (Shipment):
1.  **Mobile Vendor**: Digunakan oleh aplikasi mobile vendor (hanya bisa kirim data vendor sendiri).
2.  **Web Admin/Backoffice**: Digunakan oleh dashboard admin (bisa kirim data atas nama vendor manapun).

## Endpoint List Unshipped Items (Preparation)
`GET /mobile/vendor/shipments/unshipped-items`

Endpoint ini mengelompokkan item berdasarkan **AREA** (bukan Koperasi).

---

## 1. Skenario: Mobile Vendor App

**Endpoint:**
`POST /api/mobile/vendor/shipments/store`

### cURL Example

```bash
curl -X POST "http://localhost:8000/api/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer <VENDOR_TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "area_id": 1,
    "tracking_number": "TRK-MBL-001",
    "notes": "Pengiriman ke Area Jawa Barat",
    "items": [
        {
            "procurement_item_id": 105,
            "quantity": 10
        }
    ]
}'
```

**Payload Details:**
*   `area_id`: **Wajib**. ID Area tujuan (misal: ID Area untuk Jawa Barat). Item yang dikirim harus berasal dari koperasi yang berlokasi di area ini.
*   `items`: Array item yang dikirim.
    *   `procurement_item_id`: ID Item Pengadaan.
    *   `quantity`: Jumlah yang dikirim.
*   `tracking_number`: Opsional.
*   `notes`: Catatan Opsional.

---

## 2. Skenario: Web Admin / Non-Mobile

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
    "area_id": 1,
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
*   `vendor_id`: **Wajib**. ID Vendor pemilik barang.
*   `area_id`: **Wajib**. ID Area tujuan.
