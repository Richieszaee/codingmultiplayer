CodeLive (PHP + SQLite edition)
================================
A collaborative VS Code-style editor. No Node.js, no database server, no build step.

INSTALL (2 minutes)
1. Upload this zip to your PHP host (cPanel > File Manager > Upload),
   into public_html or any sub-folder.
2. Right-click the zip > Extract. Delete the zip afterwards.
3. Open your site. Done. The database is created automatically in the data/ folder.

CHECK IT WORKS
Open  your-site/api.php?r=/health   -> it should show {"ok":true,...}
If it says the data folder is not writable, set the folder permissions to 755 (or 775).

REQUIREMENTS
PHP 8.0 or newer with the pdo_sqlite extension (on by default on almost every host).

NOTES
- Anyone with a workspace invite code can edit that workspace. Share links only with people you trust.
- On nginx (not Apache), add this rule so the database cannot be downloaded:
      location ^~ /data/ { deny all; }
- To back up everything, copy the data/ folder.
- Limits (edit the constants at the top of api.php): 1 MB per file, 300 files per workspace,
  20 new workspaces per IP per hour.
