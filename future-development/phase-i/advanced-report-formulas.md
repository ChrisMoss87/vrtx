# I4: Advanced Report Formulas

## Overview
Excel-like formula capabilities in reports for calculated columns, custom metrics, and complex aggregations.

## Key Features
- Formula editor with autocomplete
- Calculated columns in reports
- Cross-module calculations
- Date/time functions
- Statistical functions
- Conditional logic (IF/CASE)
- Formula validation

## Formula Functions
**Math**: SUM, AVG, MIN, MAX, COUNT, MEDIAN, STDEV
**Date**: DATEDIFF, DATEADD, MONTH, YEAR, WEEKDAY, NOW
**Text**: CONCAT, LEFT, RIGHT, UPPER, LOWER, CONTAINS
**Logic**: IF, CASE, AND, OR, NOT, ISBLANK, ISNUMBER
**Lookup**: VLOOKUP, RELATED, PARENT, COUNT_RELATED

## Technical Requirements
- Formula parser
- Safe expression evaluator
- Function library
- Syntax highlighting
- Error handling
- Performance optimization

## Database Additions
```sql
-- Formulas stored in report column definitions
ALTER TABLE report_columns ADD COLUMN formula_expression TEXT;
ALTER TABLE report_columns ADD COLUMN is_calculated BOOLEAN DEFAULT FALSE;
CREATE TABLE saved_formulas (id, name, expression, description, created_by);
```

## Components
- `FormulaEditor.svelte`
- `FunctionPicker.svelte`
- `FormulaPreview.svelte`
- `SavedFormulaLibrary.svelte`
