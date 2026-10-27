# WordPress Theme Adapter

Small PHP library that reads the active WordPress theme and publishes design tokens as CSS custom properties. It is not a plugin. A host plugin bundles this directory and loads `load.php`. Site owners install the host plugin only.

Composer package: `razorsoft/wordpress-theme-adapter`.

Requires PHP 8.1 and WordPress 6.5 or newer. Older WordPress functions are skipped when they are missing; block-theme data needs the global-styles APIs from WordPress 5.9, and the package is developed against the 6.5 line PeerKit Chat already requires.

The current version is `0.1.0` (`RazorSoft\ThemeAdapter\V0_1_0\Adapter::VERSION`). The git tag for a release is `v0.1.0`. Do not put a version in `composer.json`; the tag and `Adapter::VERSION` are the version.

## Consumers

PeerKit Chat is the existing consumer. In the PeerKit repository it loads `integrations/wordpress/peerkit-chat/vendor/theme-adapter/load.php`, and falls back to the sibling `theme-adapter` directory during development. Leave that checkout as it is until a later change points it at a bundle from this package.

Formlyx is the planned next consumer. The WordPress plugin is `razorsoft/formlyx`. Its plugin header currently allows PHP 7.4, and this package requires PHP 8.1, so Formlyx cannot bundle it until that requirement moves to 8.1. This package does not change the Formlyx plugin.

## What it reads

Block themes, and classic themes that include `theme.json`, are read with `wp_get_global_settings()`, `wp_get_global_styles()`, `wp_is_block_theme()`, and `wp_theme_has_theme_json()`.

Other classic themes use `get_theme_support('editor-color-palette')`, `get_background_color()`, and `get_header_textcolor()`. WordPress core's default palette is not treated as that theme's colors. A short script then reads the computed `body`, `button.wp-element-button`, and link styles, and only fills tokens the server marked as missing.

No stylesheet is parsed from disk, and nothing is sent to a remote service. Bootstrap, Tailwind, and theme-specific class names are not required.

## Public API

| Call | Role |
| --- | --- |
| `razor_theme_tokens(?array $source = null): array` | Normalized tokens for the active theme, or for a snapshot passed by tests |
| `razor_theme_css(?array $tokens = null): string` | The `:root` stylesheet for those tokens |
| `razor_theme_enqueue(): void` | Registers the stylesheet and, when values are missing, `assets/detect.js` |
| `apply_filters('razor_theme_tokens', $tokens)` | Replaces token values. The copy that is running seals the result before printing |
| `require load.php` | Returns the runtime for this directory |

`razor_theme_adapter()` returns the same runtime the global functions would use for the calling plugin. The runtime exposes `version()`, `packageDir()`, `styleHandle()`, `tokens()`, `css()`, and `enqueue()`.

The implementation classes live in `RazorSoft\ThemeAdapter\V0_1_0`. They are not global class aliases. Global aliases would collide as soon as two copies loaded.

## Tokens

The stylesheet is printed on `:root`. Consuming plugins use the variables and keep their own class names.

| Token | Custom property |
| --- | --- |
| Primary, primary contrast, secondary, secondary contrast | `--razor-theme-color-primary`, `--razor-theme-color-primary-contrast`, `--razor-theme-color-secondary`, `--razor-theme-color-secondary-contrast` |
| Background, text, border, link | `--razor-theme-color-background`, `--razor-theme-color-text`, `--razor-theme-color-border`, `--razor-theme-color-link` |
| Typography | `--razor-theme-font-family`, `--razor-theme-font-size`, `--razor-theme-font-weight`, `--razor-theme-line-height` |
| Radius, border width | `--razor-theme-radius`, `--razor-theme-border-width` |
| Spacing | `--razor-theme-spacing-block`, `--razor-theme-spacing-inline` |
| Button color, border, radius, padding, type | `--razor-theme-button-background`, `--razor-theme-button-color`, `--razor-theme-button-border-color`, `--razor-theme-button-border-width`, `--razor-theme-button-border-style`, `--razor-theme-button-radius`, `--razor-theme-button-padding-block`, `--razor-theme-button-padding-inline`, `--razor-theme-button-font-family`, `--razor-theme-button-font-size`, `--razor-theme-button-font-weight`, `--razor-theme-button-line-height` |

`enqueue()` prints the stylesheet for the current request. There is no stored copy, so the next page load follows the newly active theme. `--razor-theme-stylesheet` is the active stylesheet slug.

Values that are not a color, length, font stack, or WordPress preset variable are dropped. `url()`, expressions, and extra declarations cannot get through.

## Host plugin

Bundle the package, then keep the object this directory returns:

```php
$razor_theme = require __DIR__ . '/vendor/theme-adapter/load.php';
add_action('wp_enqueue_scripts', [$razor_theme, 'enqueue']);
add_action('enqueue_block_assets', [$razor_theme, 'enqueue']);
```

