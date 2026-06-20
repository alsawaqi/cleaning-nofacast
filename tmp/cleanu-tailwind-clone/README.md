# Cleanu-inspired Tailwind homepage

A clean-room recreation of the **visible homepage layout and interactions** from the referenced cleaning-services demo, written from scratch with:

- HTML5
- Tailwind CSS Play CDN
- Custom CSS for animation and component states
- Vanilla JavaScript

## Run

Open `index.html` directly, or serve the directory locally:

```bash
python3 -m http.server 8080
```

Then visit `http://localhost:8080`.

## Included interactions

- Responsive desktop and mobile navigation
- Desktop dropdown menus
- Sticky header
- Off-canvas information panel
- Search panel
- Hero slider with autoplay and controls
- Video modal
- Scroll reveal animation
- Animated counters
- Horizontal project carousel
- Testimonial carousel
- Estimate and newsletter form validation
- Back-to-top button

## Production note

This zero-build version uses Tailwind's browser CDN for easy editing. Tailwind documents the browser/Play CDN as a development workflow. For production, install Tailwind through its CLI, Vite, or PostCSS and generate a static CSS bundle.

## Assets and rights

This project does **not** contain the paid template's source code, original logo file, or its licensed preview assets. The layout and JavaScript were implemented from scratch. Demo photography is loaded from Unsplash; verify each asset's current license and replace it with your own production assets before launch.
