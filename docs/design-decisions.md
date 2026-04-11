# Design Decisions

Documented tradeoffs and intentional choices in the codebase. These are not bugs — they are deliberate decisions with known consequences.

## Controller duplication (CollectController / EventController)

Both controllers share ~80% of their logic (JSON decode, validate, sanitize URL, check exclusion, generate fingerprint, persist). This is intentional: two single-action controllers are simpler to test and maintain than one combined controller.

The duplication is tolerable at 2 controllers. **If a third collect endpoint is added**, extract the shared infrastructure logic into a common service.

## Fingerprint accuracy limitations

`sha256(IP + UserAgent + date)` means:

- **Undercounting:** Users behind the same corporate proxy with the same browser appear as one visitor.
- **Overcounting:** Users whose IP changes frequently (mobile networks) appear as multiple visitors.

This is an accepted tradeoff for the cookieless/GDPR-compliant privacy model. Anyone consuming the analytics data should understand this margin of error.
