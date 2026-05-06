## What

<!-- Brief description of the change -->

## Why

<!-- What problem does this solve? Link to issue if applicable -->

## Testing

<!-- How was this tested? -->

- [ ] Unit tests pass (`composer test`)
- [ ] Linting passes (`composer check`)
- [ ] JS build succeeds (`npm run build`)
- [ ] Manually tested in wp-env (if UI change)

## Checklist

- [ ] No new PHPCS or PHPStan warnings
- [ ] All output is escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- [ ] All inputs are sanitized
- [ ] Database queries use `$wpdb->prepare()` or safe patterns
- [ ] New strings are translatable (`__()`, `esc_html__()`)
- [ ] Tests added/updated for changed code
- [ ] No sensitive data in code (API keys, passwords, tokens)
