# Phase 14: Search & Global Navigation - Complete

## Overview
Phase 14 implements a comprehensive global search and command palette system, enabling fast navigation and record discovery across all modules.

## Features Implemented

### 1. Global Search Backend
- **Full-text search** using PostgreSQL tsvector/tsquery
- **Search index** table for fast searching
- **Simple search** with ILIKE for partial matching
- **Search results grouped by module**
- **Relevance scoring** for result ordering

### 2. Search Index System
- **Automatic indexing** of module records
- **Searchable content** from text-based fields
- **Primary/secondary values** for display
- **Metadata storage** for additional context
- **Module-level reindexing** command

### 3. Search History & Suggestions
- **Search history logging** per user
- **Recent searches** displayed in command palette
- **Popular searches** aggregation
- **Recent unique searches** for suggestions
- **History clearing** functionality

### 4. Saved Searches
- **Save searches** with custom names
- **Pin searches** for quick access
- **Usage tracking** (count and last used)
- **Filter support** for module-specific searches

### 5. Command Palette (Cmd+K)
- **Global keyboard shortcut** (Cmd+K / Ctrl+K)
- **Quick actions** (create record, settings, profile, logout)
- **Module navigation** (jump to any module)
- **Instant search** as-you-type
- **Keyboard navigation** (up/down arrows, enter, escape)
- **Pinned saved searches** in quick access

## Files Created

### Backend - Migration
- `database/migrations/tenant/2025_12_05_140000_create_search_tables.php`
  - `search_history` table - Logs all searches
  - `saved_searches` table - User's saved searches
  - `search_index` table - Full-text search index with GIN indexes

### Backend - Models
- `app/Models/SearchIndex.php`
  - `indexRecord()` - Index a module record
  - `removeRecord()` - Remove from index
  - `reindexModule()` - Reindex entire module
  - `search()` - PostgreSQL full-text search
  - `simpleSearch()` - ILIKE partial matching

- `app/Models/SearchHistory.php`
  - `log()` - Log a search query
  - `getRecent()` - Get recent searches
  - `getRecentUnique()` - Get unique recent searches
  - `getPopular()` - Get popular searches
  - `clearOld()` - Clean up old history

- `app/Models/SavedSearch.php`
  - CRUD for saved searches
  - `togglePin()` - Pin/unpin search
  - `recordUsage()` - Track usage

### Backend - Controller
- `app/Http/Controllers/Api/SearchController.php`
  - `search()` - Full global search
  - `quickSearch()` - Instant search for as-you-type
  - `suggestions()` - Get search suggestions
  - `history()` - Get search history
  - `clearHistory()` - Clear user's history
  - `savedSearches()` - List saved searches
  - `saveSearch()` - Save a search
  - `deleteSavedSearch()` - Delete saved search
  - `togglePin()` - Toggle pin status
  - `reindex()` - Reindex search data (admin)
  - `commands()` - Get command palette data

### Frontend - API Client
- `src/lib/api/search.ts`
  - Full TypeScript interfaces
  - All search API methods

### Frontend - Components
- `src/lib/components/command-palette/CommandPalette.svelte`
  - Command palette modal
  - Keyboard shortcuts handling
  - Search results display
  - Module navigation
  - Quick actions

## API Endpoints

### Search
```
GET    /api/v1/search                      - Global search
GET    /api/v1/search/quick                - Quick search (as-you-type)
GET    /api/v1/search/suggestions          - Get suggestions
GET    /api/v1/search/history              - Get search history
DELETE /api/v1/search/history              - Clear history
GET    /api/v1/search/saved                - List saved searches
POST   /api/v1/search/saved                - Save a search
DELETE /api/v1/search/saved/{id}           - Delete saved search
POST   /api/v1/search/saved/{id}/toggle-pin - Toggle pin
POST   /api/v1/search/reindex              - Reindex (admin)
GET    /api/v1/search/commands             - Get command palette data
```

## Usage

### Opening Command Palette
Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux) from anywhere in the app.

### Searching
1. Open command palette
2. Start typing to search
3. Results appear instantly
4. Use up/down arrows to navigate
5. Press Enter to select

### Quick Navigation
1. Open command palette
2. Type module name (e.g., "contacts")
3. Select module to navigate

### Saving a Search
```javascript
await search.saveSearch({
  name: 'Active Contacts',
  query: 'status:active',
  module_api_name: 'contacts',
  is_pinned: true
});
```

### Reindexing Records
```javascript
// Reindex specific module
await search.reindex('contacts');

// Reindex all modules
await search.reindex();
```

## Search Index Architecture

The search system uses a dedicated `search_index` table that stores:
- **searchable_content**: Combined text from all searchable fields
- **primary_value**: Main display value (name, title)
- **secondary_value**: Secondary display (email, phone)
- **metadata**: Additional context for display

Records are indexed when created/updated. The index can be rebuilt using the reindex endpoint.

## PostgreSQL Full-Text Search

The system uses PostgreSQL's built-in full-text search with:
- `tsvector` for storing searchable content
- `tsquery` for query parsing
- GIN indexes for fast lookups
- `ts_rank` for relevance scoring

For simple partial matching (as-you-type), ILIKE queries are used on the primary_value field.

## Command Palette Features

### Actions Available
| Action | Shortcut | Description |
|--------|----------|-------------|
| Create Record | n | Open create record dialog |
| Search | / | Focus search input |
| Settings | , | Go to settings page |
| Profile | - | Go to profile page |
| Logout | - | Log out of application |

### Keyboard Shortcuts
| Key | Action |
|-----|--------|
| Cmd+K / Ctrl+K | Open command palette |
| ↑ / ↓ | Navigate items |
| Enter | Select item |
| Escape | Close palette |

## Integration Points

### Auto-indexing Records
To automatically index records on create/update, add an observer:

```php
// In AppServiceProvider or ModuleServiceProvider
ModuleRecord::observe(SearchIndexObserver::class);
```

### Custom Indexing
For complex fields or custom searchable content:

```php
SearchIndex::indexRecord($record);
```

## Testing
1. Open command palette with Cmd+K
2. Search for records by name
3. Navigate to modules
4. Check recent searches appear
5. Save and pin a search
6. Verify keyboard navigation works

## Next Steps (Phase 15)
Consider adding:
- Advanced search operators
- Search filters UI
- Search analytics dashboard
- Search result highlighting
- Auto-complete suggestions
- Fuzzy matching
