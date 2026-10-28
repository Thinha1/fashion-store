# Design: Fashion Store (storefront)

The locked system for the customer-facing pages. Every Hallmark run reads this first and pages share it. Change it on purpose; the file is the rule. The admin keeps its own look in `resources/css/app.css` and `admin.css`.

## System
- Genre: editorial
- Shape of the first page: Ecosystem Index (a cover of featured products, then shelves, the category index, the brands)
- Theme: Editorial (Hallmark catalog, deal 2/3)
- Axes: light/display-condensed/chromatic-green · posture: restrained
- Job: buy. Hero postures: Stage (/), Index (/san-pham, /bo-suu-tap), Counter (/san-pham/{slug})

## Tokens
`resources/css/store/tokens.css` is the source of truth (Tailwind v4 `@theme static`, entry `resources/css/store.css`). The roles:
```css
:root {
  --color-paper: oklch(97% 0.007 86);  --color-paper-2: oklch(94% 0.010 86);  --color-paper-3: oklch(91% 0.012 84);
  --color-ink: oklch(16% 0.012 280);  --color-ink-2: oklch(29% 0.011 280);  --color-muted: oklch(46% 0.010 75);
  --color-rule: oklch(85% 0.009 85);  --color-rule-2: oklch(64% 0.010 80);
  --color-accent: oklch(49% 0.130 152);  --color-accent-ink: oklch(98% 0.005 88);  --color-focus: oklch(52% 0.135 152);
  --font-display: "Bricolage Grotesque" (wdth 75, weight 800 for heads; 500 at opsz 12 for labels and controls);
  --font-body: "Source Serif 4" (18px reading text);
  --radius-card: 0;  --radius-pill: 0;  --rule-hair: 1px;  --rule-heavy: 4px;  --shadow-card: none;
}
```
The spacing scale (4px base), the type scale, the easings and the durations live in `tokens.css`. Tailwind's gray, brand, red, green and radius names are mapped onto these roles there, so shared Blade components follow the storefront palette.

## Surfaces
- A section opens with a 4px ink rule, its head flush under it; 1px hairlines part rows. Nothing is boxed or shadowed.
- Depth is a paper step (`--color-paper-2`), never a raised card. One ink band a page at most (the brands on the home page, the sign-in story).
- Product photographs at 4:5 in `[data-slot]` frames with an 8% ink inset outline.

## Controls
- Primary: ink fill, paper text · square · 48px (52px on the buy button)
- Secondary: 1px ink outline on paper · square · same height
- Chips and filters: 44px, outline; selected is ink-filled (or a 2px ink border for view toggles)
- The accent green stays under 5% of a screen: price tags, the cart count, the wordmark dot, focus rings, link underlines on hover

## Motion
- Character: still. State changes only (underline colour, a one-step darker fill, a 1px press). The cover crossfades between products when the arrows are used; nothing moves on its own. Under reduced motion everything ends at once.

## Exports
`resources/css/store/tokens.css` in this project. Other formats are added here on request.
