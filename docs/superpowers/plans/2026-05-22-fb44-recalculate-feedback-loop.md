# FB44 — Recalculate Feedback Loop Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Gutenberg sidebar's Recalculate button dirty-state aware so it always scores the current editor content, not stale saved content.

**Architecture:** Add `isDirty` selector and `savePost` dispatch to `ScoreSidebar`; extract a `saveAndRecalculate` callback that conditionally fires `savePost()` before calling the REST endpoint; update button label and hint to reflect dirty state; fix the misleading "✓ Added" pill in `SchemaTypeRow`. No new PHP, no new REST endpoints, no Engine.php changes.

**Tech Stack:** React (`@wordpress/element`), `@wordpress/data` (already imported), `apiFetch`, `@wordpress/components`, WordPress REST API (`/citewp/aiso/v1/score/{id}/recalculate`), `@wordpress/scripts` build

---

## File Map

| File | Action | What changes |
|---|---|---|
| `src/sidebar/index.js` | Modify | ScoreSidebar: add `isDirty` selector + `savePost` dispatch, replace `recalculate` with `saveAndRecalculate`, update button + hint; SchemaTypeRow: update "✓ Added" label |
| `ai-search-optimizer.php` | Modify | Version bump 0.7.5 → 0.7.6 |
| `SESSION-LOG.md` | Modify | S38 entry |

---

### Task 1: Add dirty-state awareness and extract `saveAndRecalculate` callback

**Files:**
- Modify: `src/sidebar/index.js:50-103`

Root cause: `recalculate()` at lines 74-89 calls the REST endpoint directly. `ScoreController::recalculate()` calls `get_post()` which reads from the database — it never sees in-memory Gutenberg editor changes. Fix: check `isEditedPostDirty()`, save first if true, then hit the endpoint.

- [ ] **Step 1: Add `isDirty` useSelect and `savePost` useDispatch inside `ScoreSidebar`**

In `src/sidebar/index.js`, add two lines immediately after the existing `isAutosavingPost` selector (after line 53, before the blank line at 55). `useDispatch` is already imported from `@wordpress/data`; `savePost` is a `core/editor` action (separate from the `core/block-editor` dispatch used in `SchemaSuggestions`).

Replace this block (lines 51-53):
```js
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const isSavingPost = useSelect( ( select ) => select( 'core/editor' ).isSavingPost(), [] );
	const isAutosavingPost = useSelect( ( select ) => select( 'core/editor' ).isAutosavingPost(), [] );
```

With:
```js
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const isSavingPost = useSelect( ( select ) => select( 'core/editor' ).isSavingPost(), [] );
	const isAutosavingPost = useSelect( ( select ) => select( 'core/editor' ).isAutosavingPost(), [] );
	const isDirty = useSelect( ( select ) => select( 'core/editor' ).isEditedPostDirty(), [] );
	const { savePost } = useDispatch( 'core/editor' );
```

- [ ] **Step 2: Replace `recalculate` with `saveAndRecalculate`**

Remove the existing `recalculate` callback (lines 74-89) and replace with `saveAndRecalculate`. This extracted callback is the FB39 hook point — the future `PluginPrePublishPanel` will call `saveAndRecalculate` from the same JS module via prop-drilling or context; extracting it here keeps the logic in one place.

Replace:
```js
	const recalculate = useCallback( async () => {
		if ( ! postId ) return;
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( {
				path: `/citewp/aiso/v1/score/${ postId }/recalculate`,
				method: 'POST',
			} );
			setScore( data );
		} catch ( e ) {
			setError( e.message || 'Failed to recalculate score' );
		} finally {
			setLoading( false );
		}
	}, [ postId ] );
```

