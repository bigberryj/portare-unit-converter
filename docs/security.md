# Security model

Admin configuration requires manage_options and WordPress Settings API nonces. Flags, modes, menu locations, colors, and dimensions are strictly sanitized. Selector strings are bounded and validated before DOM lookup; selectors cannot execute JavaScript. Frontend output is escaped and converted text uses text-node writes, not HTML parsing.

The converter does not access the network or send telemetry. Stored product dimensions, variation keys, form values, submissions, URLs, and authored builder content are unchanged. Inputs/editable areas are excluded. Implicit option values are pinned before labels are changed.

Browser-local storage is optional and failure must not prevent page-level switching. No secrets or personally identifying data are stored. Deploy only to verified Portare staging, without changing staging mail/payment isolation or production.
