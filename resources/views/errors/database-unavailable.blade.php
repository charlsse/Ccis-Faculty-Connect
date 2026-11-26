<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporarily unavailable | FacultyConnect</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f7f5; color: #183d32; font: 16px/1.6 system-ui, sans-serif; }
        main { max-width: 34rem; padding: 2rem; }
        h1 { line-height: 1.2; }
        a { display: inline-block; padding: .7rem 1.2rem; border-radius: .5rem; color: white; background: #176348; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <p>FacultyConnect</p>
        <h1>We’re reconnecting</h1>
        <p>The website is temporarily reconnecting to its database. Please try again in a minute.</p>
        <a href="{{ request()->getPathInfo() }}">Try again</a>
    </main>
</body>
</html>
