# Mobile Vendor API Documentation

Dokumentasi ini berisi daftar lengkap endpoint yang tersedia untuk aplikasi Mobile Vendor. Semua endpoint memerlukan Header Autorization.

## Base Configuration

- **Base URL**: `http://localhost:8000/api`
- **Auth Header**: `Authorization: Bearer {TOKEN}`

---

## 1. Dashboard & General

### Get Dashboard Statistics
`GET /mobile/vendor/dashboard`

Retrieves summary statistics for the dashboard.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/dashboard" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Reference: Production Attributes
`GET /mobile/vendor/production-attributes`

Retrieves list of production attributes (e.g. for selection updates).

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/production-attributes" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

---

## 2. Procurement Management

### List Procurements
`GET /mobile/vendor/procurements`

Retrieves a paginated list of procurements.
Optional Params: `search`, `status` (draft, processed, completed, cancelled), `per_page`.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/procurements?status=processed" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Show Procurement Detail
`GET /mobile/vendor/procurements/{id}`

Retrieves detailed information of a specific procurement.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/procurements/5" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### List Ready to Ship Items
`GET /mobile/vendor/ready-to-ship`

Retrieves items that are completed in production and ready for delivery.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/ready-to-ship" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Update Item Delivery Status
`PUT /mobile/vendor/procurements/{id}/delivery-status`

Updates the delivery status of a specific procurement **item** (ProcurementItem ID).
Values: `pending`, `prepared`, `shipped`, `delivered`.

```bash
curl -X PUT "http://localhost:8000/api/mobile/vendor/procurements/105/delivery-status" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "delivery_status": "prepared",
    "notes": "Barang sedang dikemas"
}'
```

### Update Item Process Status (Production)
`PUT /mobile/vendor/procurements/{id}/process-status`

Updates the production status and details of a specific procurement **item**.
Values: `pending`, `purchase`, `production`, `completed`.

```bash
curl -X PUT "http://localhost:8000/api/mobile/vendor/procurements/105/process-status" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "process_status": "production",
    "percentage": 50,
    "production_attribute_id": 1,
    "notes": "Mulai perakitan mesin"
}'
```

---

## 3. Shipment Management

### List Unshipped Items (Preparation)
`GET /mobile/vendor/shipments/unshipped-items`

Retrieves items ready to be shipped, grouped by Cooperative (Destination). Use this to select items for `store` shipment.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/shipments/unshipped-items" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Create Shipment (Store)
`POST /mobile/vendor/shipments/store`

Creates a new shipment for a specific cooperative.

```bash
curl -X POST "http://localhost:8000/api/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "cooperative_id": 1,
    "tracking_number": "TRK-001",
    "notes": "Pengiriman Batch 1",
    "items": [
        { "procurement_item_id": 105, "quantity": 10 }
    ]
}'
```

### List Shipments (History)
`GET /mobile/vendor/shipments`

Retrieves history of created shipments.
Optional Params: `search`, `status`, `per_page`.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/shipments" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Show Shipment Detail
`GET /mobile/vendor/shipments/{id}`

Retrieves details of a shipment.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/shipments/12" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Show Tracking History
`GET /mobile/vendor/shipments/{id}/trackings`

Retrieves location log history for a shipment.

```bash
curl -X GET "http://localhost:8000/api/mobile/vendor/shipments/12/trackings" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
```

### Update Shipment Status
`PUT /mobile/vendor/shipments/{id}/status`

Updates the main status of use shipment.
Values: `pending`, `prepared`, `shipped`, `delivered`, `received`, `returned`, `cancelled`.

```bash
curl -X PUT "http://localhost:8000/api/mobile/vendor/shipments/12/status" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "shipped",
    "tracking_number": "NEW-TRK-002",
    "notes": "Barang sudah naik truk",
    "latitude": -6.200,
    "longitude": 106.800
}'
```

### Track Shipment Location
`POST /mobile/vendor/shipments/{id}/track`

Adds a location update log to the shipment without changing its status.

```bash
curl -X POST "http://localhost:8000/api/mobile/vendor/shipments/12/track" \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "latitude": -6.300,
    "longitude": 106.900,
    "notes": "Posisi di tol gate"
}'
```
