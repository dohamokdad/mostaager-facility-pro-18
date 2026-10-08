# Mostaager Facility PRO — 18.4.0 (Premium look + motion)

## Look
- Page header is now a navy hero with soft gold light, gold eyebrow pill, animated gold rule and a faded Cairo skyline.
  Secondary hero buttons are glass; primary is a gold gradient with a light sweep on hover.
- Sidebar: deeper navy gradient, floating brand mark, ONE sliding active indicator (pill + glowing gold edge)
  that glides between items instead of jumping.
- KPI cards get a navy icon chip (auto-picked from the card title), soft gold halo and lift on hover.
- Tables: sticky navy header, hover row highlight with gold edge; status texts become pills with a dot
  (overdue dot pulses gently).
- Forms: warm input background, gold focus ring. Busy buttons show a spinner.

## Motion
- Page intro: sidebar slides in, menu items fade in sequence, hero scales in, gold rule draws itself.
- Section (tab) switch: content fades up with staggered cards (CSS, restarts every time a tab opens).
- Numbers count up once when first visible. Tables below the fold reveal on scroll.
- Mobile: active tab scrolls into view; switching tabs scrolls smoothly to content top.
- All motion uses transform/opacity only and is fully disabled for prefers-reduced-motion.

## Files
- assets/css/ms-brand.css (rewritten) · assets/js/ms-ux.js (motion module)
