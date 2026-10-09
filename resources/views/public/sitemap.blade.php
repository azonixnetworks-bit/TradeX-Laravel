<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">@foreach($paths as $path)<url><loc>{{ url('/'.$path) }}</loc><lastmod>{{ now()->toDateString() }}</lastmod></url>@endforeach</urlset>
