# CPCL API CURL EXAMPLES

Gunakan file ini sebagai referensi untuk melakukan request ke API. Pastikan Anda sudah login untuk mendapatkan `access_token`.

## 1. Authentication
### Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"username": "admin", "password": "password"}'
```

### Verify OTP (Jika Email Login Aktif)
```bash
curl -X POST http://localhost:8000/api/auth/verify-otp \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@example.com", "otp": "123456"}'
```

---

## 2. Profil & User Management
### Get Profile
```bash
curl -X GET http://localhost:8000/api/profile \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### Admin: List User
```bash
curl -X GET http://localhost:8000/api/admin-users \
     -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 3. Master Data
### Items (Barang)
*   **List Items**: `curl -X GET http://localhost:8000/api/items -H "Authorization: Bearer YOUR_TOKEN"`
*   **Create Item**:
    ```bash
    curl -X POST http://localhost:8000/api/items/store \
         -H "Authorization: Bearer YOUR_TOKEN" \
         -H "Content-Type: application/json" \
         -d '{"name": "Mesin Kapal 15PK", "item_type_id": 1, "unit": "Unit", "description": "Mesin tempel standar"}'
    ```

### Vendors
**📁 Dokumentasi lengkap tersedia di:** [`CURL_VENDOR.md`](./CURL_VENDOR.md)

**Quick Examples:**
```bash
# List all vendors
curl -X GET http://localhost:8000/api/vendors \
     -H "Authorization: Bearer YOUR_TOKEN"

# Create vendor
curl -X POST http://localhost:8000/api/vendors/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
        "name": "PT Maritim Jaya",
        "area_id": 1,
        "email": "vendor@maritim.com",
        "npwp": "123456789012345",
        "contact_person": "Budi Santoso",
        "phone": "08123456789",
        "address": "Jl. Maritim No. 10, Jakarta Utara"
     }'

# Show vendor detail
curl -X GET http://localhost:8000/api/vendors/1/show \
     -H "Authorization: Bearer YOUR_TOKEN"

# Update vendor
curl -X PUT http://localhost:8000/api/vendors/1/update \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"name": "PT Maritim Updated", "area_id": 1, "email": "new@maritim.com"}'

# Delete vendor (archive)
curl -X DELETE http://localhost:8000/api/vendors/1/delete \
     -H "Authorization: Bearer YOUR_TOKEN"

# Restore vendor
curl -X POST http://localhost:8000/api/vendors/1/restore \
     -H "Authorization: Bearer YOUR_TOKEN"
```

**Available Endpoints:**
- `GET /api/vendors` - List with filters (search, area_id, archived)
- `GET /api/vendors/{id}/show` - Vendor detail
- `POST /api/vendors/store` - Create vendor (+ documents)
- `PUT /api/vendors/{id}/update` - Update vendor
- `DELETE /api/vendors/{id}/delete` - Soft delete
- `POST /api/vendors/{id}/restore` - Restore archived
- `GET /api/vendors/procurements/{vendorID}/show` - Vendor + procurement summary
- `GET /api/vendors/procurements/{vendorID}/{procurementID}/items` - Procurement items detail

---

## 4. CPCL (Calon Penerima Calon Lokasi)
### Create Document
```bash
curl -X POST http://localhost:8000/api/cpcl-documents/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -d '{"document_number": "CPCL/2026/001", "document_date": "2026-01-08", "notes": "Pengusulan bantuan alat tangkap"}'
```

### List Archived Documents
```bash
curl -X GET "http://localhost:8000/api/cpcl-documents?filter=archived" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 5. Rapat Pleno (Plenary Meetings)
### Create Plenary Meeting
```bash
curl -X POST http://localhost:8000/api/plenary-meetings/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
        "meeting_title": "Rapat Penetapan Bantuan 2026",
        "meeting_date": "2026-01-10",
        "location": "Aula KKP",
        "items": [
            {
                "cooperative_id": 1,
                "item_id": 1,
                "package_quantity": 10,
                "location": "Wilayah A"
            }
        ]
     }'
```

---

## 6. Pengadaan (Procurements)
### Create Procurement (Input Harga)
```bash
curl -X POST http://localhost:8000/api/procurements/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
        "vendor_id": 1,
        "procurement_number": "PO-2026-001",
        "procurement_date": "2026-01-12",
        "items": [
            {
                "plenary_meeting_item_id": 1,
                "unit_price": 15000000
            }
        ]
     }'
```

---

## 7. Pengiriman (Shipments)
### Create Shipment (By Vendor or Admin)
```bash
curl -X POST http://localhost:8000/api/shipments/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
        "vendor_id": 1,
        "tracking_number": "TRACK-001",
        "items": [
            {
                "procurement_item_id": 1,
                "quantity": 5
            }
        ]
     }'
```

### Update Shipment Status
```bash
curl -X PUT http://localhost:8000/api/shipments/1/status \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"status": "shipped", "notes": "Sedang dalam perjalanan ke lokasi"}'
```

---

## 8. Vendor Dashboard
### Update Production Status
```bash
curl -X PUT http://localhost:8000/api/vendor/procurements/1/process-status \
     -H "Authorization: Bearer YOUR_TOKEN_VENDOR" \
     -H "Content-Type: application/json" \
     -d '{"process_status": "production", "notes": "Barang sedang diproduksi"}'
```

---

## 9. Budgeting
### List Annual Budgets
```bash
curl -X GET http://localhost:8000/api/annual-budgets \
     -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Tips
*   Gunakan `?per_page=50` untuk mengubah jumlah data per halaman.
*   Gunakan `?search=keyword` untuk melakukan pencarian global pada list.
*   Gunakan `?filter=archived` untuk melihat data yang sudah dihapus (soft-deleted).
