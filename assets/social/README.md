# InkWall social preview

The three files in this directory contain the same 1280 x 640 composition:

- `inkwall-social-preview.png` is the lossless source for GitHub's repository
  social preview setting.
- `inkwall-social-preview.webp` is the compact web variant.
- `inkwall-social-preview.jpg` is a progressive fallback for integrations that
  do not accept WebP.

The public InkWall page continues to generate its per-note Open Graph image
dynamically. These static files identify the repository and product itself.
