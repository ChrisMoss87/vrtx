# F1: Document Templates

## Overview
Template system for generating documents (proposals, contracts, letters) with merge fields from CRM data.

## Key Features
- Template builder with rich text
- Merge field insertion
- Conditional content blocks
- Multi-format output (PDF, DOCX)
- Template library
- Version control
- Team sharing

## Merge Field Types
- Contact fields
- Company fields
- Deal fields
- User fields
- Custom variables
- Date/math functions

## Database Additions
```sql
CREATE TABLE document_templates (id, name, category, content, merge_fields, output_format);
CREATE TABLE generated_documents (id, template_id, record_id, output_url, created_by);
```

## Components
- `TemplateBuilder.svelte`
- `MergeFieldPicker.svelte`
- `DocumentPreview.svelte`
- `TemplateLibrary.svelte`
