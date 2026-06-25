# LOKA Fleet Management System — Design Update Plan

**Date:** 2025-06-25
**Status:** All 5 Phases Complete
**Goal:** Eliminate dual CSS framework overhead, unify responsive behavior, improve mobile UX

---

## Executive Summary

The project currently loads **both Bootstrap 5.3 (CDN) and TailwindCSS + DaisyUI (Vite)** simultaneously. This creates:
- ~250KB+ of unused CSS on every page load
- Conflicting class names and responsive breakpoints
- Maintenance overhead from two parallel styling systems

This plan migrates to a **Tailwind-only** frontend in 5 phases, with each phase independently deployable and testable.

---

## Current State

| Component | Current | Target |
|-----------|---------|--------|
| CSS Framework | Bootstrap 5.3 + TailwindCSS 3.4 | TailwindCSS 3.4 + DaisyUI 4.x |
| JS Framework | jQuery 3.7.1 + Bootstrap JS + Vanilla JS | Vanilla JS (jQuery removed) |
| Table Library | DataTables (jQuery) | DataTables (vanilla) or Vue DataTable |
| Charts | Chart.js 4.4.2 (inline PHP) | Chart.js 4.4.2 (extracted module) |
| Component Lib | Bootstrap components | DaisyUI (prefixed `dui-`) |
| Sidebar | CSS custom properties + JS toggle | Unified CSS variable + same JS logic |
| Breakpoints | CSS: 991.98/768/575.98px; Tailwind: 1024/768/640px | Tailwind defaults only |

---

## Phase 1: Quick Wins — Fix Visible Mobile Issues

**Goal:** Fix the most visible mobile problems without touching the framework setup.
**Risk:** Very Low — CSS-only changes, no structural changes.

### Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 1.1 | `pages/dashboard/index.php` | Add `flex-wrap gap-2` to page header flex container | ~221 |
| 1.2 | `assets/css/style.css` | Unify sidebar width variable to `16rem` (256px) | Line 6 |
| 1.3 | `assets/css/style.css` | Unify mobile sidebar width to `16rem` / `max-width: 85vw` | ~414 |
| 1.4 | `assets/js/app.js` | Enable `responsive: true` on DataTables init | Line 164 |
| 1.5 | `assets/js/app.js` | Add `columnDefs` with responsive priorities for non-essential columns | Line 164 |

### Verification
- [ ] Dashboard header wraps gracefully on mobile
- [ ] Sidebar is consistent width on desktop and mobile
- [ ] Tables auto-hide columns on mobile with expand toggle
- [ ] No visual regressions on desktop

---

## Phase 2: Header/Footer Cleanup — Remove Unused CDN Libraries

**Goal:** Conditionally load Bootstrap/jQuery only on pages that still need them.
**Risk:** Low — additive changes, nothing removed yet.

### Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 2.1 | `includes/header.php` | Wrap Bootstrap CSS in `<?php if (!UI_MODERN_ENABLED): ?>` conditional | ~10 |
| 2.2 | `includes/header.php` | Wrap DataTables CSS in conditional | ~16 |
| 2.3 | `includes/header.php` | Move Chart.js `<script>` from `<head>` to footer with `defer` | ~25 |
| 2.4 | `includes/footer.php` | Wrap jQuery in conditional | ~7 |
| 2.5 | `includes/footer.php` | Wrap Bootstrap JS in conditional | ~10 |
| 2.6 | `includes/footer.php` | Wrap DataTables JS in conditional | ~13-14 |
| 2.7 | `includes/footer.php` | Add Chart.js script tag with `defer` | New |

### Verification
- [ ] When `UI_MODERN_ENABLED=true`: only Tailwind/DaisyUI loads (no Bootstrap CSS/JS)
- [ ] When `UI_MODERN_ENABLED=false`: Bootstrap/jQuery/DataTables load as before
- [ ] Chart.js still works on dashboard
- [ ] DataTables still works on PHP-rendered table pages

---

## Phase 3: Unify Sidebar — Single Source of Truth

**Goal:** One sidebar implementation using CSS custom properties, compatible with both PHP and Vue pages.
**Risk:** Medium — sidebar is used on every page.

### Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 3.1 | `tailwind.config.js` | Update sidebar spacing to match `16rem` (already correct) | 57 |
| 3.2 | `assets/css/style.css` | Keep `--sidebar-width: 16rem` (already changed in Phase 1) | 6 |
| 3.3 | `assets/css/style.css` | Ensure mobile sidebar uses same `--sidebar-width` variable | ~414 |
| 3.4 | `assets/css/design-system.css` | Add `.loka-sidebar` component classes | New |
| 3.5 | `assets/css/design-system.css` | Add `.loka-sidebar-overlay` component classes | New |
| 3.6 | `assets/js/app.js` | No changes needed — JS logic already uses CSS classes | — |

