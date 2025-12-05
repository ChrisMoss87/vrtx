# Phase 12: Import/Export & Data Management - Complete

## Overview

Phase 12 implements comprehensive data import and export functionality for all modules in the CRM system, enabling users to easily migrate data in and out of the platform.

## Features Implemented

### Import System

#### Backend
- **ImportController** (`backend/app/Http/Controllers/Api/DataManagement/ImportController.php`)
  - File upload and validation
  - Column mapping configuration
  - Import execution and progress tracking
  - Error reporting per row
  - Import cancellation

- **ImportEngine** (`backend/app/Services/Import/ImportEngine.php`)
  - Row-by-row validation with field type checking
  - Auto-mapping columns to fields based on header names
  - Value transformation (dates, numbers, booleans, etc.)
  - Duplicate handling (skip, update, create)
  - Default value application

- **FileParser** (`backend/app/Services/Import/FileParser.php`)
  - CSV parsing with auto-detect delimiter
  - Excel (XLSX/XLS) parsing using PhpSpreadsheet
  - Preview generation with headers and sample rows
  - Memory-efficient row streaming with generators

- **Jobs**
  - `ValidateImportJob` - Async validation with 10-minute timeout
  - `ProcessImportJob` - Async import execution with 1-hour timeout

- **Models**
  - `Import` - Import job tracking with status, progress, and error storage
  - `ImportRow` - Individual row status, mapped data, and errors

#### Frontend
- **ImportWizard** (`frontend/src/lib/components/import-wizard/ImportWizard.svelte`)
  - 5-step wizard: Upload → Map Columns → Options → Validate → Import
  - Progress tracking with polling
  - Completion notification

- **FileUploadStep** - Drag-and-drop file upload with preview
- **ColumnMappingStep** - Interactive column-to-field mapping with suggestions
- **ImportOptionsStep** - Duplicate handling, empty row skipping
- **ValidationStep** - Error display and row-level issues
- **ImportProgressStep** - Real-time progress tracking

### Export System

#### Backend
- **ExportController** (`backend/app/Http/Controllers/Api/DataManagement/ExportController.php`)
  - Export creation with field selection
  - Filter and sorting support
  - Template management (save/reuse export configurations)
  - File download with count tracking
  - Export expiration (7 days)

- **ProcessExportJob** (`backend/app/Jobs/ProcessExportJob.php`)
  - Async export processing with 1-hour timeout
  - CSV and XLSX generation using PhpSpreadsheet
  - Value formatting (dates, currencies, booleans, etc.)
  - Filter application

- **Models**
  - `Export` - Export job tracking with file path and download count
  - `ExportTemplate` - Reusable export configurations

#### Frontend
- **ExportBuilder** (`frontend/src/lib/components/export-builder/ExportBuilder.svelte`)
  - Field selection with select all/clear all
  - Filter builder for conditional exports
  - Format options (CSV/XLSX, date format, headers)
  - Record count preview

- **ExportFieldSelector** - Draggable field selection
- **ExportFilterBuilder** - Advanced filter conditions

### Integration Points

- **Records Page** (`frontend/src/routes/(app)/records/[moduleApiName]/+page.svelte`)
  - Import and Export buttons in toolbar
  - Links to `/records/[module]/import` and `/records/[module]/export`

- **Import/Export Route Pages**
  - `/records/[moduleApiName]/import/+page.svelte`
  - `/records/[moduleApiName]/export/+page.svelte`

## API Endpoints

### Import Routes
```
GET    /api/v1/imports/{moduleApiName}              - List imports
GET    /api/v1/imports/{moduleApiName}/template     - Get import template
POST   /api/v1/imports/{moduleApiName}/upload       - Upload file
GET    /api/v1/imports/{moduleApiName}/{id}         - Get import status
PUT    /api/v1/imports/{moduleApiName}/{id}/configure - Configure mapping
POST   /api/v1/imports/{moduleApiName}/{id}/validate  - Start validation
POST   /api/v1/imports/{moduleApiName}/{id}/execute   - Execute import
POST   /api/v1/imports/{moduleApiName}/{id}/cancel    - Cancel import
GET    /api/v1/imports/{moduleApiName}/{id}/errors    - Get error rows
DELETE /api/v1/imports/{moduleApiName}/{id}           - Delete import
```

### Export Routes
```
GET    /api/v1/exports/{moduleApiName}              - List exports
POST   /api/v1/exports/{moduleApiName}              - Create export
GET    /api/v1/exports/{moduleApiName}/{id}         - Get export status
GET    /api/v1/exports/{moduleApiName}/{id}/download - Download file
DELETE /api/v1/exports/{moduleApiName}/{id}         - Delete export
GET    /api/v1/exports/{moduleApiName}/templates    - List templates
POST   /api/v1/exports/{moduleApiName}/templates    - Create template
PUT    /api/v1/exports/{moduleApiName}/templates/{id} - Update template
DELETE /api/v1/exports/{moduleApiName}/templates/{id} - Delete template
POST   /api/v1/exports/{moduleApiName}/templates/{id}/export - Export from template
```

## Database Schema

