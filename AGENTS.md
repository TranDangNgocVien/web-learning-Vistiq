# Vistiq UI rules

- Admin screens must prioritize the working content and controls. Do not add large hero/banner frames, decorative wave/stat blocks, or marketing-style copy above the main function unless the user explicitly requests it.
- Keep page titles and headings short and descriptive. Do not introduce oversized headline titles such as “Đưa âm thanh vào đúng ngữ cảnh.” or similarly verbose promotional headings.
- Prefer a compact section heading followed immediately by the relevant form, table, list, search, or editor. Avoid decorative layout that pushes the functional content below the fold.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.

## Graphify setup and synchronization

Codex and Google Antigravity must use the same repository graph at graphify-out/graph.json. Do not create assistant-specific copies of the graph. The project integrations for this repository use the official Graphify CLI:

- Codex: graphify codex install
- Google Antigravity: graphify antigravity install

At the start of a codebase task, check whether the graphify CLI and both project integrations are available. If the CLI exists but either integration is missing, run the matching install command and verify its generated files. Expected project integration files are .codex/hooks.json, .agents/rules/graphify.md, and .agents/workflows/graphify.md; Antigravity also uses the user-level Graphify skill. Preserve unrelated entries in assistant config files.

If graphify is not on PATH, attempt the recommended Windows installation automatically:

    winget install astral-sh.uv
    uv tool install graphifyy
    graphify codex install
    graphify antigravity install

If winget or uv cannot be used but Python 3.10+ is available, try the package fallback:

    py -m pip install --user --upgrade graphifyy
    py -m graphify codex install
    py -m graphify antigravity install

The PyPI package is graphifyy (two y characters); the executable is graphify. After installation, reopen the terminal if PATH has not refreshed. If automatic installation or either platform integration still fails, do not silently skip Graphify or claim the two assistants are synchronized. Ask the user to run the applicable commands above, report the failed command and error, then continue once they confirm installation. Use the official guide at https://github.com/Graphify-Labs/graphify for platform changes.

## Shared UX/UI rules

- Keep admin screens task-first: use a short title, then the relevant filters, form, table, editor, or list. Do not add a hero, promotional copy, decorative statistics, or oversized banner above working controls.
- Use the existing Vistiq design tokens, shared components, typography, spacing, and responsive breakpoints. Do not introduce a module-only button, modal, toast, or form style when a shared pattern exists.
- Buttons must describe the action (for example, “Lưu thay đổi”, “Import 10 bài”, “Xóa 3 mục”). Use one primary action per action area, secondary styling for alternatives, and destructive styling for destructive actions. Icon-only controls need an accessible name and tooltip. Disable duplicate submissions while a request is pending and expose the pending state.
- Show success and failure through the shared notification pattern. A failure must identify what failed and how to correct it when possible; do not silently swallow errors or display both a toast and a duplicate alert for the same result. Keep field errors beside their fields and preserve entered values after validation failures.
- Confirm only destructive, irreversible, or high-impact actions. State the affected record or count and the consequence; use an explicit action label such as “Xóa 3 bài” or “Gỡ 12 người khỏi vai trò”. Cancel is the safe default. Removing a role with members must show the member count and explain that their access from that role will be removed.
- Upload and import flows must state accepted file types and size limits before selection, show upload/progress state, validate before committing, and show a preview or import summary when the operation changes multiple records. Report row/file-specific errors, preserve the selected file and form values on recoverable errors, and make retry behavior clear. Never report an import as successful while invalid rows were silently skipped.
- Forms must label required fields, provide format/length constraints at the point of entry, associate errors with inputs, and support keyboard navigation and visible focus. Lists need loading, empty, error, and pagination states. Do not rely on color alone to convey status; respect reduced-motion preferences.
- Keep dialogs focused and short. Use a dialog for a decision that needs context or confirmation, not as a container for a large workflow. Do not use vague labels such as “OK” or “Xác nhận” when the actual action can be named.

## Module ownership and merge boundaries

- Module scope is defined by `docs/design/modules/<module>/SCOPE.md`; the module's `design.md` defines its product behavior and UI. A contributor may edit only the paths listed as owned in that scope. Other module directories are read-only to that contributor.
- Keep controllers, requests, routes, views, domain services, and tests inside the matching module paths. Do not add new feature code to legacy flat directories. Shared models, migrations, providers, middleware registration, route loaders, Composer configuration, `app/Admin/Http/Requests/Admin/Shared/`, `app/Domain/Services/Shared/`, shared resources, common layouts/components, shared assets, and root configuration are integration-owned; module contributors must request shared changes rather than editing those files in parallel.
- Every Admin module must implement server-side authorization for every page and mutation it owns. Route middleware and policy/Gate checks are authoritative; hiding a button is only a UI enhancement. The module scope lists the existing permission keys and guard requirements. If a module needs a new permission or a changed role default, submit the key, label, description, affected actions, and default-role mapping to the integration owner, who updates the shared permission catalog, seeder, and assignment screen together. Do not create permission CRUD; the catalog remains code-defined.
- Admin permission checks must use the existing `PermissionCatalog`/Gate mechanism and remain least-privilege. Preserve the full-permission Admin default. Role removal or role assignment changes that reduce access must be auditable and clearly confirmed when they affect multiple users.
- Keep routes, route names, middleware, and response contracts stable while moving files. Each module owns only its route include; `routes/web/admin.php` and `routes/web/learner.php` remain integration-owned loaders and must not contain feature routes.
- Module tests belong under that module's scoped test directory. Do not move or edit another module's tests to make a change pass; cross-module contract tests and shared fixtures are integration-owned.
- Resolve cross-module needs through the documented public service/action contract. Do not call another module's controller or edit its private implementation. If the contract is missing, document the needed interface and coordinate with its owner.
- For four-way parallel work, assign whole modules (or the non-overlapping work packages in `docs/architecture/module-ownership.md`). Do not assign two contributors the same module or shared integration files at the same time. The integrator owns merges and shared-file updates.
