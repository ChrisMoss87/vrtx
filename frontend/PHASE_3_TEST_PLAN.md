# Phase 3: Dynamic Form Renderer - Test Plan

**Test URL**: http://techco.vrtx.local/test-form (or http://localhost:5174/test-form)

## Overview

This test plan validates that the Dynamic Form Renderer correctly handles all 21 field types, validation, submission, and conditional visibility.

---

## Test Categories

### 1. Visual Rendering Test ✅

**Objective**: Verify all field types render correctly without errors

**Steps**:

1. Navigate to http://techco.vrtx.local/test-form
2. Verify page loads without console errors
3. Verify all sections are visible:
   - Basic Text Fields
   - Numeric Fields
   - Selection Fields
   - Other Field Types

**Expected Result**: All fields render, no JavaScript errors in console

---

### 2. Field Input Capture Test

**Objective**: Verify all 21 field types can accept and capture user input

**Test each field type**:

#### Basic Text Fields

- [ ] **Text Field** - Enter text (min 3 chars required)
- [ ] **Email** - Enter valid email (required)
- [ ] **Phone** - Enter phone number
- [ ] **Website (URL)** - Enter URL
- [ ] **Textarea** - Enter multi-line text

#### Numeric Fields

- [ ] **Number** - Enter whole number
- [ ] **Decimal** - Enter decimal number
- [ ] **Currency** - Enter currency value
- [ ] **Percentage** - Enter percentage value

#### Date/Time Fields

- [ ] **Date** - Select date
- [ ] **DateTime** - Select date and time
- [ ] **Time** - Select time

#### Selection Fields

- [ ] **Select (Dropdown)** - Select single option
- [ ] **Multiselect** - Select multiple options
- [ ] **Radio** - Select one radio option
- [ ] **Checkbox** - Check/uncheck
- [ ] **Toggle** - Toggle on/off

#### Advanced Fields

- [ ] **Lookup** - Search and select (uses static options in test)
- [ ] **Formula** - Displays calculated result (read-only)
- [ ] **File Upload** - Select file(s)
- [ ] **Image Upload** - Select image(s)
- [ ] **Rich Text** - Enter formatted text

**Expected Result**: All fields accept input and display entered values

---

### 3. Validation Test

**Objective**: Verify required field validation works

**Steps**:

1. Leave required fields empty:
   - Text Field (required, min 3 chars)
   - Email (required)
   - Agreed to Terms (required checkbox)
2. Click "Save" button
3. Verify error messages appear under required fields
4. Fill in required fields
5. Verify error messages disappear

**Expected Result**:

- Empty required fields show error messages
- Filled required fields clear error messages
- Form cannot submit with validation errors

---

### 4. Form Submission Test

**Objective**: Verify form data is captured and submitted correctly

**Steps**:

1. Fill out all fields with test data
2. Click "Save" button
3. Verify submit handler is called
4. Check that "Form Data Submitted" card appears
5. Verify all entered data appears in the JSON output

**Expected Result**:

- Submit button triggers form submission
- All field values are captured in the data object
- Submitted data displays in green card below form
- Data structure matches field api_names

**Sample Expected Output**:

```json
{
	"text_field": "Test text",
	"email": "test@example.com",
	"phone": "+1 555-1234",
	"website": "https://example.com",
	"description": "Multi-line text",
	"age": 25,
	"price": 99.99,
	"salary": 75000,
	"discount": 15,
	"start_date": "2025-11-28",
	"appointment": "2025-11-28T14:30",
	"meeting_time": "14:30",
	"status": "active",
	"tags": ["important", "urgent"],
	"priority": "high",
	"terms_agreed": true,
	"email_notifications": true,
	"terms_date": "2025-11-28"
}
```

---

### 5. Conditional Visibility Test

**Objective**: Verify fields show/hide based on conditions

**Test Setup**:

- Field: "Terms Acceptance Date" (date field)
- Condition: Only visible when "Agreed to Terms" checkbox is checked

**Steps**:

1. Scroll to "Agreed to Terms" checkbox
2. Verify "Terms Acceptance Date" field is NOT visible
3. Check "Agreed to Terms" checkbox
4. Verify "Terms Acceptance Date" field becomes visible
5. Uncheck "Agreed to Terms" checkbox
6. Verify "Terms Acceptance Date" field disappears again

**Expected Result**:

- Conditional field hidden by default
- Checking checkbox makes field appear
- Unchecking checkbox hides field again
- Hidden field data not included in submission

---

### 6. Browser Compatibility Test

**Objective**: Verify form works across browsers

**Browsers to Test**:

- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari (if available)
- [ ] Edge

**Expected Result**: Form renders and functions correctly in all browsers

---

### 7. Responsive Design Test

**Objective**: Verify form layout adapts to screen sizes

**Steps**:

1. Test on desktop (>1024px)
2. Test on tablet (768px - 1024px)
3. Test on mobile (<768px)
4. Verify 2-column fields stack to 1 column on mobile

**Expected Result**: Form is usable on all screen sizes

---

## Test Execution Checklist

### Pre-Test Setup

- [x] Dev server running (`./dev.sh` or `pnpm dev`)
- [x] No TypeScript errors (`pnpm check`)
- [x] No console errors on page load
- [x] Test page accessible at http://techco.vrtx.local/test-form

### Core Functionality Tests

- [ ] All 21 field types render
- [ ] All fields accept input
- [ ] Required field validation works
- [ ] Form submission captures all data
- [ ] Conditional visibility works
- [ ] No browser console errors during interaction

### Quality Tests

- [ ] Responsive layout works
- [ ] Accessibility (keyboard navigation, ARIA labels)
- [ ] Error states display clearly
- [ ] Loading states show during submission

---

## Test Results

### Date Tested: **\*\***\_**\*\***

### Tested By: **\*\***\_**\*\***

### Browser: **\*\***\_**\*\***

### Result: ☐ PASS / ☐ FAIL

### Issues Found:

1.
2.
3.

### Notes:

---

## Acceptance Criteria

Phase 3 can be marked COMPLETE when:

✅ **Must Have**:

1. All 21 field types render without errors
2. All fields can accept and display user input
3. Required field validation works
4. Form submission captures all field data correctly
5. Conditional visibility works (field shows/hides based on condition)
6. No TypeScript compilation errors
7. No browser console errors

☐ **Should Have** (Future enhancements):

- Formula calculator integration (auto-calculates based on other fields)
- File upload with preview
- Image upload with preview
- Rich text editor toolbar
- Date picker component (currently using HTML5 input)
- Lookup field with API integration

☐ **Nice to Have**:

- Field-level permissions
- Custom validation messages
- Field help tooltips
- Inline editing
- Auto-save functionality

---

## Known Limitations

1. **Formula Fields**: Created but formula calculator not integrated yet (displays static value)
2. **File/Image Upload**: Basic HTML file input (no preview or upload to server)
3. **Lookup Fields**: Uses static options (no API integration for dynamic data)
4. **Date Fields**: Uses HTML5 date input (not custom date picker component)
5. **Rich Text**: Basic contenteditable (no toolbar or formatting controls)

These limitations are documented and acceptable for Phase 3 completion. They can be enhanced in future phases.

---

**Document Version**: 1.0
**Last Updated**: November 28, 2025
**Status**: Ready for Testing
