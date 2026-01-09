# Vendor Mobile API - Comprehensive Documentation

This file contains all cURL commands for the Vendor Mobile Application. 

**Base URL**: `http://localhost:8000`  
**Authentication**: Required `Authorization: Bearer YOUR_TOKEN` header.  
**Security**: Access restricted to users with `role: vendor`.

---

## 1. Dashboard & Statistics
Overview of the vendor's performance and task counts.

```bash
# Get Summary Counts
curl -X GET "http://localhost:8000/mobile/vendor/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## 2. Procurement Management (Pesanan)
Management of items assigned to the vendor for procurement or production.

### List All Procurements
```bash
# Paginated list with filtering
curl -X GET "http://localhost:8000/mobile/vendor/procurements?per_page=10&process_status=production" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Show Procurement Detail
```bash
curl -X GET "http://localhost:8000/mobile/vendor/procurements/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Update Process Status (Mulai/Selesai Produksi)
Used to track the manufacturing steps.
```bash
curl -X PUT "http://localhost:8000/mobile/vendor/procurements/1/process-status" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "process_status": "production",
    "production_start_date": "2024-01-10",
    "notes": "Sedang dalam tahap perakitan unit"
  }'
```

---

## 3. Shipment Management (Pengiriman)
Managing the physical delivery of items.

### List Shipments (Riwayat Kirim)
```bash
curl -X GET "http://localhost:8000/mobile/vendor/shipments" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Show Shipment Detail
```bash
curl -X GET "http://localhost:8000/mobile/vendor/shipments/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Create New Shipment (Kirim Barang Baru)
```bash
curl -X POST "http://localhost:8000/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tracking_number": "RESI-123456789",
    "notes": "Pengiriman mesin jaring tahap 1",
    "items": [
        {
            "procurement_item_id": 1,
            "quantity": 5
        }
    ]
  }'
```

### Update Shipment Status (Update Resi/Status)
```bash
curl -X PUT "http://localhost:8000/mobile/vendor/shipments/1/status" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "shipped",
    "tracking_number": "RESI-123456789-UPDATED",
    "notes": "Barang sudah di kurir JNE"
  }'
```

---

## 4. Master Data (Utility)

### Search Master Items
To see if an item is `purchase` or `production`.
```bash
curl -X GET "http://localhost:8000/items?search=Jaring&process_type=production" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```
