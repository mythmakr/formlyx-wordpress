# Formlyx Forms WordPress walkthrough

Isolated Remotion project for a 1920×1080, 30 fps product walkthrough. It is not part of the WordPress plugin package.

Capture uses Playwright against a local Chrome profile that is already signed in to WordPress and Formlyx. The script never types a password. It does not deactivate plugins, and it refuses the existing PeerKit and Formlyx Acceptance pages.

```sh
cd video/wordpress-walkthrough
npm install
FORMLYX_CHROME_PROFILE=/tmp/formlyx-video-chrome npm run capture
npm run render
npm run still
```

Outputs:

- `out/formlyx-wordpress-walkthrough.mp4`
- `out/formlyx-wordpress-thumbnail.png`

Raw captures stay in `public/captures/` and are not part of the plugin zip.
