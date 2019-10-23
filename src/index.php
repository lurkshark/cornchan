<?php // Establish basic preconditions
header('X-Powered-By: Corn v0.1');
extension_loaded('dba') or exit('The dba extension is not available!');
in_array('lmdb', dba_handlers())
    or in_array('dba', dba_handlers())
    or exit('Neither lmdb or db4 implementations are available for dba!');
$DBA_HANDLER = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'db4';
$BOARDS = ['corn', 'prog'];

// General use params
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/^\/(' . join('|', $BOARDS) . ')\/?(\d*)\/?$/', $path, $matches);
$board = $matches[1];
$post = $matches[2];

// Redirect /board to /board/
if (!empty($board) && empty($post) && $path != '/' . $board . '/') {
    header('Location: /' . $board . '/', true, 301);
}

// Redirect /board/100/ to /board/100
if (!empty($board) && !empty($post) && $path != '/' . $board . '/' . $post) {
    header('Location: /' . $board . '/' . $post, true, 301);
} ?>
<!DOCTYPE html>
<html>
<head>
    <title>ping</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <pre>OK</pre>
</body>
</html>
