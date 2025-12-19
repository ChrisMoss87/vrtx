# H7: Blueprint Mode (Auto CRM Setup)

## Overview
AI-powered CRM setup that analyzes uploaded business documents and automatically generates modules, fields, and pipelines.

## Key Features
- Upload documents (proposals, spreadsheets, emails)
- AI analyzes business entities and fields
- Auto-generate module structure
- Create pipelines from detected stages
- Import historical data
- Review and customize before applying

## Document Types Supported
- Spreadsheets (contacts, deals lists)
- Proposals and contracts
- Email exports
- Business process documents
- Existing CRM exports

## AI Capabilities
- Entity detection (contacts, companies, deals)
- Field inference from column headers
- Relationship mapping
- Stage detection from deal progression
- Data type inference

## Technical Requirements
- Document parsing (PDF, XLSX, CSV, DOCX)
- LLM for structure inference
- Preview and confirmation UI

## Database Additions
```sql
CREATE TABLE blueprint_sessions (id, user_id, documents, detected_structure, status);
CREATE TABLE blueprint_entities (id, session_id, entity_name, fields, sample_data);
CREATE TABLE blueprint_relationships (id, session_id, from_entity, to_entity, type);
```

## Components
- `BlueprintWizard.svelte`
- `DocumentUploader.svelte`
- `StructurePreview.svelte`
- `EntityEditor.svelte`
- `BlueprintApply.svelte`
