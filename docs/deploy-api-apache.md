# Deploy API on Apache

Upload the entire local `api/` folder to the domain document root as `api/`:

```text
public_html/
  index.html
  assets/
  api/
    .htaccess
    index.php
    app/
```

Do not upload `api/app/Config/Env.example.php` as `Env.php`. Create `api/app/Config/Env.php` directly on the server with production database and provider settings; it must not be committed or publicly accessible.

Apache needs `mod_rewrite` enabled and `AllowOverride FileInfo` (or `All`) for `api/.htaccess`. Confirm PHP extensions `mysqli`, `curl`, `fileinfo`, and `zip` are enabled.

After deployment, check these URLs:

```text
https://vpictura.com/api/
https://vpictura.com/api/Example/Health/check
https://vpictura.com/api/Store/Catalog/home
```

The last endpoint needs the Vita Pictura database migrations and catalog records before it can return catalog data.
