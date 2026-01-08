# VENDOR API - CURL EXAMPLES

Dokumentasi lengkap untuk semua endpoint Vendor API.

**Base URL:** `http://localhost:8000/api/vendors`

**Middleware:** `auth` + `admin` (kecuali untuk endpoint vendor dashboard)

---

## 📋 Daftar Endpoint

| Method | Route | Deskripsi |
|--------|-------|-----------|
| `GET` | `/` | List semua vendor |
| `GET` | `/{id}/show` | Detail vendor |
| `POST` | `/store` | Buat vendor baru |
| `PUT` | `/{id}/update` | Update vendor |
| `DELETE` | `/{id}/delete` | Hapus vendor (soft delete) |
| `POST` | `/{id}/restore` | Restore vendor yang dihapus |
| `GET` | `/procurements/{vendorID}/show` | Vendor + summary procurement |
| `GET` | `/procurements/{vendorID}/{procurementID}/items` | Detail items procurement |

---

## 1️⃣ List All Vendors

**Endpoint:** `GET /api/vendors`

```bash
curl -X GET http://localhost:8000/api/vendors \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### Query Parameters (Optional):

#### **Search**
Cari berdasarkan: name, npwp, contact_person, email
```bash
curl -X GET "http://localhost:8000/api/vendors?search=maritim" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

#### **Filter by Area**
```bash
curl -X GET "http://localhost:8000/api/vendors?area_id=1" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

#### **Show Archived Only**
```bash
curl -X GET "http://localhost:8000/api/vendors?filter=archived" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

#### **Show All Including Archived**
```bash
curl -X GET "http://localhost:8000/api/vendors?show_archived=true" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

#### **Pagination**
```bash
curl -X GET "http://localhost:8000/api/vendors?per_page=50&page=2" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

#### **Kombinasi Filter**
```bash
curl -X GET "http://localhost:8000/api/vendors?search=jaya&area_id=1&per_page=20" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 2️⃣ Show Vendor Detail

**Endpoint:** `GET /api/vendors/{id}/show`

```bash
curl -X GET http://localhost:8000/api/vendors/1/show \
     -H "Authorization: Bearer YOUR_TOKEN"
```

**Response Example:**
```json
{
  "success": true,
  "message": "Vendor detail",
  "data": {
    "id": 1,
    "user_id": 5,
    "area_id": 1,
    "name": "PT Maritim Jaya",
    "npwp": "123456789012345",
    "contact_person": "Budi Santoso",
    "phone": "08123456789",
    "email": "vendor@maritim.com",
    "address": "Jl. Maritim No. 10, Jakarta Utara",
    "latitude": -6.17511,
    "longitude": 106.86503,
    "total_paid": 150000000,
    "deleted_at": null,
    "user": {
      "id": 5,
      "username": "pt_maritim_jaya",
      "email": "vendor@maritim.com",
      "role": "vendor"
    },
    "area": {
      "id": 1,
      "name": "Jakarta Utara"
    },
    "documents": [
      {
        "id": 1,
        "vendor_id": 1,
        "document_type_id": 1,
        "file_path": "uploads/vendor_docs/1/company_profile.pdf",
        "notes": "Company Profile",
        "document_type": {
          "id": 1,
          "name": "Company Profile"
        }
      }
    ]
  }
}
```

---

## 3️⃣ Create Vendor

**Endpoint:** `POST /api/vendors/store`

### Option A: Simple (JSON)

```bash
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
        "address": "Jl. Maritim No. 10, Jakarta Utara",
        "latitude": -6.17511,
        "longitude": 106.86503
     }'
```

### Option B: With Documents (Multipart)

```bash
curl -X POST http://localhost:8000/api/vendors/store \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -F "name=PT Maritim Jaya" \
     -F "area_id=1" \
     -F "email=vendor@maritim.com" \
     -F "npwp=123456789012345" \
     -F "contact_person=Budi Santoso" \
     -F "phone=08123456789" \
     -F "address=Jl. Maritim No. 10, Jakarta Utara" \
     -F "latitude=-6.17511" \
     -F "longitude=106.86503" \
     -F "documents[0][document_type_id]=1" \
     -F "documents[0][file]=@/path/to/company_profile.pdf" \
     -F "documents[0][notes]=Company Profile" \
     -F "documents[1][document_type_id]=2" \
     -F "documents[1][file]=@/path/to/npwp.pdf" \
     -F "documents[1][notes]=NPWP Document"