### Verification
- [ ] Sidebar collapses/expands correctly on desktop
- [ ] Sidebar slides in/out on mobile with overlay
- [ ] Sidebar width is consistent across all breakpoints
- [ ] localStorage persistence works
- [ ] Escape key and overlay click close sidebar on mobile

---

## Phase 4: Extract Inline Scripts — Dashboard Charts Module

**Goal:** Move 183 lines of inline chart JS from `dashboard/index.php` to a proper module.
**Risk:** Low — extracted to separate file, behavior unchanged.

### Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 4.1 | `assets/js/charts/dashboard.js` | Create new module with all chart configurations | New |
| 4.2 | `pages/dashboard/index.php` | Remove inline `<script>` block (lines 368-551) | 368-551 |
| 4.3 | `pages/dashboard/index.php` | Add `<script src="/assets/js/charts/dashboard.js" defer>` | New |
| 4.4 | `assets/js/charts/dashboard.js` | Make mobile detection dynamic (resize listener) | New |
| 4.5 | `assets/js/charts/dashboard.js` | Configure Chart.js responsive options properly | New |

### Verification
- [ ] All 4 charts render correctly (daily trips, status, department, peak hours)
- [ ] Charts resize properly on window resize
- [ ] Mobile font sizes and legend positions update dynamically
- [ ] No console errors

---

## Phase 5: Expand Design System — Tailwind Component Library

**Goal:** Build out `design-system.css` with all missing component definitions so new pages can be built Tailwind-only.
**Risk:** Low — additive CSS, no existing code modified.

### Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 5.1 | `assets/css/design-system.css` | Add `.loka-navbar` component | New |
| 5.2 | `assets/css/design-system.css` | Add `.loka-table` / `.loka-table-responsive` components | New |
| 5.3 | `assets/css/design-system.css` | Add `.loka-chart-container` component with responsive heights | New |
| 5.4 | `assets/css/design-system.css` | Add `.loka-form-group` / `.loka-form-label` / `.loka-form-input` components | New |
| 5.5 | `assets/css/design-system.css` | Add `.loka-badge` / `.loka-alert` components | New |
| 5.6 | `assets/css/design-system.css` | Add `.loka-modal` responsive component (bottom-sheet on mobile) | New |
| 5.7 | `assets/css/design-system.css` | Add `.loka-dropdown` component | New |
| 5.8 | `assets/css/design-system.css` | Add `.loka-avatar` component | New |

### Verification
- [ ] All new classes render correctly
- [ ] Responsive behavior works at all breakpoints
- [ ] No conflicts with existing Bootstrap classes
- [ ] Design tokens (colors, spacing, shadows) are consistent

---

## File Change Summary

| File | Phases | Nature of Change |
|------|--------|-----------------|
| `assets/css/style.css` | 1, 3 | Fix sidebar width, no other changes |
| `pages/dashboard/index.php` | 1, 4 | Add flex-wrap, remove inline script |
| `assets/js/app.js` | 1 | Enable DataTables responsive |
| `includes/header.php` | 2 | Conditional Bootstrap loading, move Chart.js |
| `includes/footer.php` | 2 | Conditional jQuery/Bootstrap/DataTables loading |
| `tailwind.config.js` | 3 | Verify sidebar value (no change needed) |
| `assets/css/design-system.css` | 3, 5 | Add sidebar + all missing component definitions |
| `assets/js/charts/dashboard.js` | 4 | New file — extracted chart module |

---

## Risk Mitigation

1. **Feature flag preserved:** `UI_MODERN_ENABLED` stays in place. If anything breaks, set `UI_MODERN_ENABLED=false` to roll back to Bootstrap-only mode.
2. **No deletion in Phase 1-4:** Bootstrap CSS/JS files are only made conditional, never deleted. Rollback is instant.
3. **Phase 5 is purely additive:** New CSS classes only — zero risk to existing functionality.
4. **Each phase is independently deployable:** If Phase N causes issues, previous phases remain functional.

---

## Testing Checklist

After each phase, verify:
- [ ] Login page renders correctly
- [ ] Dashboard loads with all charts
- [ ] Sidebar collapses/expands on desktop
- [ ] Sidebar slides in/out on mobile
- [ ] Request list table is scrollable on mobile
- [ ] DataTables sort/search/pagination work
- [ ] Modals open and close correctly
- [ ] Flash messages display
- [ ] Print layout hides sidebar/navbar
- [ ] Dark mode toggle works (if implemented)
- [ ] No console errors
- [ ] No Bootstrap JS errors (dropdowns, toasts, modals)

---

## Implementation Order

```
Phase 1 (Quick Wins)         ← START HERE
    ↓
Phase 2 (CDN Cleanup)        ← reduces page weight by ~200KB
    ↓
Phase 3 (Sidebar Unify)      ← single source of truth
    ↓
Phase 4 (Extract Scripts)    ← cleaner dashboard code
    ↓
Phase 5 (Design System)      ← foundation for future pages
```

**Estimated Total Effort:** 3-4 hours across all phases
**Each Phase:** 30-60 minutes
