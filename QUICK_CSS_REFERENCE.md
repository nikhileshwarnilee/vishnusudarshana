# 🎨 CSS Standardization - Quick Reference Guide

## Color Palette

```
┌─ Primary Colors ─────────────────────────┐
│                                          │
│  🟨 Cream Background    #FFFDEB          │
│  🟨 Button              #FFFACD          │
│  🟨 Button Hover        #FFD700          │
│  🟥 Maroon (Text)       #8B1538          │
│  🟧 Saffron (Accent)    #FF9933          │
│                                          │
└──────────────────────────────────────────┘
```

## Quick CSS Variables Reference

| Use For | Variable | Value |
|---------|----------|-------|
| Page Background | `--cream-bg` | #FFFDEB |
| Button Background | `--button-bg` | #FFFACD |
| Button Hover | `--button-hover` | #FFD700 |
| Button Border | `--button-border` | #FFD700 |
| Button Border Hover | `--button-border-hover` | #d4af37 |
| Primary Text | `--text-dark` | #333 |
| Headings | `--maroon` | #8B1538 |
| Secondary Text | `--saffron` | #FF9933 |
| Admin Accents | `--dark-maroon` | #6B0E2E |
| Light Backgrounds | `--light-bg` | #F8F8F8 |
| Gradient | `--spiritual-bg` | #fffbe6 → #f9f3e9 |

## Code Examples

### ✅ CORRECT: Use CSS Variables

```html
<!-- HTML -->
<main class="main-content">
  <h1>Welcome</h1>
</main>

<!-- CSS -->
.main-content {
  background-color: var(--cream-bg);
}

h1 {
  color: var(--maroon);
}
```

### ❌ INCORRECT: Hardcoded Colors

```html
<!-- HTML -->
<main style="background-color: #FFD700;">
  <h1 style="color: #8B1538;">Welcome</h1>
</main>
```

## Common Utility Classes

| Class | Purpose | Example |
|-------|---------|---------|
| `.btn-block` | Full-width button | Links, buttons |
| `.btn-soft-yellow` | Soft yellow button | CTA buttons |
| `.alert-info` | Info alert box | Notifications |
| `.category-logo` | Logo styling | Category cards |
| `.detail-section-center` | Centered section | Detail pages |

## How to Change Theme

### Global Theme Change
1. Open `assets/css/style.css`
2. Edit `:root` variables (lines 1-11)
3. Save and refresh browser

### Example: Dark Theme
```css
:root {
  --cream-bg: #1a1a1a;        /* Dark background */
  --button-bg: #2d2d2d;       /* Dark button */
  --maroon: #ff6b9d;          /* Pink accent */
  --text-dark: #f0f0f0;       /* Light text */
  /* ... continue for other colors ... */
}
```

## Files to Remember

### Critical Files
- `assets/css/style.css` - Where CSS variables are defined
- `header.php` - Main header styling
- `index.php` - Home page styling

### Admin Files
- `admin/index.php` - Admin dashboard
- `admin/cif/category.php` - Category management
- `admin/cif/clients.php` - Client management

## Search & Replace Patterns

### Find Hardcoded Colors
**Pattern**: `#[0-9A-F]{6}` or `#[0-9A-F]{3}`
**Tool**: Use Ctrl+F (Find) in editor

### Common Colors to Replace
- `#FFD700` → `var(--button-hover)`
- `#FFFACD` → `var(--button-bg)`
- `#FFFDEB` → `var(--cream-bg)`
- `#8B1538` → `var(--maroon)`
- `#333` → `var(--text-dark)`
- `#f8f8f8` → `var(--light-bg)`

## Color Usage Statistics

| Element | # of Uses | Status |
|---------|-----------|--------|
| Buttons | 40+ | ✅ Standardized |
| Headings | 80+ | ✅ Standardized |
| Backgrounds | 50+ | ✅ Standardized |
| Borders | 30+ | ✅ Standardized |
| Text | 15+ | ✅ Standardized |
| **Total** | **215+** | **✅ 100%** |

## Browser Support

All modern browsers support CSS variables:
- ✅ Chrome 49+
- ✅ Firefox 31+
- ✅ Safari 9.1+
- ✅ Edge 15+
- ✅ Opera 36+

## Testing Checklist

Before committing changes:
- [ ] Home page displays correctly
- [ ] Services page styling is correct
- [ ] Admin panel looks good
- [ ] Buttons are styled properly
- [ ] Text is readable
- [ ] No broken layouts
- [ ] Mobile responsive

## Emergency: Quick Revert

If colors look wrong:
1. Open `assets/css/style.css`
2. Find `:root { }`
3. Revert values to:
   - `--cream-bg: #FFFDEB`
   - `--maroon: #8B1538`
   - `--button-hover: #FFD700`
4. Save and refresh

## Common Issues & Solutions

### Issue: Colors not changing site-wide
**Solution**: 
- Clear browser cache (Ctrl+Shift+Delete)
- Check that variable is used with `var()` syntax
- Verify CSS file is linked in header.php

### Issue: Button text not visible
**Solution**:
- Ensure text color contrasts with background
- Check `color: var(--text-dark)` is applied
- Verify `:hover` state colors

### Issue: Mobile layout broken
**Solution**:
- Check responsive breakpoints in CSS
- Verify utility classes are applied
- Test with DevTools (F12)

## Next Steps

1. **For Developers**: Use CSS variables for ALL new colors
2. **For Designers**: Reference this guide for color values
3. **For Project Leads**: Review `CSS_REFACTORING_COMPLETE.md` for full details
4. **For QA**: Test with color changes using browser DevTools

## Additional Resources

📄 **Full Documentation**: `CSS_REFACTORING_COMPLETE.md`
✅ **Verification Checklist**: `CSS_REFACTORING_VERIFICATION.md`
📋 **Summary Document**: `COLOR_REFACTORING_SUMMARY.md`

---

**Last Updated**: January 11, 2026
**Version**: 1.0
**Status**: ✅ Live & Active
