# VRTX CRM Potentially Redundant Files

## Overview
This document identifies potentially redundant, unused, or duplicate files that may be candidates for removal or consolidation.

---

## High Priority (Address Soon)

### 1. Duplicate Field Type Constants
**Issue:** Multiple conflicting field type definitions across 3 files.

| File | Lines | Status |
|------|-------|--------|
| `frontend/src/lib/constants/fieldTypes.ts` | 534 | KEEP (most comprehensive) |
| `frontend/src/lib/constants/field-types.ts` | 368 | REMOVE |
| `frontend/src/lib/types/field-types.ts` | 352 | REVIEW |

**Risk:** HIGH - Multiple definitions can cause inconsistencies.

**Action:**
```bash
# Check usage before removing
grep -r "from.*field-types" frontend/src/
grep -r "from.*constants/field-types" frontend/src/

# If no imports, remove:
rm frontend/src/lib/constants/field-types.ts
```

---

### 2. Environment Backup Files
**Issue:** Backup .env file committed to repository.

| File | Risk |
|------|------|
| `backend/.env.backup` | Security risk |

**Action:**
```bash
rm backend/.env.backup
echo ".env.backup" >> backend/.gitignore
```

---

## Medium Priority

### 3. Duplicate Formula Calculator
**Issue:** Two formula calculator implementations.

| File | Lines | Used By |
|------|-------|---------|
| `frontend/src/lib/utils/formulaCalculator.ts` | 527 | Re-exported, most comprehensive |
| `frontend/src/lib/form-logic/formulaCalculator.ts` | 331 | DynamicForm.svelte only |

**Risk:** MEDIUM - Code duplication, potential divergence.

**Action:**
1. Update `DynamicForm.svelte` to import from `$lib/utils/formulaCalculator`
2. Remove `frontend/src/lib/form-logic/formulaCalculator.ts`

---

### 4. Duplicate DND Utilities
**Issue:** Two drag-and-drop utility files.

| File | Lines | Purpose |
|------|-------|---------|
| `frontend/src/lib/utils/dnd.svelte.ts` | 484 | Full Svelte 5 DND implementation |
| `frontend/src/lib/utils/dnd.ts` | 108 | Legacy utilities, `generateId` only |

**Risk:** MEDIUM - Confusing, potential import errors.

**Action:**
1. Move `generateId` function to `dnd.svelte.ts` or separate utility
2. Update imports in `form-builder.ts` store
3. Remove `dnd.ts`

---

### 5. Unused Backend Model
**Issue:** `ScheduledDataJob` model not referenced anywhere.

| File | Status |
|------|--------|
| `backend/app/Models/ScheduledDataJob.php` | Orphaned |

**Risk:** MEDIUM - Dead code.

**Action:**
```bash
# Verify no usage
grep -r "ScheduledDataJob" backend/app/
grep -r "scheduled_data_jobs" backend/database/

# If no results, remove
rm backend/app/Models/ScheduledDataJob.php
```

---

## Low Priority (Demo/Test Files)

### 6. Demo Routes - Frontend
**Issue:** 9 demo/test routes in production build.

| Route | Purpose |
|-------|---------|
| `(app)/datatable-demo/` | DataTable feature demo |
| `(app)/field-types-demo/` | Field type showcase |
| `(app)/wizard-demo/` | Wizard component demo |
| `(app)/wizard-builder-demo/` | Wizard builder demo |
| `(app)/conditional-wizard-demo/` | Conditional wizard demo |
| `(app)/step-types-demo/` | Step types showcase |
| `(app)/draft-demo/` | Draft functionality demo |
| `(app)/editor-demo/` | Editor component demo |
| `(app)/test-form/` | Form testing page |

**Risk:** LOW - Clutter, not a bug.

**Options:**
1. Move to `/dev/` route group (exclude from production)
2. Add environment check to route guards
3. Remove entirely after documenting features

