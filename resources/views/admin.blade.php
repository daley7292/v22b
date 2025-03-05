<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="/assets/admin/components.chunk.css">
    <link rel="stylesheet" href="/assets/admin/umi.css">
    <link rel="stylesheet" href="/assets/admin/custom.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title>{{$title}}</title>
    <script>window.routerBase = "/";</script>
    <script>
        window.settings = {
            title: '{{$title}}',
            theme: {
                sidebar: '{{$theme_sidebar}}',
                header: '{{$theme_header}}',
                color: '{{$theme_color}}',
            },
            version: '{{$version}}',
            background_url: '{{$background_url}}',
            logo: '{{$logo}}',
            secure_path: '{{$secure_path}}'
        }
    </script>
</head>

<body>
<div id="root"></div>
<script src="/assets/admin/vendors.async.js"></script>
<script src="/assets/admin/components.async.js"></script>
<script src="/assets/admin/umi.js"></script>
<script src="/assets/admin/js.js"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-SXF603RWJM');
</script>
</body>

</html>
