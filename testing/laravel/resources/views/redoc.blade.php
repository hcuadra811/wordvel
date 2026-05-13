<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WordVel API Documentation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
<div id="redoc"></div>
<script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
<script>
    Redoc.init("{{ route('api.docs', ['jsonFile' => 'api-docs.json']) }}", {
        theme: {
            colors: {
                primary: {
                    main: '#111827'
                }
            }
        }
    }, document.getElementById('redoc'));
</script>
</body>
</html>
