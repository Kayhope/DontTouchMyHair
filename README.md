# Don't Touch My Hair — Website (GitHub Pages ready)

This folder is set up to go straight onto GitHub and be served live, for free, over https — no separate hosting needed. This works because the current site is the "no-server" version (see the main README you already have for what that means and its trade-off around bookings/orders being saved per-browser rather than shared).

## Putting it on GitHub and going live

1. **Create a GitHub account**, if you don't have one already, at github.com — it's free.
2. **Create a new repository**: click the **+** in the top right → **New repository**. Give it a name (e.g. `dont-touch-my-hair`), leave it **Public**, and click **Create repository**. Don't add a README/license — leave everything else unticked.
3. On the new (empty) repo's page, click **uploading an existing file**.
4. Open this folder on your computer, select **everything inside it** (`index.html`, `AboutUs`, `Book`, `Image`, `Mypage`, `nav.css`, `styles.css`, `README.md`) and **drag them all into the upload box** in your browser. Important: drag the *contents* of this folder, not the folder itself — `index.html` needs to end up at the top level of the repository, not inside a subfolder.
5. Scroll down, leave the default commit message (or write your own, like "Add website files"), and click **Commit changes**.
6. In the repository, go to **Settings** (top menu) → **Pages** (left sidebar).
7. Under "Build and deployment," set **Source** to **Deploy from a branch**, **Branch** to **main** and folder to **/ (root)**, then click **Save**.
8. Wait about a minute, then refresh that Settings → Pages screen. GitHub will show you the live address, something like:
   `https://<your-github-username>.github.io/<repository-name>/`
9. Open that address — your site is now live on the internet over https, and you can share that link with anyone.

## Updating the site later

Any time you want to change something, edit the files and go back to the repository → **Add file** → **Upload files** → drag in the changed file(s) → **Commit changes**. GitHub Pages automatically republishes within a minute or two of any commit.

## Worth remembering once this is public

This is still the "no-server" version — bookings and hair-oil orders are saved in each visitor's own browser, not in one place you can see. That's fine for showing people a live link and letting them click around, but real customers booking from their own phones **will not** show up on your `Mypage/track.html` admin page, since there's nowhere shared for that data to land. When you're ready to fix that properly, the earlier PHP + database version of this site handles it — it just needs real PHP web hosting (GitHub Pages can only serve static files, not run PHP), which is a separate, small step from what's covered here.
