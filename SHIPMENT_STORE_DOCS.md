# Shipment Store API Documentation

Dokumentasi ini mencakup dua skenario penggunaan API pembuatan pengiriman (Shipment):
1.  **Mobile Vendor**: Digunakan oleh aplikasi mobile vendor.
2.  **Web Admin/Backoffice**: Digunakan oleh dashboard admin.

## Konsep Pengiriman
Pengiriman tidak lagi terikat secara ketat dengan ID Koperasi atau Area di database level. Pengiriman hanya mencatat:
1.  **Vendor** (Pengirim)
2.  **Lokasi dropship** (Latitude & Longitude)
3.  **Daftar Barang**

Namun, untuk memudahkan user memilih barang, endpoint `listUnshippedItems` tetap mengelompokkan barang berdasarkan **Koperasi** asal barang tersebut.

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
    "tracking_number": "TRK-MBL-001",
    "notes": "Pengiriman ke titik koordinat",
    "latitude": -6.2088,
    "longitude": 106.8456,
    "items": [
        {
            "procurement_item_id": 105,
            "quantity": 10
        }
    ]
}'
```

**Payload Details:**
*   `latitude`: **Opsional**. Koordinat Lintang lokasi pengiriman.
*   `longitude`: **Opsional**. Koordinat Bujur lokasi pengiriman.
*   `items`: Array item yang dikirim.
    *   `procurement_item_id`: ID Item Pengadaan.
    *   `quantity`: Jumlah yang dikirim.
*   `tracking_number`: Opsional.
*   `notes`: Catatan Opsional.
*   **TIDAK ADA** field `cooperative_id` atau `area_id`.

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
    "tracking_number": "TRK-ADM-999",
    "notes": "Pengiriman dibuat oleh Admin",
    "latitude": -6.2088,
    "longitude": 106.8456,
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
*   `latitude` & `longitude`: Opsional.
