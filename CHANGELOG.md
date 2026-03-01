# Changelog - LexHoy Despachos

## [1.2.3] - 2026-03-01
### Added
- **Aggressive Sitemap Discovery**: Added filters to force RankMath to include LexHoy silos in the `sitemap_index.xml` even if terms are empty.
- **Taxonomy Flags**: Added `show_in_sitemap` and `publicly_queryable` to `provincia` and `area_practica` for better SEO plugin compatibility.

## [1.2.2] - 2026-03-01
### Changed
- **RankMath Compatibility**: Updated taxonomy registration to be explicitly public (required for RankMath).
- **Bugfix**: Fixed misplaced method in main plugin file; correctly moved sitemap logic to the CPT class.

## [1.2.1] - 2026-03-01
### Changed
- **SEO Fix**: Adjusted `template_redirect` priorities to resolve a redirection loop between clean URLs and 404 handlers.
- **Aesthetics**: Refined premium card styles and verification badges in `silos.css`.

## [1.2.0] - 2026-03-01 (Major SEO Update)
### Added
- **Silo Architecture**: New taxonomy-based silos for `/provincia/` and `/especialidad/`.
- **Clean URLs**: Removed the `/despacho/` slug from lawyer profiles for a flatter, more authoritative URL structure.
- **Premium Design System**: New `assets/css/silos.css` implementing LexHoy's high-end aesthetic (Black, Red, White).
- **Interlinking Widget**: New shortcode `[lexhoy_abogados_relacionados]` to distribute authority from blog posts to lawyers.
- **SEO Safety**: Implemented global `noindex` logic for thin content and automated 301 redirects for legacy URLs.
- **Migration Tool**: Admin utility to migrate province meta-data to the new taxonomy.
