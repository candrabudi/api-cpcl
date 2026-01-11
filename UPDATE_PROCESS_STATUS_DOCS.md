# Update Process Status API Documentation

Endpoint ini digunakan oleh Vendor untuk memperbarui status proses produksi suatu item pengadaan.

**Endpoint:**
`PUT /api/mobile/vendor/procurements/{procurement_item_id}/process-status`

## cURL Example

**Catatan:** Angka `105` pada URL di bawah adalah **ID Item** (`procurement_item_id`), BUKAN ID Procurement header.

```bash
curl -X PUT "http://localhost:8000/api/mobile/vendor/procurements/105/process-status" \
  -H "Authorization: Bearer <VENDOR_TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "process_status": "production",
    "production_attribute_id": 1,
    "percentage": 50,
    "production_start_date": "2026-01-12",
    "area_id": 1,
    "notes": "Sedang dalam tahap perakitan awal"
}'
```

## Payload Parameters

| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `process_status` | String | **Yes** | Status baru. Values: `pending`, `purchase`, `production`, `completed`. |
| `production_attribute_id` | Integer | No | ID dari atribut produksi/tahapan (jika ada). |
| `percentage` | Integer | No | Persentase progres (0-100). Tidak boleh lebih kecil dari persentase sebelumnya. |
| `production_start_date` | Date | No | Format `YYYY-MM-DD`. |
| `production_end_date` | Date | No | Format `YYYY-MM-DD`. Harus setelah start status. |
| `area_id` | Integer | No | ID Area lokasi produksi (opsional). |
| `notes` | String | No | Catatan tambahan. |

## Notes
- Endpoint ini hanya valid untuk item dengan `process_type` = `production`.
- Jika `percentage` dikirim, nilainya divalidasi agar tidak turun dari record terakhir (progress tidak bisa mundur).