With:
```js
	const saveAndRecalculate = useCallback( async () => {
		if ( ! postId ) return;
		setLoading( true );
		setError( null );
		try {
			if ( isDirty ) {
				await savePost();
				// savePost() resolves even on failure — check for a save error via
				// the editor store rather than relying on thrown exceptions.
				const saveError = select( 'core/editor' ).getSaveError?.();
				if ( saveError ) {
					setError( saveError.message || 'Save failed — recalculation aborted.' );
					return;
				}
			}
			const data = await apiFetch( {
				path: `/citewp/aiso/v1/score/${ postId }/recalculate`,
				method: 'POST',
			} );
			setScore( data );
		} catch ( e ) {
			setError( e.message || 'Failed to recalculate score' );
		} finally {
			setLoading( false );
		}
	}, [ postId, isDirty, savePost ] );
```

Note: `select` is already a named import from `@wordpress/data` (line 10). `getSaveError` is available in WP 6.3+ via the core/editor store; the optional-chain `?.()` ensures safe fallback on older WP installs.

- [ ] **Step 3: Verify `select` import is present**

Check line 10 of `src/sidebar/index.js`. It should read:
```js
import { useSelect, useDispatch, select } from '@wordpress/data';
```
If `select` is missing from that import, add it. (Per the pre-scan it is already present — this is a confirmation step only.)

- [ ] **Step 4: Confirm the wasSaving effect still works**

The existing effect at lines 95-103 calls `fetchScore()` after any save completes (including saves triggered by `saveAndRecalculate`). This will fire a GET after the POST — last-write-wins on `setScore` is intentional and harmless since both return the same fresh score. No change needed here.

---

### Task 2: Update button label and hint line

**Files:**
- Modify: `src/sidebar/index.js:136-148`

- [ ] **Step 1: Update the Recalculate button in `ScoreSidebar` JSX**

Replace the existing recalc block (lines 136-148):
```jsx
							<div className="citewp-aiso-sidebar-recalc">
								<Button
									variant="secondary"
									onClick={ recalculate }
									disabled={ loading }
									isBusy={ loading }
								>
									Recalculate
								</Button>
								<p className="citewp-aiso-sidebar-recalc-hint">
									Saves trigger auto-recalculation.
								</p>
							</div>
```

With:
```jsx
							<div className="citewp-aiso-sidebar-recalc">
								<Button
									variant="secondary"
									onClick={ saveAndRecalculate }
									disabled={ loading }
									isBusy={ loading }
								>
									{ isDirty ? 'Save & Recalculate' : 'Recalculate' }
								</Button>
								<p className="citewp-aiso-sidebar-recalc-hint">
									{ isDirty
										? 'Will save your changes first.'
										: 'Saves trigger auto-recalculation.' }
								</p>
							</div>
```

---

### Task 3: Fix the misleading "✓ Added" pill label in `SchemaTypeRow`

**Files:**
- Modify: `src/sidebar/index.js:401-406`

Context: `insertSchemaBlock` appends the schema block to the in-memory editor block tree immediately but does NOT save. Showing "✓ Added" implies the schema is live on the page — it isn't until the user saves the post.

- [ ] **Step 1: Change the statusLabel string**

Find this block inside `SchemaTypeRow` (around line 401-406):
```js
	let action;
	if ( detected || inserted ) {
		const statusLabel = ( inserted && ! detected ) ? '✓ Added' : 'Already detected';
		action = (
			<span className="citewp-aiso-sidebar-schema-row__pill">
				{ statusLabel }
			</span>
		);
```

Replace with:
```js
	let action;
	if ( detected || inserted ) {
		const statusLabel = ( inserted && ! detected ) ? 'Added to editor (save to apply)' : 'Already detected';
		action = (
			<span className="citewp-aiso-sidebar-schema-row__pill">
				{ statusLabel }
			</span>
		);
```

---

### Task 4: Document Classic Editor limitation (verify-only, no code change)

**Files:**
- No code change. Limitation documented here and in SESSION-LOG.md.

- [ ] **Step 1: Confirm EditorPanel.php meta box is Gutenberg-suppressed**

`includes/Admin/EditorPanel.php` `register_meta_box()` (lines 63-76) calls `use_block_editor_for_post_type()` and skips registration in Gutenberg. Only Classic Editor / Elementor / Divi / Beaver Builder users see it. This is per decision P24.

- [ ] **Step 2: Note the Classic Editor limitation**

