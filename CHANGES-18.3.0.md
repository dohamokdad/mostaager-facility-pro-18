# Mostaager Facility PRO — 18.3.0 (Brand identity + layout)

Reference: «مستأجر العقاري» moodboard — navy #0D1B2A, gold #D4AF37, beige #F2E9DB, gray #6B7280, white; Cairo/Tajawal.

## Colors → design tokens
- ~1,030 hard-coded colors in dashboard PHP/JS/CSS replaced by `var(--ms-*, <brand fallback>)`.
  Changing the brand later = editing `:root` in `assets/css/ms-brand.css` only.
- Legacy blue (#2563eb & co.) → gold for actions / navy for text. Gold buttons use navy text (contrast ≈ 8.5:1).
- Amounts no longer red (#c24126 → navy). Red is reserved for overdue/danger.
- Status colors kept semantic (success / warning / danger) with brand-tuned values.
- PDF invoices/reports navy aligned to #0D1B2A.

## Layout (all four dashboards)
- New `assets/css/ms-brand.css`, loaded last on dashboard pages (+ Cairo/Tajawal).
- Sidebar: navy, sticky, brand block (مستأجر — إيجار • بيع • ثقة), gold active indicator,
  line icons instead of emoji (templates/partials/sidebar.php), logout separated, Cairo skyline line-art.
  Optional logo: option `ms_dashboard_logo_url`.
- Unified page header (eyebrow + title + gold rule + subtitle + quick actions) — now also on tenant and building-manager dashboards.
- KPI cards with gold edge + large navy numbers; navy table headers; gold focus rings; consistent radii and soft shadows.
- Mobile ≤991px: sidebar becomes a sticky horizontal icon nav; tables auto-wrapped in horizontal scroll; actions stack.
- Building picker hidden when the manager has a single building.
- aria-current on active menu item; prefers-reduced-motion respected.

## New assets
- assets/css/ms-brand.css · assets/img/ms-skyline.svg · assets/img/ms-skyline-navy.svg
