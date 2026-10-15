# Deployment and rollback

Target: Portare staging at `https://portare.up.railway.app`, Railway project portare-staging, web service liveedge-web. Railway calls its environment production; the verified WordPress application environment must be staging. This is NOT liveedgedesign.com.

Package only runtime PHP/assets/readme/LICENSE/README and docs. Transfer archive over authorized Railway SSH, verify SHA-256 before extraction, lint target PHP, activate with WP-CLI, read back active plugin version/settings, then exercise frontend/admin in a real browser. The installed directory is on the existing WordPress persistent volume. Rebuilding that volume requires reinstalling from the source ZIP/repository.

Rollback: deactivate portare-unit-converter. Reloading pages returns untouched original inch HTML. Do not uninstall other plugins or restore the database. Uninstall removes only puc_settings. No content migration is needed.

Verify the Settings API through authenticated browser save/reload, including all placement choices, colors, rounding, default units, and master disable. Compare baseline hashes of authored content, Brizy metadata, product dimensions, and existing plugin settings after deployment. Temporary QA sessions must be revoked.
