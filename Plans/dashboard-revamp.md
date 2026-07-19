# Dashboard Power Plan (Cross-Role)

## Overview

Role-aware Dashboard redesign so each role gets actionable KPIs, queues, and deep links—instead of a mostly generic shared page.

## Current state (baseline)

Main entry: `public_html/pages/dashboard/index.php`

- Shared page for all logged-in users; light branching only.
- Cards: My Requests, Pending Approvals (approver+), Available Vehicles/Drivers, New Request CTA.
- Lists: global Upcoming Trips (7 days), Recent Activity (admin=all, else own).
- Charts (admin/motorpool/approver): trips 7d, status 30d, by dept, peak hours.
- Gaps: Guard ops on separate page; CAF/driver surfaces missing; unused `vehicleUtilization`; pending counts mismatch Approvals; sidebar badges not reused on dashboard.

Roles: requester, guard, chief_admin_finance, approver, motorpool_head, admin (+ driver capability).

## Design principles

1. Role-first widget packs on a shared shell.
2. Action Needed first (items to handle today).
3. Stat cards deep-link to filtered list pages.
4. Shared badge/count helpers for sidebar + dashboard.
5. Scope data by role (dept / assigned motorpool / own).
6. Keep existing LOKA UI components.

## Target layout

1. Greeting + date
2. Action Needed chips
3. KPI row (3–5 cards)
4. Primary queue (top 5–10)
5. Secondary panel (upcoming / fleet / finance)
6. Charts (role-appropriate only)

## Widget packs

| Role | KPIs / focus |
|------|----------------|
| Requester | Drafts/revision, pending, upcoming approved, own gas pending |
| Approver | Dept pending, unviewed, trip tickets submitted, maintenance, gas review |
| Motorpool | Assigned pending motorpool, trips today, on-trip, gas, maintenance, tickets |
| Admin | Motorpool pack + audit snippet + global activity |
| Guard | Redirect/ops: today, pending dispatch, pending arrival |
| CAF | Gas `pending_approval`, approved unpaid |
| Driver | Next trip, on trip, trip tickets |

## Technical approach

| Piece | Approach |
|-------|----------|
| Structure | `dashboard/index.php` + `dashboard/partials/*` |
| Data | `includes/dashboard_stats.php` → `dashboardStatsForUser()` |
| Badges | `includes/badge_counts.php` shared with sidebar |
| Charts | Role-scoped JSON + utilization canvas |
| Guard | Pure guards redirect to `?page=guard`; nav entry added |
| Perf | Cap lists 5–10; COUNT queries; no N+1 |

## Phased rollout

1. **P0** – Stats helpers, Action Needed + KPIs, motorpool count fix, guard entry.
2. **P1** – Role packs + dept-scoped charts.
3. **P2** – Utilization chart, admin audit snippet, polish.

## Success metrics

- Each role has a clear “do this next” action.
- KPI numbers match destination filters.
- Guard/CAF/driver are not stuck on a requester-like dashboard.
- Charts only show actionable analytics for that role.

## Out of scope

- Full IA rewrite, mobile apps, websockets.
- Changing approval business rules.