The inline JS in `EditorPanel.php` (lines 276-326) uses `fetch()` directly against the REST endpoint. Classic Editor has no equivalent of `isEditedPostDirty()`. Adding a dirty check would require injecting a JS framework or posting editor textarea content to a new REST endpoint — both out of scope. **Known limitation:** Classic Editor users who edit content and click Recalculate without clicking "Update" will score stale DB content. Document this in SESSION-LOG.md S38 entry.

---

### Task 5: Build, verify, version bump, session log

**Files:**
- Modify: `ai-search-optimizer.php` (version constant)
- Modify: `SESSION-LOG.md`

- [ ] **Step 1: Run the build**

```powershell
cd "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer"
npm run build
```

Expected: exits 0, no errors, `build/index.js` updated.

- [ ] **Step 2: Manual browser verification — dirty path**

1. Open any post in the Gutenberg editor at `http://citewp-dev.local`
2. Open the CiteWP Cite Score sidebar
3. Note initial score and button label ("Recalculate")
4. Edit the post content (add a word or sentence)
5. **Verify:** button label changes to "Save & Recalculate", hint reads "Will save your changes first."
6. Click "Save & Recalculate"
7. **Verify:** post saves (title bar loses the ● dirty indicator), score updates to reflect edited content
8. **Verify:** button label returns to "Recalculate"

- [ ] **Step 3: Manual browser verification — clean path**

1. On the same (now-saved) post, do NOT edit anything
2. **Verify:** button label is "Recalculate", hint reads "Saves trigger auto-recalculation."
3. Click "Recalculate"
4. **Verify:** score refreshes without triggering a save (no save spinner in title bar)

- [ ] **Step 4: Check debug.log**

Open `C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\debug.log`. Verify no new PHP errors from this session.

- [ ] **Step 5: Version bump**

In `ai-search-optimizer.php`, update:
- Plugin header: `Version: 0.7.5` → `Version: 0.7.6`
- Constant: `define( 'CITEWP_AISO_VERSION', '0.7.5' )` → `define( 'CITEWP_AISO_VERSION', '0.7.6' )`

- [ ] **Step 6: Update SESSION-LOG.md**

Add S38 entry documenting:
- Delivered: FB44 dirty-state Recalculate fix; dynamic button label; "Added to editor (save to apply)" label fix
- Classic Editor limitation: meta box Recalculate always scores saved DB content (no dirty API in Classic)
- Deferred: schema-fail-only filter (FB candidate), `new Repository()` hoist (S37 carryover)
- Next session: FB39 (Publish block injection) can now import `saveAndRecalculate` from ScoreSidebar

- [ ] **Step 7: Commit**

```powershell
git add src/sidebar/index.js ai-search-optimizer.php SESSION-LOG.md
git commit -m "fix: FB44 — dirty-aware Recalculate; save first when editor has unsaved changes"
git push
```

---

## Self-Review

**Spec coverage:**
- ✓ Item 1 (dirty-aware recalculate) → Task 1
- ✓ Item 2 (dynamic button label + hint) → Task 2
- ✓ Item 3 (save-failure abort) → Task 1 Step 2 (`getSaveError` check + catch block)
- ✓ Item 4 ("✓ Added" label fix) → Task 3
- ✓ Item 5 (extracted `saveAndRecalculate` for FB39) → Task 1 Step 2 (named `saveAndRecalculate` useCallback)
- ✓ Item 6 (EditorPanel.php verify-only) → Task 4

**Placeholder scan:** No TBD/TODO/placeholder language present.

**Type consistency:**
- `saveAndRecalculate` is named consistently across Task 1 (definition) and Task 2 (button `onClick`)
- `isDirty` is defined in Task 1 and consumed in Task 1 (callback dep array) and Task 2 (button JSX)
- `fetchScore` already exists; unchanged
- `select` import already present; confirmed in Task 1 Step 3

**Guardrails:**
- Engine.php: not touched
- No new REST endpoints
- Schema Suggestions detection/insert logic: only the label string changed, no logic
- Menu.php "Recalculate All": not touched
