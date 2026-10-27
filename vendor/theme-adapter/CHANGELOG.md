# Changelog

## 0.1.0

Initial WordPress package, extracted from the theme adapter that PeerKit Chat already runs.

- Reads block themes and classic themes that ship `theme.json` through WordPress global styles.
- Reads other classic themes from the editor palette, background color, and header text color, then fills the remaining gaps in the browser.
- Publishes colors, typography, spacing, border radius, and button tokens as `--razor-theme-*` custom properties.
- Keeps `razor_theme_tokens()`, `razor_theme_css()`, `razor_theme_enqueue()`, and the `razor_theme_tokens` filter.
- Loads each bundled copy on its own. A plugin uses the copy it shipped, including when another plugin shipped a different version.
