# F4: Approval Workflows

## Overview
Multi-step approval processes for quotes, discounts, contracts, and other items requiring sign-off.

## Key Features
- Approval rule configuration
- Multi-level approvals
- Parallel and sequential flows
- Delegation support
- Mobile approval
- SLA tracking
- Escalation rules
- Approval history

## Approval Types
- Discount approvals (>X%)
- Quote approvals (>$X)
- Contract approvals
- Expense approvals
- Custom approvals

## Database Additions
```sql
CREATE TABLE approval_rules (id, name, module_id, conditions, approver_chain);
CREATE TABLE approval_requests (id, rule_id, record_id, status, requested_by);
CREATE TABLE approval_steps (id, request_id, approver_id, step_order, status, decided_at);
CREATE TABLE approval_delegations (id, delegator_id, delegate_id, start_date, end_date);
```

## Components
- `ApprovalRuleBuilder.svelte`
- `ApprovalQueue.svelte`
- `ApprovalDetail.svelte`
- `ApprovalHistory.svelte`
