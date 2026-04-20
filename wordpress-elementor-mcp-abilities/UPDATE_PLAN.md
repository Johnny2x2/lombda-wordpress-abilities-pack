# Elementor Abilities Update Plan

## Overview
The `elementor-abilities.php` plugin has started moving away from direct `_elementor_data` writes toward Elementor-aware document saving. The first implementation pass now targets modern container-based documents, exposes runtime capability information for MCP callers, and validates raw JSON before persistence.

## Implemented In This Pass
1. **Native Save Abstraction:** Added a shared save layer that routes document writes through Elementor's document API when available, with a controlled meta fallback.
2. **Runtime Capability Detection:** Added centralized capability reporting for document API availability, container activation, and grid container support.
3. **Validated Raw Updates:** `elementor/update-elementor-data` now decodes and validates raw JSON before saving instead of blindly writing the meta value.
4. **Modernized Container Generation:** `elementor/create-container` now supports flex and grid payload generation and rejects unsupported grid requests.
5. **Safer Widget/Page Mutations:** Page creation, document save, add-widget, add-section, move-widget, remove-widget, duplicate-widget, and bulk updates now save through the shared document path.
6. **Template Persistence Updates:** Template upload and update now use the shared save layer, and template export/read paths use the same normalized document readers.
7. **Diagnostics Improvements:** `elementor/get-info` now reports runtime capabilities and active Elementor experiments in addition to basic version details.

## Remaining Follow-Up
1. **Legacy Write Cleanup:** `elementor/add-section` still supports a legacy section fallback when container support is unavailable. Decide whether to fully deprecate legacy section writes in a later pass.
2. **Deeper Schema Validation:** Validation currently focuses on element structure, widget existence, and container mode safety. It does not yet validate every control payload or responsive setting shape.
3. **Editor-Level Verification:** The updated plugin still needs live end-to-end verification inside the Elementor editor on the local site.
4. **Optional API Cleanup:** Consider whether `add-section` should become an explicit `add-container` primary API for MCP callers in a later breaking-change pass.

## Testing Strategy
1. Create a new Elementor page through MCP and confirm it opens in the Elementor editor without Recovery Mode.
2. Save a document with nested containers and widgets, then reopen it and confirm the structure persists correctly.
3. Use `elementor/update-elementor-data` with invalid JSON and confirm the ability returns an error instead of corrupting the document.
4. Create flex and grid containers through `elementor/create-container` and confirm unsupported grid requests are rejected when the site does not report grid support.
5. Upload and update Elementor templates through MCP, then confirm they open correctly in the Elementor editor.