### imports table
- `id`, `module_id`, `user_id`, `name`
- `file_name`, `file_path`, `file_type`, `file_size`
- `status` (pending, validating, validated, importing, completed, failed, cancelled)
- `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `skipped_rows`
- `column_mapping` (JSON), `import_options` (JSON), `validation_errors` (JSON)
- `error_message`, `started_at`, `completed_at`
- `created_at`, `updated_at`, `deleted_at`

### import_rows table
- `id`, `import_id`, `row_number`
- `original_data` (JSON), `mapped_data` (JSON)
- `status` (pending, success, failed, skipped)
- `record_id` (nullable, reference to created record)
- `errors` (JSON)
- `created_at`, `updated_at`

### exports table
- `id`, `module_id`, `user_id`, `name`
- `file_name`, `file_path`, `file_type`, `file_size`
- `status` (pending, processing, completed, failed, expired)
- `total_records`, `exported_records`
- `selected_fields` (JSON), `filters` (JSON), `sorting` (JSON), `export_options` (JSON)
- `error_message`, `started_at`, `completed_at`, `expires_at`
- `download_count`
- `created_at`, `updated_at`, `deleted_at`

### export_templates table
- `id`, `module_id`, `user_id` (nullable for shared)
- `name`, `description`
- `selected_fields` (JSON), `filters` (JSON), `sorting` (JSON), `export_options` (JSON)
- `default_file_type`, `is_shared`
- `created_at`, `updated_at`, `deleted_at`

### scheduled_data_jobs table (prepared for future)
- `id`, `module_id`, `user_id`, `name`
- `job_type` (import, export)
- `cron_expression`, `is_active`
- `job_config` (JSON)
- `source_type`, `source_config` (JSON) - for imports
- `destination_type`, `destination_config` (JSON) - for exports
- `last_run_at`, `next_run_at`
- `run_count`, `success_count`, `failure_count`
- `created_at`, `updated_at`, `deleted_at`

## Configuration

### Storage Disks (filesystems.php)
```php
'imports' => [
    'driver' => 'local',
    'root' => storage_path('app/imports'),
],
'exports' => [
    'driver' => 'local',
    'root' => storage_path('app/exports'),
],
```

### Required Package
```bash
composer require phpoffice/phpspreadsheet
```

## Import Process Flow

1. **Upload** - User uploads CSV/XLSX/XLS file
2. **Preview** - System parses headers and shows sample rows
3. **Map** - User maps file columns to module fields (with suggestions)
4. **Configure** - Set duplicate handling and other options
5. **Validate** - Async job validates all rows against field rules
6. **Review** - User reviews validation results and errors
7. **Execute** - Async job imports valid rows, creates records
8. **Complete** - Summary of successful, failed, and skipped rows

## Export Process Flow

1. **Configure** - User selects fields, filters, and format
2. **Start** - Export job is queued
3. **Process** - Async job generates file
4. **Download** - User downloads completed file (7-day expiry)

## Supported File Types

### Import
- CSV (comma, semicolon, tab, pipe delimited)
- XLSX (Excel 2007+)
- XLS (Excel 97-2003)

### Export
- CSV
- XLSX

## Field Type Handling

| Field Type | Import Transform | Export Format |
|------------|------------------|---------------|
| text | trim, normalize | as-is |
| number | parse numeric, remove symbols | formatted number |
| currency | parse, strip currency symbols | formatted with 2 decimals |
| percent | parse numeric | value + "%" |
| date | parse various formats → Y-m-d | configurable format |
| datetime | parse → Y-m-d H:i:s | configurable format |
| boolean | true/false/yes/no/1/0 | "Yes" / "No" |
| select | validate against options | as-is |
| multiselect | comma-separated → array | array → comma-separated |
| email | validate email format | as-is |
| url | validate URL format | as-is |

## Future Enhancements (Not Implemented)

1. **Scheduled Imports/Exports**
   - Database schema ready (`scheduled_data_jobs`)
   - Needs scheduler implementation and UI

2. **Import Sources**
   - SFTP, URL, Email attachment sources
   - Schema supports source configuration

3. **Export Destinations**
   - SFTP, Email, Webhook delivery
   - Schema supports destination configuration

4. **Import History UI**
   - List of past imports with re-run capability
   - Detailed error viewer

5. **Export History UI**
   - List of exports with download links
   - Progress tracking for in-progress exports

## Files Created/Modified

### Backend
- `app/Http/Controllers/Api/DataManagement/ImportController.php`
- `app/Http/Controllers/Api/DataManagement/ExportController.php`
- `app/Services/Import/ImportEngine.php`
- `app/Services/Import/FileParser.php`
- `app/Jobs/ValidateImportJob.php`
- `app/Jobs/ProcessImportJob.php`
- `app/Jobs/ProcessExportJob.php`
- `app/Models/Import.php`
- `app/Models/ImportRow.php`
- `app/Models/Export.php`
- `app/Models/ExportTemplate.php`
- `database/migrations/tenant/2025_12_05_100000_create_imports_table.php`
- `database/migrations/tenant/2025_12_05_100001_create_exports_table.php`
- `config/filesystems.php` (added imports/exports disks)
- `routes/tenant-api.php` (added import/export routes)

### Frontend
- `src/lib/api/imports.ts`
- `src/lib/api/exports.ts`
- `src/lib/components/import-wizard/` (6 components)
- `src/lib/components/export-builder/` (3 components)
- `src/routes/(app)/records/[moduleApiName]/import/+page.svelte`
- `src/routes/(app)/records/[moduleApiName]/export/+page.svelte`
- `src/routes/(app)/records/[moduleApiName]/+page.svelte` (added Import/Export buttons)

## Testing

To test the import/export functionality:

1. Navigate to any module's records page (e.g., `/records/contacts`)
2. Click "Import" to upload a CSV/Excel file
3. Follow the wizard to map columns and import data
4. Click "Export" to download records as CSV/Excel
5. Configure fields, filters, and format options

## Status: Complete

Phase 12 core functionality is complete. Import and export features are fully functional with proper error handling, async job processing, and a polished UI experience.