```css
.widget-button {
  background: var(--razor-theme-button-background);
  color: var(--razor-theme-button-color);
  border-radius: var(--razor-theme-button-radius);
}
```

The library does not style the host plugin's markup.

Calling `razor_theme_enqueue()` from the host plugin is still supported. Hook the returned object when more than one plugin may bundle the library. A string callback such as `add_action('wp_enqueue_scripts', 'razor_theme_enqueue')` has no plugin file on the stack. With a single bundle loaded, that callback uses the only bundle. With several bundles loaded, it enqueues each owner's copy instead of guessing.

Depend on `$razor_theme->styleHandle()`. While one owner is loaded, that handle is `razor-theme-adapter`. When several owners are loaded, each owner gets `razor-theme-adapter-{slug}-{hash}` so the registrations do not collide.

```php
wp_register_style(
    'my-plugin',
    plugins_url('assets/plugin.css', __FILE__),
    [$razor_theme->styleHandle()],
    '1.0.0',
);
```

## Several plugins, several versions

Two plugins may each ship this library. PHP cannot define the same class or function twice, and the copy that happens to load first must not become the implementation for every plugin.

This package separates those problems:

1. **Versioned code.** `0.1.0` lives in `RazorSoft\ThemeAdapter\V0_1_0`. The next release moves to a new namespace, such as `V0_2_0`, in the same commit that changes behavior or `Adapter::VERSION`. Two versions are different classes, so both can load. Reusing a namespace for different code would make the first file to load win.
2. **Same version, shared class body.** Two bundles of `0.1.0` share the class definition that loaded first. That is safe only because that version is one body of code. Asset paths still come from the directory that was registered. Bump the version when the code changes.
3. **Stable loader.** `src/Registry.php` and `src/functions.php` are defined once. `register()`, `resolve()`, `runtimes()`, `ownerCount()`, and `claimHandle()` stay compatible so an older bundle can be the one that defined them.
4. **The returned runtime is that directory.** `require load.php` always returns the copy in that folder, whatever else has loaded.
5. **Global functions follow the caller.** A call from `wp-content/plugins/{slug}`, `mu-plugins/{slug}`, or `themes/{slug}` uses that container. In a development checkout, a call from `includes/`, `src/`, `admin/`, `public/`, `blocks/`, or `templates/` uses the parent directory. A plugin that loaded two versions gets the newer one from the global functions. The older object it kept from `require` still runs the older code.
6. **No winner for an unrelated caller.** `razor_theme_tokens()` and `razor_theme_css()` throw `RazorSoft\ThemeAdapter\AmbiguousAdapter` when more than one owner is loaded and the caller is in none of them. `razor_theme_enqueue()` then publishes every owner's selected runtime, each on its own handle.
7. **One owner, one handle.** The newer version can replace `razor-theme-adapter` when the same owner enqueues twice. An equal version leaves the existing registration in place. Different owners never share that decision.
8. **One filter name.** `razor_theme_tokens` runs inside the copy that is building tokens. That copy seals the filtered values before they are printed. A callback should tolerate running once per copy.

`:root` custom properties are still one cascade. If two owners enqueue different values for the same property, the stylesheet that WordPress prints later wins that property. Each owner can also call `css()` and attach the result to its own stylesheet when it needs the values its own copy produced.

## Bundling

From this repository:

```bash
php packages/wordpress/bin/bundle.php /path/to/plugin/vendor/theme-adapter
```

The script copies `load.php`, `src/`, `assets/`, `composer.json`, `README.md`, `CHANGELOG.md`, and the MIT `LICENSE`. It does not copy tests. The destination must not already exist. Commit the result inside the plugin. Do not ask the site owner to install Composer or this package.

For local development, a path repository also works:

```json
{
  "repositories": [
    { "type": "path", "url": "../theme-adapter/packages/wordpress" }
  ],
  "require": {
    "razorsoft/wordpress-theme-adapter": "0.1.0"
  }
}
```

A path install still needs the plugin to `require` the installed `load.php`. Composer autoloading does not register a bundle, and a single shared `vendor` directory is one copy rather than a per-plugin bundle.

## Tests

```bash
php packages/wordpress/tests/run.php
```

The checks do not boot WordPress and do not use the network. They cover block themes, classic themes, global styles, fallbacks, unsafe values, the `razor_theme_tokens` filter, and two bundled versions loaded in either order.

## Releasing a new version

1. Change the code under a new `V{major}_{minor}_{patch}` namespace. Leave the previous namespace only in plugins that already bundled it.
2. Set `Adapter::VERSION` to the same numbers.
3. Record the change in `packages/wordpress/CHANGELOG.md`.
4. Keep the stable loader methods compatible.
5. Tag `vX.Y.Z` when the release is cut. Tagging is a separate step from committing the package.
