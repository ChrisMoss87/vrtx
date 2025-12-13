# B2: Revenue Intelligence Graph

## Overview

A visual, interactive network graph showing relationships between contacts, companies, deals, and revenue flow. This unique feature provides insight into business networks that list-based CRMs cannot offer.

## User Stories

1. As a sales rep, I want to visualize how contacts are connected to identify warm introductions
2. As an executive, I want to see revenue concentration and customer relationships
3. As an account manager, I want to understand the decision-maker network at key accounts
4. As a marketer, I want to visualize referral chains and attribution

## Feature Requirements

### Core Functionality
- [ ] Interactive network visualization
- [ ] Multiple node types (contacts, companies, deals)
- [ ] Relationship lines with types (works at, referred by, influenced, etc.)
- [ ] Revenue flow visualization (deal value as edge thickness)
- [ ] Filtering by relationship type, date range, revenue
- [ ] Zoom and pan navigation
- [ ] Node clustering for large datasets
- [ ] Click to expand relationships
- [ ] Search and highlight paths

### Node Types
- **Contacts** - People (size by influence/deal involvement)
- **Companies** - Organizations (size by revenue)
- **Deals** - Opportunities (color by stage, size by value)
- **Users** - Internal team members

### Relationship Types
- Works at (contact → company)
- Reports to (contact → contact)
- Referred by (contact → contact)
- Influenced (contact → deal)
- Decision maker (contact → deal)
- Partner (company → company)
- Parent company (company → company)

### Visualization Features
- Force-directed layout
- Hierarchical layout option
- Revenue heat map overlay
- Temporal animation (show network growth over time)
- Path finding between nodes
- Cluster detection (identify groups)

## Technical Requirements

### Database Schema

```sql
-- Explicit relationships beyond lookups
CREATE TABLE entity_relationships (
    id SERIAL PRIMARY KEY,
    from_entity_type VARCHAR(50) NOT NULL, -- 'contact', 'company', 'deal', 'user'
    from_entity_id INTEGER NOT NULL,
    to_entity_type VARCHAR(50) NOT NULL,
    to_entity_id INTEGER NOT NULL,
    relationship_type VARCHAR(50) NOT NULL,
    strength INTEGER DEFAULT 1, -- 1-10 relationship strength
    metadata JSONB DEFAULT '{}',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(from_entity_type, from_entity_id, to_entity_type, to_entity_id, relationship_type)
);

-- Indexes for graph queries
CREATE INDEX idx_rel_from ON entity_relationships(from_entity_type, from_entity_id);
CREATE INDEX idx_rel_to ON entity_relationships(to_entity_type, to_entity_id);
CREATE INDEX idx_rel_type ON entity_relationships(relationship_type);

-- Precomputed graph metrics (updated periodically)
CREATE TABLE graph_metrics (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER NOT NULL,
    degree_centrality DECIMAL(10,6),
    betweenness_centrality DECIMAL(10,6),
    closeness_centrality DECIMAL(10,6),
    cluster_id INTEGER,
    total_connected_revenue DECIMAL(15,2),
    calculated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(entity_type, entity_id)
);
```

### Backend Components

**Services:**
- `GraphService` - Build and query relationship graph
- `GraphMetricsService` - Calculate centrality, clusters
- `PathFindingService` - Find shortest paths between nodes

**Jobs:**
- `CalculateGraphMetricsJob` - Periodic metrics recalculation
- `InferRelationshipsJob` - Auto-detect relationships from data

**API Endpoints:**
```
GET /api/v1/graph/nodes
    ?entity_types=contact,company,deal
    &filters[min_revenue]=10000
    &limit=500
    # Returns nodes for visualization

GET /api/v1/graph/edges
    ?relationship_types=works_at,referred_by
    &node_ids=1,2,3,4,5
    # Returns edges between specified nodes

GET /api/v1/graph/neighborhood/{type}/{id}
    ?depth=2
    # Returns nodes and edges within N hops

GET /api/v1/graph/path
    ?from_type=contact&from_id=123
    &to_type=contact&to_id=456
    # Find shortest path between nodes

GET /api/v1/graph/clusters
    # Returns detected clusters

GET /api/v1/graph/metrics/{type}/{id}
    # Returns centrality metrics for entity

POST /api/v1/graph/relationships
    # Create manual relationship

DELETE /api/v1/graph/relationships/{id}
    # Remove relationship
```

