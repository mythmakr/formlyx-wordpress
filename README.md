# Formlyx Forms

Official WordPress plugin for the hosted [Formlyx](https://www.formlyx.com) service. Formlyx stores forms and submissions. This repository is the free plugin intended for WordPress.org (`formlyx` slug).

Version 1.0.0 connects a WordPress site to a Formlyx account and embeds published forms with the Formlyx Form block or the `[formlyx]` shortcode.

## Compatibility

| Requirement | Version | Why |
| --- | --- | --- |
| WordPress | 6.7 or newer, tested to 7.1 | 6.7 through 7.1 were still receiving maintenance releases in October 2026. WordPress.org statistics on 16 October 2026 put about 85% of sites on 6.7 or newer. 7.1.3 is the current release. |
| PHP | 8.1 or newer | The bundled RazorSoft Theme Adapter 0.1.0 requires PHP 8.1. WordPress 6.7 supports that version. |
| Node.js | `^22.22.2`, `^24.15.0`, or `>=26`, development only | Required by `@wordpress/scripts` 36. Production sites do not need Node.js. |

## Connect a Formlyx account

1. Install and activate Formlyx Forms.
2. Open **Settings → Formlyx**.
3. Choose **Connect to Formlyx**, or **Create a Formlyx account** if you need a new account.
4. Sign in on Formlyx, or create an account there and verify the email Formlyx sends. Formlyx returns you to the approval screen after verification.
5. Approve access to read forms. WordPress does not ask for your Formlyx password and does not use an API key.

The site must be served over HTTPS. HTTP is accepted only for `localhost`, `127.0.0.1`, and `::1`, which is the same rule Formlyx uses for redirect URLs.

The callback address is:

`https://example.com/wp-admin/admin-post.php?action=formlyx_oauth_callback`

Stay logged in to WordPress in the same browser while you approve access. The callback is available only to an administrator with the `manage_options` capability.

**Check connection** calls `GET https://api.formlyx.com/v1/workspace`. **Reconnect** starts approval again and keeps the current connection until the new approval succeeds. **Disconnect** removes the local tokens and asks Formlyx to revoke the refresh token. The screen says whether Formlyx confirmed that revocation. Disconnect does not delete the Formlyx account, hosted forms, or submissions.

## OAuth

The plugin is a public OAuth client. It registers itself with `POST https://api.formlyx.com/oauth/register` (`token_endpoint_auth_method: none`) and reuses that registration until the callback URL changes. Formlyx does not offer an API to update or delete a registration, so a changed site URL creates a new client and the previous registration remains in Formlyx.

Authorization uses the code flow with PKCE S256, scopes `forms:read` and `forms:embed`, and resource `https://api.formlyx.com/v1`. The plugin does not request the MCP resource or form-editing scopes. `forms:embed` lets an editor add this site's own address to one form. It does not allow every domain. A connection made before that scope existed can still list forms; approving a site requires reconnecting. The code verifier and tokens stay on the server. The browser only receives `code`, `state`, and `iss`. The editor never sends an origin: WordPress derives it from the site address.

Access tokens last about one hour. Refresh tokens last 30 days, and Formlyx rotates them. Refresh uses an `add_option()` lock named `formlyx_refresh_lock` so two requests do not rotate the same token. The lock expires after 30 seconds. A request that loses the lock waits briefly, then uses the token pair saved by the request that won. If the stored refresh token changed while a refresh was in flight, including when an administrator disconnected, that response is discarded.

The callback must include `iss=https://api.formlyx.com`. A missing or different issuer is rejected and nothing is saved.

## Embed a form

Visitors load the published form from Formlyx. WordPress does not render the fields or store the submission.

1. Open a page in the block editor.
2. Insert the **Formlyx Form** block (Widgets).
3. Choose a published form, or type its form ID.
4. Publish the page.

A public form can be embedded by ID without connecting the site. The picker needs the connection because it lists that account's forms. The list is cached for five minutes. **Refresh forms** in the block loads it again. Connecting, reconnecting, or disconnecting clears the cache.

The editor shows the form name and ID. The live form appears on the published page. Formlyx's embed script looks for `data-form-id` when it loads and does not document a way to start that scan again inside the editor, so the block does not inject `embed.js` there.

The picker keeps at most 100 forms. Each name is limited to 200 characters and each description to 300. The form-list response itself is limited to 1 MB. A response larger than the limit is rejected and is not cached. An administrator can still type a public form ID.

```text
[formlyx id="FORM_ID"]
[formlyx id="FORM_ID" theme="auto"]
[formlyx id="FORM_ID" theme="match"]
```

`theme` accepts `auto` (the default), `light`, `dark`, `inherit`, `unstyled`, and `match`. `auto`, `light`, `dark`, `inherit`, and `unstyled` are the values `embed.js` understands. An unknown value is saved as `auto`.

`match` is **Match WordPress Theme**. The block sidebar lists it under Appearance, and the shortcode uses `theme="match"`. The plugin reads the active theme with the bundled Theme Adapter on that WordPress site, including a private or local site. It does not call SiteLens and it does not write the result back to the form saved in Formlyx.

The container keeps `data-theme="match"`. Published `embed.js` treats an unrecognized `data-theme` as `auto`, so the saved Formlyx tokens remain the fallback. The plugin sets these host variables on `.formlyx-embed[data-theme="match"]`, and `embed.js` copies them onto the fields it renders in the page:

| Host variable | Theme Adapter token |
| --- | --- |
| `--formlyx-primary` | `--razor-theme-color-primary` |
| `--formlyx-background` | `--razor-theme-color-background` |
| `--formlyx-text` | `--razor-theme-color-text` |
| `--formlyx-muted` | A mix of the theme text and background. An invalid mix leaves the saved muted color in place. |
| `--formlyx-border` | `--razor-theme-color-border` |
| `--formlyx-radius` | `--razor-theme-radius` |
| `--formlyx-font` | `--razor-theme-font-family` |
| `--formlyx-input-bg` | `--razor-theme-color-background` |
| `--formlyx-button-bg` | `--razor-theme-button-background` |
| `--formlyx-button-text` | `--razor-theme-button-color` |

Those variables reach labels, inputs, and the submit button because `embed.js` paints the form inside the container and reads `var(--formlyx-*, …)`. The rules are limited to `.formlyx-embed`. They do not restyle the rest of the WordPress theme.

Deliberate exceptions, because `embed.js` sets them itself for the `auto` path that `match` uses:

- Validation and success text stay the embed colors (`#b91c1c` and `#0f766e`). Required marks stay red. Theme accent colors are not applied to those states.
- The submit button stays a pill. `embed.js` sets `border-radius: 999px` inline, so the theme button radius is not used.
- Input border width, field padding, font size, and the form's spacing stay the embed layout.
- Focus rings stay the browser and embed defaults. `embed.js` does not provide a hover token, so hover is left unchanged.
- A classic theme that does not declare button metrics can update those button variables after the first paint. The adapter's `detect.js` fills only the gaps it marked. Block themes that already define the values do not load that script.

Another plugin may bundle Theme Adapter too. Formlyx keeps the runtime returned by its own `vendor/theme-adapter/load.php` and enqueues that copy only when a form uses `match`. Same-version bundles share one class body. Different owners receive different style handles. `:root` custom properties still share one cascade, so two different adapter versions can disagree on a property; the stylesheet WordPress prints later wins that property.

Several blocks and shortcodes can appear on one page, including two copies of the same form. The page loads `https://www.formlyx.com/embed.js` once, in the footer. If a form is rendered after the footer has already printed, the plugin prints that script at the form.

The container is:

```html
<div class="formlyx-embed" data-form-id="FORM_ID" data-theme="auto"></div>
```

## External service

Published pages request `https://www.formlyx.com/embed.js`. That script fetches the public form definition from Formlyx and sends the visitor's submission to Formlyx. It does not receive the site's OAuth tokens. The plugin does not vendor or replace that script: Formlyx renders the form and accepts the submission, and this plugin does not include a second renderer.

Formlyx's terms: https://www.formlyx.com/terms

Formlyx's privacy policy: https://www.formlyx.com/privacy

Settings → Privacy shows suggested policy text for the stored tokens and for visitor submissions. `readme.txt` has the same service disclosure for the WordPress.org listing.

This plugin is not a statement of WordPress.org directory approval. The hosted embed script remains a remote script on purpose. Directory review may still flag that loading.

## Token storage

Connection data is in the option `formlyx_connection`. The client id is in `formlyx_oauth_client`. An in-progress approval is in `formlyx_oauth_pending`. These options are not autoloaded. WordPress options are not encrypted; the plugin does not claim that they are. Tokens are not written to JavaScript, HTML, logs, or the public site.

Uninstall deletes those options, the refresh lock, and cached form metadata. It does not delete WordPress posts or pages, hosted forms, submissions, or the Formlyx account.

## Troubleshooting

| What you see | What it means |
| --- | --- |
| This site needs HTTPS | The callback is not HTTPS, and the host is not localhost. |
| That connection attempt expired | More than 10 minutes passed before you returned from Formlyx. Start again. |
| The previous approval is no longer valid | The refresh token expired, was revoked, or was already rotated. Connect again. |
| Formlyx could not be reached | The tokens are still saved. Check the connection when the network is available. |
| Formlyx did not confirm that access was removed | This site forgot the tokens, but revocation did not succeed. Remove the connection in the Formlyx account if it is still listed. |
| Connect Formlyx to select a form | The picker needs a connection. You can still type a public form ID. |
| The Formlyx connection has expired | Reconnect from Settings → Formlyx. |
| No published forms are available for embedding | The account has no form that is both published and enabled for public embedding. |
| The form is missing on the page | The block or shortcode has no usable form ID, or the form is not published for public embedding. The live form is rendered by Formlyx, not in the editor. |

## Development

```bash
composer install
npm install
composer syntax
composer lint
composer compat
composer analyse
composer test
npm run lint:js
npm run build
npm run plugin-zip
```

| Command | Purpose |
| --- | --- |
| `composer syntax` | PHP syntax |
| `composer lint` | WordPress Coding Standards |
| `composer lint:fix` | Automatic coding-standard fixes |
| `composer compat` | PHP 8.1 and newer |
| `composer analyse` | PHPStan level 8 |
| `composer test` | Unit tests with mocked Formlyx HTTP |
| `composer test:integration` | WordPress integration tests |
| `npm run build` | Build `src/` with `@wordpress/scripts` |
| `npm run lint:js` | ESLint |
| `npm run plugin-zip` | Build the WordPress package |

Unit tests do not call production Formlyx. Integration tests need MySQL and the WordPress 7.1.3 test library:

```bash
export WP_TESTS_DIR=/tmp/formlyx-wordpress-tests
export WP_CORE_DIR=/tmp/formlyx-wordpress-core
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 7.1.3
composer test:integration
```

GitHub Actions runs those integration tests. This workstation does not use Docker.

This machine's Node.js is 25.9.0, which is outside the `@wordpress/scripts` range, so `npm run check-engines` fails here. The JavaScript build still runs. No Node version was installed for this project.

`npm run build` writes `build/`. The plugin package includes that directory. `node --test tests/js/picker-state.test.cjs` checks the form-picker rules.

## Theme Adapter

Formlyx bundles RazorSoft Theme Adapter `0.1.0` (`razorsoft/wordpress-theme-adapter`).

| | |
| --- | --- |
| Repository | https://github.com/razorsoftinc/theme-adapter |
| Tag | `v0.1.0` |
| Commit | `02a1354e42a53c23ae0f3e2eb0c1e088f8619577` |
| Directory | `vendor/theme-adapter/` |
| Record | `vendor/theme-adapter-source.json` |

Regenerate that copy from a clean checkout of the tag:

```bash
php bin/bundle-theme-adapter.php
```

Pass the checkout path when it is not the sibling directory `../theme-adapter`. The script checks the tag, commit, and a clean worktree, then runs `packages/wordpress/bin/bundle.php`. It does not modify the Theme Adapter repository. Site owners do not run it, and they do not install Composer or a second plugin.

`includes/ThemeAdapter.php` loads `vendor/theme-adapter/load.php` and enqueues the returned runtime for `theme="match"`. `includes/embed.css` maps the published `--razor-theme-*` properties onto the `--formlyx-*` properties `embed.js` already applies to the form. The page still loads `https://www.formlyx.com/embed.js`.

## Internationalization

The text domain is `formlyx`. English is the source language. Brazilian Portuguese is planned and is not included. WordPress loads PHP translations just in time. The block editor script calls `wp_set_script_translations()` with the `formlyx` text domain and `languages/`.

`languages/formlyx.pot` is the source catalog. Regenerate it with WP-CLI:

```bash
wp i18n make-pot . languages/formlyx.pot --domain=formlyx --exclude=node_modules,vendor,tests,build --headers='{"Report-Msgid-Bugs-To":"https://www.formlyx.com"}'
```

## Packaging

`npm run plugin-zip` uses `wp-scripts plugin-zip`, then `bin/filter-plugin-zip.py` removes development files and adds `vendor/theme-adapter/`. wp-scripts does not pick up that directory on its own. The package contains the bootstrap, `includes/`, the bundled Theme Adapter, `vendor/theme-adapter-source.json`, the built block in `build/`, `readme.txt`, and `license.txt`. Install that zip from Plugins → Add New → Upload Plugin.

There is no deploy workflow and no WordPress.org SVN publishing.

## License

GPL-2.0-or-later. See `license.txt`.
