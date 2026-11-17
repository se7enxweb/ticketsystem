# Design

`[ChromeSettings] Chrome` chooses the frame of the views:

- `katalyst`: the Katalyst customer console (its rail, top bar and command palette, `design:katalyst/parts/chrome_open.tpl`),
  for a user linked to a Katalyst customer. The views then use only the console's components (`ktl-*` of
  katalyst.css): page head, cards, tabs, table, pills, facts, form fields, buttons.
- `standard`: the extension's own frame, `design/standard/stylesheets/ticketsystem.css` (`ts-*`), loaded through
  `[StylesheetSettings] FrontendCSSFileList` and `BackendCSSFileList`.
- `auto` (default): katalyst when possible, else standard.

Every element carries both class sets; each stylesheet styles only its own frame (`.ts-app ...` and the few
`.ktl-app .ts-*` rules for a post's head and body).

Rules kept everywhere:

- No `<style>` and no `style=""` in a template. Colours, radii, spaces and sizes are tokens (`--ts-*`), each colour
  with its dark value (`prefers-color-scheme`).
- Mobile first: 16 px text, 44 px controls, the list is a stack of blocks on phones and a table from 720 px, the
  ticket's details move beside the conversation from 960 px. No sideways scroll.
- Plain words, American English, i18n context `extension/ticketsystem`.

The original templates of ticketsystem 1.x (the ticket's full view and edit forms in the administration) are kept in
`design/standard/override/templates`.
