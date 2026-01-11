#!/bin/bash
source "./_curl_env.sh"

echo -e "${BLUE}1. TEST: Store Shipment (Mobile Vendor) - GPS ONLY${NC}"
echo -e "${GREEN}Endpoint: POST ${BASE_URL}/mobile/vendor/shipments/store${NC}"

# DATA CONTOH
ITEM_ID=105

curl -s -X POST "${BASE_URL}/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"tracking_number\": \"MBL-GPS-$(date +%s)\",
    \"notes\": \"Shipment dengan koordinat GPS\",
    \"latitude\": -6.2088,
    \"longitude\": 106.8456,
    \"items\": [
        { \"procurement_item_id\": ${ITEM_ID} }
    ]
}" | python3 -m json.tool

echo -e "\n${BLUE}Done.${NC}"
