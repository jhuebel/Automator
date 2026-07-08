# Single Sign-On (Microsoft Entra ID / Google)

Automator supports SSO login via Microsoft Entra ID (Azure AD) and Google, in addition
to — not instead of — the built-in username/password login. Everything is configured
from **Settings → Single Sign-On**; there are no `.env` variables or redeploys involved.
Credentials are encrypted at rest, the same way the Anthropic API key is (see
[docs/DATA_MODEL.md](DATA_MODEL.md#app_settings)).

## How it decides who gets an account

On first SSO login, Automator looks for an existing account in this order:

1. **Already linked** — a previous SSO login from this same Microsoft/Google identity.
2. **Matching email** — an admin already created a user (Settings → Users) with this
   email address. That account is linked to the SSO identity and used from then on.
3. **Auto-provisioning** (if enabled) — a new account is created automatically, assigned
   the configured default role, with no local password (see below).
4. Otherwise, the sign-in is rejected with "No Automator account found for
   `{email}`. Contact your administrator."

Auto-provisioning and its optional email-domain restriction are both toggles in
Settings → Single Sign-On — off by default, so a fresh install requires an admin to
provision every account (via SSO-matched email or manually) until you explicitly turn it
on.

Accounts created via auto-provisioning have no local password — they can only sign in
via SSO. The **My Account** page's password-change form is hidden for these accounts.

## Setting up Microsoft Entra ID

1. In the [Entra admin center](https://entra.microsoft.com), go to **App registrations →
   New registration**.
2. Name it (e.g. "Automator"), leave the account type as your default, and set the
   **Redirect URI** (platform: **Web**) to the value shown on the Settings → Single
   Sign-On page — `{your-automator-url}/auth/entra/callback`.
3. Under **Certificates & secrets**, create a new client secret and copy its value
   immediately (it's only shown once).
4. Under **API permissions**, the default `User.Read` (Microsoft Graph, delegated) is
   sufficient — that's all Automator requests.
5. From the app's **Overview** page, copy the **Application (client) ID** and
   **Directory (tenant) ID**.
6. In Automator's Settings → Single Sign-On, enable Microsoft Entra ID and paste in the
   client ID, client secret, and tenant ID. Leave the tenant ID blank (defaults to
   `common`) to allow any work/school account to attempt sign-in, or set it to your
   tenant ID to restrict sign-in to your organization at the Microsoft side (in addition
   to, or instead of, the domain-restriction setting below).

## Setting up Google

1. In the [Google Cloud Console](https://console.cloud.google.com), select or create a
   project, then go to **APIs & Services → Credentials**.
2. If you haven't already, configure the **OAuth consent screen** (External or Internal,
   depending on your Google Workspace setup).
3. **Create Credentials → OAuth client ID**, application type **Web application**.
4. Add the redirect URI shown on the Settings → Single Sign-On page —
   `{your-automator-url}/auth/google/callback` — under **Authorized redirect URIs**.
5. Copy the generated **Client ID** and **Client Secret**.
6. In Automator's Settings → Single Sign-On, enable Google and paste them in.

Unlike Entra (which is inherently scoped to a tenant), Google sign-in is open to any
Google account by default — set **Allowed email domains** if you want to restrict
auto-provisioning to your organization's domain(s).

## Notes

- Both providers' OAuth flow uses Socialite's standard session-backed `state` parameter
  for CSRF protection — this isn't a stateless/API integration, it's a normal browser
  login redirect.
- Disabling a provider (or leaving its client ID/secret blank) makes its `/auth/{provider}/redirect`
  and `/auth/{provider}/callback` routes 404 and hides its button on the login page —
  existing users linked to that provider simply fall back to password login (or, if
  they were auto-provisioned and have no password, ask an admin to set one via
  Settings → Users).
- See [docs/DATA_MODEL.md](DATA_MODEL.md) for the exact `users`/`app_settings` columns
  involved, and [docs/ARCHITECTURE.md](ARCHITECTURE.md) for how this fits into the rest
  of the app.