```

### Request Fields:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `area_id` | integer | ✅ Yes | ID area (must exist in areas table) |
| `name` | string | ✅ Yes | Nama vendor (max 255) |
| `npwp` | string | ❌ No | NPWP vendor (unik) |
| `contact_person` | string | ❌ No | Nama contact person (max 255) |
| `phone` | string | ❌ No | Nomor telepon (max 50) |
| `email` | string | ❌ No | Email vendor (max 100) |
| `address` | string | ❌ No | Alamat lengkap |
| `latitude` | numeric | ❌ No | Latitude (-90 to 90) |
| `longitude` | numeric | ❌ No | Longitude (-180 to 180) |
| `documents` | array | ❌ No | Array dokumen vendor |
| `documents[].document_type_id` | integer | ✅ Yes* | ID tipe dokumen (*jika documents ada) |
| `documents[].file` | file | ✅ Yes* | File dokumen (max 5MB) |
| `documents[].notes` | string | ❌ No | Catatan dokumen |

### Success Response:

```json
{
  "success": true,
  "message": "Vendor created",
  "data": {
    "username": "pt_maritim_jaya",
    "email": "vendor@maritim.com",
    "password": "Vendor1234!"
  }
}
```

**📝 Note:** 
- Sistem otomatis membuat akun user untuk vendor dengan password default: `Vendor1234!`
- Username dibuat otomatis dari nama vendor (lowercase, spasi diganti underscore)
- Jika email tidak diisi, akan auto-generate: `{username}@example.com`

---

## 4️⃣ Update Vendor

**Endpoint:** `PUT /api/vendors/{id}/update`

### Option A: Update Data (JSON)

```bash
curl -X PUT http://localhost:8000/api/vendors/1/update \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
        "name": "PT Maritim Jaya Updated",
        "area_id": 1,
        "email": "vendor_new@maritim.com",
        "npwp": "123456789012345",
        "contact_person": "Budi Santoso",
        "phone": "08123456789",
        "address": "Jl. Maritim Baru No. 15, Jakarta Utara",
        "latitude": -6.17511,
        "longitude": 106.86503
     }'
```

### Option B: Update with New Documents (Multipart)

```bash
curl -X PUT http://localhost:8000/api/vendors/1/update \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -F "name=PT Maritim Jaya Updated" \
     -F "area_id=1" \
     -F "email=vendor_new@maritim.com" \
     -F "npwp=123456789012345" \
     -F "contact_person=Budi Santoso" \
     -F "phone=08123456789" \
     -F "address=Jl. Maritim Baru No. 15, Jakarta Utara" \
     -F "latitude=-6.17511" \
     -F "longitude=106.86503" \
     -F "documents[0][document_type_id]=3" \
     -F "documents[0][file]=@/path/to/certificate.pdf" \
     -F "documents[0][notes]=Updated Certificate"
```

**📝 Note:** 
- Update email akan otomatis update email di tabel users juga
- Upload dokumen baru tidak menghapus dokumen lama
- NPWP harus unik (kecuali untuk vendor yang sama)

---

## 5️⃣ Delete Vendor (Archive)

**Endpoint:** `DELETE /api/vendors/{id}/delete`

Soft delete - data tidak benar-benar dihapus, hanya di-archive.

```bash
curl -X DELETE http://localhost:8000/api/vendors/1/delete \
     -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "message": "Vendor deleted"
}
```

**📝 Note:** 
- User account vendor juga ikut di-soft delete
- Data masih bisa di-restore menggunakan endpoint restore

---

## 6️⃣ Restore Vendor

**Endpoint:** `POST /api/vendors/{id}/restore`

Restore vendor yang sudah di-archive.

```bash
curl -X POST http://localhost:8000/api/vendors/1/restore \
     -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "message": "Vendor restored"
}
```

**📝 Note:** User account vendor juga ikut di-restore

---

## 7️⃣ Show Vendor with Procurements Summary

**Endpoint:** `GET /api/vendors/procurements/{vendorID}/show`

Melihat vendor beserta ringkasan semua procurement yang pernah dilakukan.

### Basic Request:
```bash
curl -X GET http://localhost:8000/api/vendors/procurements/1/show \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### Search by Procurement Number:
```bash
curl -X GET "http://localhost:8000/api/vendors/procurements/1/show?search=PO-2026" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### Response Example:
```json
{
  "success": true,
  "message": "Vendor procurement summary retrieved",
  "data": {
    "vendor": {
      "id": 1,
      "name": "PT Maritim Jaya",
      "npwp": "123456789012345",
      "contact_person": "Budi Santoso",
      "phone": "08123456789",
      "email": "vendor@maritim.com",
      "address": "Jl. Maritim No. 10, Jakarta Utara",
      "latitude": -6.17511,
      "longitude": 106.86503,
      "total_paid": 300000000
    },
    "procurements": [
      {
        "procurement_id": 1,
        "procurement_number": "PO-2026-001",
        "procurement_date": "2026-01-12",
        "total_spent": 150000000
      },
      {
        "procurement_id": 2,
        "procurement_number": "PO-2026-002",
        "procurement_date": "2026-01-15",
        "total_spent": 150000000
      }
    ]
  }
}
```

**📝 Note:**
- `total_paid` = total seluruh pembayaran dari semua procurement
- `total_spent` = total untuk procurement tertentu

---

## 8️⃣ Get Vendor Procurement Items Detail

**Endpoint:** `GET /api/vendors/procurements/{vendorID}/{procurementID}/items`

Melihat detail semua item dalam procurement tertentu untuk vendor tertentu.

```bash
curl -X GET http://localhost:8000/api/vendors/procurements/1/2/items \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### URL Parameters:
- `vendorID`: ID vendor
- `procurementID`: ID procurement

