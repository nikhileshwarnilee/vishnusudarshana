# Color Refactoring Summary

## Overview
The entire codebase has been refactored to use universal CSS color variables instead of hardcoded color values. This makes it easy to update the site's color scheme in one place.

## CSS Variables Defined

```css
:root {
  --cream-bg: #FFFDEB;           /* Light yellow cream background */
  --button-bg: #FFFACD;          /* Soft yellow button background */
  --button-hover: #FFD700;       /* Gold button hover background */
  --button-border: #FFD700;      /* Gold button border */
  --button-border-hover: #d4af37;/* Dark gold border on hover */
  --maroon: #8B1538;             /* Primary maroon text/accent color */
  --text-dark: #333;             /* Dark text color */
}
```

## Files Updated

### 1. **assets/css/style.css** (Main stylesheet)
- Added `:root` CSS variables at the top of the file
- Updated button styles to use `var(--button-bg)`, `var(--button-hover)`, `var(--button-border)`, `var(--button-border-hover)`, and `var(--maroon)`
- Updated text colors to use `var(--text-dark)` and `var(--maroon)`
- Updated background colors to use `var(--cream-bg)` and `var(--light-bg)`
- Refactored header design-12 section to use gradient variables
- Updated mobile navigation to use color variables
- Updated all sections (who-for, cta-guidance, services-online, how-to-use, etc.) to use variables

### 2. **services.php**
- Removed inline `style="background-color:#FFD700;"` from main element
- Updated `.services-main` background to use `var(--cream-bg)`
- Updated `.category-info h2` to use `var(--maroon)`
- Updated `.category-card` text color to use `var(--text-dark)`
- Updated `.category-card:hover` to use `var(--maroon)`
- Updated `.services-title` to use `var(--text-dark)`

### 3. **header.php** and **index.php**
- Already using CSS classes with variables

## How to Change Colors Globally

To update the site's color scheme, simply modify the CSS variables in `assets/css/style.css` at the top:

```css
:root {
  --cream-bg: #NEW_CREAM_COLOR;
  --button-bg: #NEW_BUTTON_COLOR;
  --maroon: #NEW_MAROON_COLOR;
  /* ... etc */
}
```

All elements using these variables will automatically update!

## Benefits

✅ **Consistency**: All colors are defined in one place
✅ **Maintainability**: Easy to find and update colors
✅ **Scalability**: Adding new color schemes requires changing just a few variables
✅ **Reusability**: Variables can be used across multiple files
✅ **Performance**: No performance impact, variables are processed at stylesheet load time

## Color Reference

| Variable | Value | Usage |
|----------|-------|-------|
| `--cream-bg` | #FFFDEB | Body and main content background |
| `--button-bg` | #FFFACD | Button backgrounds, soft yellow |
| `--button-hover` | #FFD700 | Button hover state, gold |
| `--button-border` | #FFD700 | Button borders, gold |
| `--button-border-hover` | #d4af37 | Border color on hover, dark gold |
| `--maroon` | #8B1538 | Primary accent, headings, text |
| `--text-dark` | #333 | Default text color |
