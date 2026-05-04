# Button Taxonomy P41 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename CSS class `--primary-action` → `--primary-paper`, fix its spec to match P41 (padding/font/radius), update `--outline` to P41 spec, add `--soft`, and demote Improve buttons in Needs Attention from `--primary-paper` to `--outline`.

**Architecture:** Two-file change. CSS-first (Task 1) so the class names exist before PHP references them. PHP changes are pure class-string replacements — no logic changes.

**Tech Stack:** PHP 8.0+, WordPress 6.5+, plain CSS custom properties.

---

## File Map

| File | Change |
|------|--------|
| `admin/css/citewp-aiso-admin.css` | Section 25: rename selectors, fix spec. Section ~576: update `--outline`. Add `--soft` after `--outline`. |
| `includes/Admin/Menu.php` | Lines 527, 640, 688: class string updates |
| `includes/Settings/Page.php` | Line 373: class string update |

---

## Task 1: CSS — rename, fix, update, add

**Files:**
- Modify: `admin/css/citewp-aiso-admin.css:576-585` (outline block)
- Modify: `admin/css/citewp-aiso-admin.css:1572-1597` (primary-action block → primary-paper)

- [ ] **Step 1: Rename `--primary-action` → `--primary-paper` and fix spec**

In Section 25 (`/* === SECTION 25: Primary Action button ... ===*/`), replace the entire block:

```css
/* === SECTION 25: Primary-paper button (genuine primary CTA on paper surfaces, P41) === */

.citewp-aiso-btn--primary-paper {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-4);
  background: var(--citewp-tint-blue);
  color: var(--citewp-white);
  border: 1px solid var(--citewp-tint-blue);
  border-radius: var(--radius-md);
  font: 600 var(--fs-sm)/1 'Inter', system-ui, -apple-system, sans-serif;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.12s, border-color 0.12s;
}

.citewp-aiso-btn--primary-paper:hover,
.citewp-aiso-btn--primary-paper:focus {
  background: #1d4ed8;
  border-color: #1d4ed8;
  color: var(--citewp-white);
}

.citewp-aiso-btn--primary-paper:focus-visible {
  outline: 2px solid var(--citewp-tint-blue);
  outline-offset: 2px;
}
```

Changes from old block: selector renamed, `--sp-5` (20px) → `--sp-4` (16px), `--fs-base` (14px) → `--fs-sm` (13px), `border-radius: 6px` → `var(--radius-md)` (8px).

- [ ] **Step 2: Update `--outline` to P41 spec**

Replace the existing `--outline` block (currently uses navy color + navy border) at line ~576:

```css
.citewp-aiso-btn--outline {
  background: transparent;
  color: var(--citewp-obsidian);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-4);
  font: 600 var(--fs-sm)/1 'Inter', system-ui, -apple-system, sans-serif;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.12s, border-color 0.12s;
}

.citewp-aiso-btn--outline:hover {
  background: var(--citewp-paper);
  border-color: #C5CEE0;
  color: var(--citewp-obsidian);
  opacity: 1;
}
```

Key changes: color `--citewp-navy` → `--citewp-obsidian`, border `--citewp-navy` → `--citewp-border`, add full layout props, proper hover (no opacity hack).

- [ ] **Step 3: Add `--soft` class after `--outline` block**

```css
.citewp-aiso-btn--soft {
  background: var(--citewp-paper);
  color: var(--citewp-obsidian);
  border: 1px solid var(--citewp-border);
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-4);
  font: 600 var(--fs-sm)/1 'Inter', system-ui, -apple-system, sans-serif;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.12s, border-color 0.12s;
}

.citewp-aiso-btn--soft:hover {
  background: var(--citewp-paper-mid);
  color: var(--citewp-obsidian);
}
```

- [ ] **Step 4: Commit CSS changes**

```
git add admin/css/citewp-aiso-admin.css
git commit -m "feat: P41 button taxonomy — rename primary-action→primary-paper, fix spec, update outline, add soft"
```

---

## Task 2: PHP — button class string updates

**Files:**
- Modify: `includes/Admin/Menu.php:527` (View Recommendations — stays primary)
- Modify: `includes/Admin/Menu.php:640` (Improve — DEMOTE to outline)
- Modify: `includes/Admin/Menu.php:688` (Connect Now — stays primary)
- Modify: `includes/Settings/Page.php:373` (Save Changes — stays primary, add base class)

- [ ] **Step 1: Menu.php — View Recommendations (line 527): rename to primary-paper**

```php
<a href="#cite-score" class="citewp-aiso-btn--primary-paper"><?php esc_html_e( 'View Recommendations →', 'ai-search-optimizer' ); ?></a>
```

- [ ] **Step 2: Menu.php — Improve button (line 640): demote to outline**

```php
<a href="<?php echo esc_url( $edit_url ); ?>" class="citewp-aiso-btn--outline"><?php esc_html_e( 'Improve', 'ai-search-optimizer' ); ?></a>
```

This is the key P41 change — multiple Improve buttons per list; outline prevents button-soup.

- [ ] **Step 3: Menu.php — Connect Now (line 688): rename to primary-paper**

```php
<a href="https://citewp.com/pro" target="_blank" rel="noopener noreferrer" class="citewp-aiso-btn--primary-paper"><?php esc_html_e( 'Connect Now →', 'ai-search-optimizer' ); ?></a>
```

- [ ] **Step 4: Settings/Page.php — Save Changes (line 373): rename + add base class**

```php
<button type="submit" name="submit" class="citewp-aiso-btn citewp-aiso-btn--primary-paper">
    <?php esc_html_e( 'Save Changes', 'ai-search-optimizer' ); ?>
</button>
```

- [ ] **Step 5: Commit PHP changes**

```
git add includes/Admin/Menu.php includes/Settings/Page.php
git commit -m "fix: demote Needs Attention Improve buttons to outline; rename primary-action→primary-paper in PHP"
```

---

## Spec Coverage Check

| Requirement | Covered |
|-------------|---------|
| Rename `--primary-action` → `--primary-paper` | Task 1 Step 1 |
| Fix primary-paper padding (8px 16px) | Task 1 Step 1 |
| Fix primary-paper font-size (13px) | Task 1 Step 1 |
| Fix primary-paper border-radius (8px) | Task 1 Step 1 |
| Update `--outline` to obsidian + hairline border | Task 1 Step 2 |
| Add `--soft` class | Task 1 Step 3 |
| Improve button demoted to outline | Task 2 Step 2 |
| View Recommendations stays primary-paper | Task 2 Step 1 |
| Connect Now stays primary-paper | Task 2 Step 3 |
| Save Changes stays primary-paper | Task 2 Step 4 |

All requirements covered. No placeholders.
