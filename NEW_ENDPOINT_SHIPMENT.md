# New Endpoint: List Unshipped Items Grouped by Cooperative

Endpoint ini digunakan untuk persiapan pengiriman. Vendor bisa melihat daftar Koperasi mana saja yang memiliki barang siap kirim, dan barang apa saja yang perlu dikirim.

## Request

- **URL**: `/api/mobile/vendor/shipments/unshipped-items`
- **Method**: `GET`
- **Auth**: Bearer Token (Vendor)

## Response Example

```json
{
    "status": true,
    "message": "Unshipped items grouped by cooperative",
    "data": [
        {
            "id": 1,
            "name": "Koperasi Mandiri",
            "code": "KOP-001",
            "address": "Jl. Contoh No 1",
            "phone": "0812345",
            "items": [
                {
                    "procurement_item_id": 10,
                    "procurement_number": "PROC-001",
                    "item_name": "Mesin Kapal",
                    "item_unit": "Unit",
                    "quantity_total": 10,
                    "quantity_shipped": 5,
                    "quantity_remaining": 5,
                    "delivery_status": "partially_shipped"
                }
            ]
        }
    ]
}
```

## How to Use via cURL

Run file `curl-examples/shipment_unshipped_items.sh`.
