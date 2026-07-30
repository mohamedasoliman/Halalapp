# Firebase App Check rollout

Halal Kiwi uses Firebase App Check to distinguish genuine Android and iOS
installations from scripts calling the product API with an extracted API key.

App Check is an additional control. Keep the existing API key, bounded
pagination, exact-barcode validation, rate limits and security logging.

## Providers

- Android app `com.halalkiwi.halalkiwiapp`: Play Integrity
- iOS Firebase app
  `org.reactjs.native.mohamedasoliman.--PRODUCT-NAME-rfc1034identifier-`:
  App Attest (the app's minimum supported iOS version is 14)
- Debug builds: Firebase App Check debug provider

Register both production apps under **Firebase Console → Security → App Check
→ Apps** before distributing the App Check-enabled release. Follow the
Firebase Console and Google Play Console prompts for Play Integrity. The iOS
target must keep the App Attest production entitlement in
`Runner.entitlements`. Do not register or distribute debug tokens with a
production build.

## Safe rollout

The Laravel setting has three modes:

- `off`: ignore App Check.
- `monitor`: validate tokens when supplied and log `valid`, `missing` or
  `invalid`, while allowing every request.
- `enforce`: reject missing or invalid tokens with HTTP 401.

Use this order:

1. Register the Android and iOS apps with the production providers.
2. Deploy the backend with `APP_CHECK_MODE=monitor`.
3. Release the new mobile version containing `firebase_app_check`.
4. Review `storage/logs/api-security-*.log` and compare the
   `app_check_status` counts. Old app versions appear as `missing`.
5. Wait until the updated release is broadly installed.
6. Enable the existing minimum-version/force-upgrade policy for the new
   App Check-capable version.
7. Change `APP_CHECK_MODE=enforce` and clear Laravel's configuration cache.

Do not enable `enforce` before the minimum-version step. An older Halal Kiwi
version cannot produce an App Check token and would lose access to product
search and barcode lookup.

Example production rollout settings:

```dotenv
APP_CHECK_MODE=monitor
APP_CHECK_PROJECT_NUMBER=952667093663
APP_CHECK_ALLOWED_APP_IDS=1:952667093663:android:8623ef0d014e99e46140aa,1:952667093663:ios:6268a0b1a840c84b6140aa
```

After changing production settings:

```bash
php artisan config:clear
```

Emergency rollback is immediate and does not require an app update:

```dotenv
APP_CHECK_MODE=monitor
```

## What is protected

The middleware currently protects these endpoints:

- `POST /api/listing`
- `POST /api/listingcode`
- `POST /api/v2/products/search`
- `POST /api/v2/products/barcode`

The mobile app sends its token in `X-Firebase-AppCheck`. Laravel verifies the
RS256 signature, token expiry, Firebase project number and allowed Firebase
app ID using Firebase's rotating public keys. Fresh keys are cached for six
hours, with a seven-day stale-key fallback for short upstream outages.

Firebase Console enforcement controls supported Firebase products. Enforcement
for the self-hosted Halal Kiwi API is controlled by `APP_CHECK_MODE`.
