<?php // Establish basic preconditions
header('X-Powered-By: Corn v0.1');
extension_loaded('dba') or exit('The dba extension is not available!');
in_array('lmdb', dba_handlers())
    or in_array('gdbm', dba_handlers())
    or exit('Neither lmdb or gdbm implementations are available for dba!');
$DBA_HANDLER = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'gdbm';
// Use the override when provided (e.g. Travis CI) otherwise use HOME dir
$DBA_PATH = ($_ENV['CORN_DBA_PATH_OVERRIDE'] ?? $_ENV['HOME']) . 'cornchan.db';

// Utility functions
function dba_replace_encode($key, $value, $handle) {
    return dba_replace($key, json_encode($value), $handle);
}

function dba_fetch_decode($key, $handle) {
    return json_decode(dba_fetch($key, $handle));
}

// Create the db if it doesn't exist
if (!file_exists($DBA_PATH)) {
    $db = dba_open($DBA_PATH, 'c', $DBA_HANDLER)
        or exit('Can\'t initialize a new db file!');

    $boards = ['corn', 'prog'];
    dba_replace('metadata_id', '10000', $db);
    dba_replace('metadata_name', 'cornchan', $db);
    dba_replace_encode('metadata_boards', $boards, $db);
    foreach ($boards as $board) {
        dba_replace($board . '_head_next', $board . '_tail', $db);
        dba_replace($board . '_tail_prev', $board . '_head', $db);
    }
    // Close after db initialization
    dba_close($db);
}

// Open the db for reading
$db = dba_open($DBA_PATH, 'r', $DBA_HANDLER)
    or exit('Can\'t open the db file!');

// General use params
$NAME = dba_fetch('metadata_name', $db);
$BOARDS = dba_fetch_decode('metadata_boards', $db);
$PATH = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('/^(?:\/(' . join('|', $BOARDS) . '))?(?:\/(\d+))?(?:\/(new))?\/?$/',
    $PATH, $matches);
$BOARD = $matches[1];
$THREAD = $matches[2];
$NEW = $matches[3];

$CANONICAL = '/'; // Build expected path
empty($BOARD)   or $CANONICAL .= $BOARD;
empty($THREAD)  or $CANONICAL .= '/' . $THREAD;
empty($NEW)     or $CANONICAL .= '/' . $NEW;
if (!empty($BOARD) && empty($THREAD) && empty($NEW)) {
    $CANONICAL .= '/';
}

// Return 404 on bad path; redirect if non-canonical
if ((empty($BOARD) && empty($THREAD) && empty($NEW) && $PATH != '/')
    || (empty($BOARD) && (!empty($THREAD) || !empty($NEW)))) {
    http_response_code(404);
} elseif ($PATH != $CANONICAL) {
    header('Location: ' . $CANONICAL, true, 301);
    dba_close($db);
    exit(0);
}

// If posting a new thread
if (!empty($BOARD) && !empty($NEW) && !empty($_POST['lorem'])) {
    dba_close($db); // Close the db for reads and reopen for writes
    $db = dba_open($DBA_PATH, 'w', $DBA_HANDLER);
    
    // Get the ID for the new thread
    $current_id = strval(intval(dba_fetch('metadata_id', $db)) + 1);
    dba_replace('metadata_id', $current_id, $db);

    // Insert the new thread at the head of the board
    $old_head_next = dba_fetch($BOARD . '_head_next', $db);
    dba_replace($BOARD . '_head_next', $current_id, $db);
    dba_replace($current_id . '_next', $old_head_next, $db);
    dba_replace($current_id . '_prev', $BOARD . '_head', $db);
    dba_replace($old_head_next . '_prev', $current_id, $db);

    // Sanitize the inputs; truncate at max length
    $headline = filter_var($_POST['lorem'], FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_var($_POST['ipsum'], FILTER_SANITIZE_SPECIAL_CHARS);
    dba_replace($current_id . '_headline', substr($headline, 0, 64), $db);
    dba_replace($current_id . '_message', substr($message, 0, 4096), $db);

    header('Location: /' . $BOARD . '/', true, 302);
    dba_close($db);
    exit(0);
} ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $NAME; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php // Check for error status code
if (http_response_code() != 200) { ?>
    <pre>Error <?php echo http_response_code(); ?></pre>
<?php // If at root path
} elseif (empty($BOARD) && empty($THREAD)) {
    foreach ($BOARDS as $board) {
        $board_path = '/' . $board . '/'; ?>
    <p><a href="<?php echo $board_path; ?>"><?php echo $board_path; ?></a></p>
<?php // End foreach board
    }
} elseif (!empty($BOARD) && empty($THREAD)) { ?>
    <h2>/<?php echo $BOARD; ?>/</h2>
<?php // List threads for the board
    $current_id = dba_fetch($BOARD . '_head_next', $db);
    while ($current_id != $BOARD . '_tail') {
        $thread_headline = dba_fetch($current_id . '_headline', $db);
        $thread_message = dba_fetch($current_id . '_message', $db); ?>
    <div id="thread-<?php echo $current_id; ?>">
        <p><?php echo $thread_headline; ?></p>
        <pre><?php echo $thread_message; ?></pre>
    </div>
<?php // End of foreach thread loop
        $current_id = dba_fetch($current_id . '_next', $db);
    } ?>
    <div id="newthread">
        <h3>New Thread</h3>
        <form method="post" action="/<?php echo $BOARD; ?>/new">
<?php // If form wasn't filled-out right
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['lorem'])) { ?>
            <p>You need a headline</p>
<?php
    } ?>
            <input type="text" name="lorem">
            <br><textarea name="ipsum"></textarea>
            <br><button>Submit</button>
        </form>
    </div>
<?php
}
// Final db close
dba_close($db);
// Calculate the milliseconds it took to render the page
$exec_time = intval((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000); ?>
    <pre><?php echo $exec_time; ?> milliseconds</pre>
</body>
</html>
