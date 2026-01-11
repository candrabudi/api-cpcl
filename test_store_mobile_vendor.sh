#!/bin/bash
source "./_curl_env.sh"

echo -e "${BLUE}1. TEST: Store Shipment (Mobile Vendor) - By AREA${NC}"
echo -e "${GREEN}Endpoint: POST ${BASE_URL}/mobile/vendor/shipments/store${NC}"

# DATA CONTOH
AREA_ID=1
ITEM_ID=105

curl -s -X POST "${BASE_URL}/mobile/vendor/shipments/store" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"area_id\": ${AREA_ID},
    \"tracking_number\": \"MBL-AREA-$(date +%s)\",
    \"notes\": \"Shipment ke Area via Mobile Vendor\",
    \"items\": [
        { \"procurement_item_id\": ${ITEM_ID} }
    ]
}" | python3 -m json.tool

echo -e "\n${BLUE}Done.${NC}"
