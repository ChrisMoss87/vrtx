# F2: E-Signatures

## Overview
Electronic signature functionality for contracts and agreements, either built-in or via DocuSign/HelloSign integration.

## Key Features
- Document upload or template generation
- Signature field placement
- Multiple signers with order
- Signing notifications
- Audit trail
- Status tracking
- Automatic record updates
- Expiration dates

## Integration Options
- Native e-signature
- DocuSign integration
- HelloSign integration
- PandaDoc integration

## Database Additions
```sql
CREATE TABLE signature_requests (id, document_id, deal_id, status, expires_at);
CREATE TABLE signature_signers (id, request_id, email, name, sign_order, signed_at);
CREATE TABLE signature_audit_log (id, request_id, event_type, ip_address, created_at);
```

## Components
- `SignatureRequestBuilder.svelte`
- `SignerManager.svelte`
- `SignatureStatus.svelte`
- `SignaturePad.svelte`
