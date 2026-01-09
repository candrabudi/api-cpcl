# CPCL Document API Examples

This document contains cURL examples for testing the CPCL Document endpoints.

## Base URL
Replace `http://localhost:8000/api` with your actual development server URL.

## 1. List All CPCL Documents
Retrieves a paginated list of documents.

```bash
curl -X GET "http://localhost:8000/api/cpcl-documents" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**With Query Params (Search & Pagination):**
```bash
curl -X GET "http://localhost:8000/api/cpcl-documents?search=Bobotsari&per_page=10&status=draft" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 2. Get Document Detail
Retrieves detailed information including applicants and creator.

```bash
curl -X GET "http://localhost:8000/api/cpcl-documents/1/show" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 3. Create New Document
Create a new CPCL Document using the new structure.

```bash
curl -X POST "http://localhost:8000/api/cpcl-documents/store" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -d '{
        "title": "CPCL Bobotsari",
        "program_code": "BBS-0241",
        "cpcl_date": "2026-01-02",
        "prepared_by": 4,
        "notes": "Testing creation of CPCL document"
     }'
```
*Note: `year` and `cpcl_month` will be automatically derived from `cpcl_date`.*

## 4. Update Document
Update existing document fields.

```bash
curl -X PUT "http://localhost:8000/api/cpcl-documents/1/update" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -d '{
        "title": "CPCL Bobotsari Updated",
        "cpcl_date": "2026-02-15",
        "notes": "Updated note content"
     }'
```

## 5. Update Document Status
Specifically update the document status (draft, submitted, verified, approved, rejected).

```bash
curl -X PUT "http://localhost:8000/api/cpcl-documents/1/status" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -d '{
        "status": "submitted"
     }'
```

## 6. Delete Document (Soft Delete)
```bash
curl -X DELETE "http://localhost:8000/api/cpcl-documents/1/delete" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