### Frontend Components

**New Components:**
- `RevenueGraph.svelte` - Main graph visualization
- `GraphCanvas.svelte` - D3/Cytoscape rendering
- `GraphNode.svelte` - Node component with details
- `GraphEdge.svelte` - Edge with label
- `GraphControls.svelte` - Zoom, filter, layout controls
- `GraphLegend.svelte` - Node/edge type legend
- `GraphSearch.svelte` - Search and highlight
- `NodeDetailsPanel.svelte` - Sidebar with node info
- `PathHighlighter.svelte` - Highlight path between nodes
- `RelationshipEditor.svelte` - Add/edit relationships

**Libraries:**
```bash
pnpm add d3 @types/d3
# or
pnpm add cytoscape
```

**New Routes:**
- `/insights/graph` - Revenue graph page
- Modal view from record pages

## UI/UX Design

### Main Graph View
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🕸️ Revenue Intelligence Graph              [Filter ▼] [Layout ▼]   │
├─────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────┐ ┌─────┐ │
│ │                                                         │ │Node │ │
│ │                    ○ Sarah                              │ │Info │ │
│ │                   /    \                                │ │     │ │
│ │                  /      \                               │ ├─────┤ │
│ │     ○ Mike ────●Acme Corp●──── ○ Lisa                  │ │Acme │ │
│ │                 │ $2.5M  │                              │ │Corp │ │
│ │                 │        │                              │ │     │ │
│ │                 ◆        ◆                              │ │Rev: │ │
│ │           Deal A    Deal B                              │ │$2.5M│ │
│ │           $500k     $750k                               │ │     │ │
│ │              \        /                                 │ │12   │ │
│ │               \      /                                  │ │deals│ │
│ │                ○ John (You)                             │ │     │ │
│ │                                                         │ └─────┘ │
│ └─────────────────────────────────────────────────────────┘         │
│ Legend: ○ Contact  ● Company  ◆ Deal                               │
│ Revenue flow: ─── low  ═══ medium  ▬▬▬ high                        │
└─────────────────────────────────────────────────────────────────────┘
```

### Controls
```
┌─────────────────────────────────────────────────────────────────────┐
│ Filters:                                                            │
│ [✓] Contacts  [✓] Companies  [✓] Deals  [ ] Users                  │
│ Revenue: [$10k ━━━━━━━━━━━━━━━●━━━━━━ $1M+]                        │
│ Relationships: [All ▼]                                              │
│                                                                     │
│ Layout: [Force ▼]  Zoom: [−] ━━●━━ [+]                             │
│                                                                     │
│ Search: [Find connection between... ]  [Find Path]                 │
└─────────────────────────────────────────────────────────────────────┘
```

### Path Finding Result
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🔗 Path: You → Target Contact (3 hops)                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  You ─────► Sarah Chen ─────► Acme Corp ─────► Target Contact      │
│        worked on         works at         works at                 │
│        Deal #123                                                   │
│                                                                     │
│  This is the shortest path. 2 alternate paths found.               │
│  [Request Introduction] [Add to Sequence]                          │
└─────────────────────────────────────────────────────────────────────┘
```

## Performance Considerations

- Limit initial node count (500 max)
- Progressive loading on zoom
- Server-side clustering for large datasets
- WebGL rendering for 1000+ nodes
- Cache graph data client-side
- Debounce filter changes

## Testing Requirements

- [ ] Test graph rendering performance
- [ ] Test path finding algorithm
- [ ] Test filter combinations
- [ ] Test zoom/pan interactions
- [ ] Test with 1000+ nodes
- [ ] E2E test graph exploration

## Success Metrics

- Graph feature usage
- Paths discovered → deals created
- Introduction requests made
- User engagement time on graph