---

### 7. Root Demo Routes
**Issue:** Prototype/design routes at root level.

| Route | Purpose |
|-------|---------|
| `/demo/` | Responsive modal demo |
| `/otp-01/` | OTP form design |
| `/sidebar-12/` | Sidebar design v12 |
| `/sidebar-15/` | Sidebar design v15 |

**Risk:** LOW - Prototype pages.

**Action:** Remove after confirming designs are finalized.

---

### 8. Test Files
**Issue:** Placeholder test files.

| File | Content |
|------|---------|
| `frontend/src/demo.spec.ts` | Basic "sum test" example |
| `frontend/test-wizard.py` | Python test script |

**Risk:** LOW - Unused demo tests.

**Action:**
```bash
rm frontend/src/demo.spec.ts
rm frontend/test-wizard.py
```

---

### 9. Example Components
**Issue:** Unused example component.

| File | Status |
|------|--------|
| `frontend/src/lib/components/examples/responsive-modal-example.svelte` | Not imported |

**Risk:** LOW - Unused code.

**Action:** Move to documentation or remove.

---

### 10. Wizard Conditional Logic
**Issue:** Isolated file in separate directory.

| File | Status |
|------|--------|
| `frontend/src/lib/wizard/conditionalLogic.ts` | Unclear purpose |

**Risk:** LOW - May be incomplete feature.

**Action:** Investigate purpose, consolidate or remove.

---

## Generated Files (Should be in .gitignore)

| File | Type |
|------|------|
| `backend/.phpunit.result.cache` | PHPUnit cache |

**Action:**
```bash
echo ".phpunit.result.cache" >> backend/.gitignore
```

---

## Cleanup Script

```bash
#!/bin/bash
# cleanup-redundant.sh

echo "=== VRTX CRM Redundant Files Cleanup ==="

# High Priority
echo "Removing duplicate field-types..."
rm -f frontend/src/lib/constants/field-types.ts

echo "Removing env backup..."
rm -f backend/.env.backup

# Medium Priority
echo "Removing duplicate formula calculator..."
rm -f frontend/src/lib/form-logic/formulaCalculator.ts

echo "Removing legacy dnd.ts..."
rm -f frontend/src/lib/utils/dnd.ts

# Low Priority (Demo files) - Uncomment to execute
# echo "Removing demo routes..."
# rm -rf frontend/src/routes/\(app\)/datatable-demo
# rm -rf frontend/src/routes/\(app\)/field-types-demo
# rm -rf frontend/src/routes/\(app\)/wizard-demo
# rm -rf frontend/src/routes/\(app\)/wizard-builder-demo
# rm -rf frontend/src/routes/\(app\)/conditional-wizard-demo
# rm -rf frontend/src/routes/\(app\)/step-types-demo
# rm -rf frontend/src/routes/\(app\)/draft-demo
# rm -rf frontend/src/routes/\(app\)/editor-demo
# rm -rf frontend/src/routes/\(app\)/test-form

# Root demos
# rm -rf frontend/src/routes/demo
# rm -f frontend/src/routes/otp-01/+page.svelte
# rm -f frontend/src/routes/sidebar-12/+page.svelte
# rm -f frontend/src/routes/sidebar-15/+page.svelte

# Test files
# rm -f frontend/src/demo.spec.ts
# rm -f frontend/test-wizard.py

# Example components
# rm -rf frontend/src/lib/components/examples

echo "=== Cleanup Complete ==="
```

---

## Summary

| Priority | Count | Category |
|----------|-------|----------|
| HIGH | 2 | Duplicate constants, security |
| MEDIUM | 3 | Code duplication, orphaned code |
| LOW | 5 | Demo pages, test files |

**Total files for review:** ~20 files/directories

**Estimated disk space to reclaim:** Minimal (mostly small files)

**Primary benefit:** Cleaner codebase, reduced confusion, easier maintenance.