### Response Example:
```json
{
  "success": true,
  "message": "Procurement items retrieved",
  "data": {
    "procurement": {
      "id": 2,
      "procurement_number": "PO-2026-002",
      "procurement_date": "2026-01-12",
      "total_spent": 150000000
    },
    "items": [
      {
        "procurement_item_id": 5,
        "item_id": 1,
        "item_name": "Mesin Kapal 15PK",
        "cooperative": "Koperasi Mina Jaya",
        "quantity": 10,
        "unit_price": 15000000,
        "total_price": 150000000,
        "delivery_status": "pending",
        "process_status": "ordered",
        "status_logs": [
          {
            "old_delivery_status": "pending",
            "new_delivery_status": "shipped",
            "area_id": 1,
            "status_date": "2026-01-13",
            "changed_by": 1,
            "notes": "Barang dalam pengiriman"
          }
        ],
        "process_statuses": [
          {
            "status": "production",
            "production_start_date": "2026-01-10",
            "production_end_date": "2026-01-15",
            "area_id": 1,
            "changed_by": 5,
            "status_date": "2026-01-10",
            "notes": "Produksi dimulai"
          }
        ]
      }
    ]
  }
}
```

**📝 Note:**
- Response includes full tracking: delivery status + process status
- Empty array jika vendor tidak memiliki item di procurement tersebut

---

## 📊 Status Reference

### Delivery Status:
- `pending` - Menunggu pengiriman
- `shipped` - Dalam pengiriman
- `delivered` - Sudah sampai
- `cancelled` - Dibatalkan

### Process Status:
- `ordered` - Baru dipesan
- `production` - Dalam produksi
- `ready` - Siap kirim
- `completed` - Selesai

---

## ⚠️ Error Codes

| Code | Message | Cause |
|------|---------|-------|
| 400 | Invalid vendor id | ID bukan numerik |
| 400 | Vendor not found | Vendor tidak ditemukan |
| 404 | Archived vendor not found | Vendor yang di-archive tidak ditemukan |
| 404 | No items found for this vendor and procurement | Tidak ada item |
| 422 | Validation Error | Input tidak valid |
| 500 | Failed to create/update/delete vendor | Database error |

---

## 🔐 Authentication

Semua endpoint memerlukan:
1. **Authorization Header:** `Bearer YOUR_TOKEN`
2. **Role:** `admin` (kecuali endpoint vendor dashboard)

Get token dari login endpoint:
```bash
curl -X POST http://localhost:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"username": "admin", "password": "password"}'
```

---

## 💡 Tips

1. **Pagination:** Default 10 items, gunakan `?per_page=50` untuk lebih banyak
2. **Search:** Support multiple fields sekaligus (name, npwp, contact_person, email)
3. **Documents:** Max file size 5MB per dokumen
4. **Coordinates:** Latitude: -90 to 90, Longitude: -180 to 180
5. **Auto-Generated User:** Simpan password default yang dikembalikan saat create vendor
6. **Soft Delete:** Data vendor yang dihapus masih bisa di-restore

---

**📅 Last Updated:** 2026-01-08
