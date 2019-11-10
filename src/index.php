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
    $subject = filter_var($_POST['lorem'], FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_var($_POST['ipsum'], FILTER_SANITIZE_SPECIAL_CHARS);
    dba_replace($current_id . '_subject', substr($subject, 0, 64), $db);
    dba_replace($current_id . '_message', substr($message, 0, 4096), $db);
    dba_replace($current_id . '_time', time(), $db);

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
  <link rel="stylesheet" href="/static/normalize.css">
  <link rel="stylesheet" href="/static/style.css">
  <link rel="icon" href="/static/favicon.png">
</head>
<body>
<?php // Check for error status code
if (http_response_code() != 200) { ?>
  <h1>Error <?php echo http_response_code(); ?></h1>
<?php // If at root path
} elseif (empty($BOARD) && empty($THREAD)) {
    foreach ($BOARDS as $board) {
        $board_path = '/' . $board . '/'; ?>
  <p><a href="<?php echo $board_path; ?>"><?php echo $board_path; ?></a></p>
<?php // End foreach board
    }
} elseif (!empty($BOARD) && empty($THREAD)) { ?>
  <header><h1>/<?php echo $BOARD; ?>/</h1></header>
  <main>
<?php // List threads for the board
    $current_id = dba_fetch($BOARD . '_head_next', $db);
    while ($current_id != $BOARD . '_tail' && empty($NEW)) {
        $thread_subject = dba_fetch($current_id . '_subject', $db);
        $thread_message = dba_fetch($current_id . '_message', $db);
        $thread_time = dba_fetch($current_id . '_time', $db); ?>
    <article id="thread<?php echo $current_id; ?>">
      <header>
        <hgroup>
          <h1><?php echo $thread_subject; ?></h1>
          <h2><?php echo $current_id; ?></h2>
          <h3><time><?php echo date('Y-m-d H:i', $thread_time); ?></time></h3>
        </hgroup>
      </header>
      <main><p><?php echo $thread_message; ?></p></main>
    </article>
<?php // End of foreach thread loop
        $current_id = dba_fetch($current_id . '_next', $db);
    } ?>
    <section id="newthread">
      <header><h1>New Thread</h1></header>
      <main>
        <form method="post" action="/<?php echo $BOARD; ?>/new">
<?php // If form wasn't filled-out right
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['lorem'])) { ?>
          <p>You need a headline</p>
<?php
    } ?>
          <p>
            <label for="lorem">Subject</label>
            <input type="text" name="lorem" autocomplete="off">
          </p>
          <p class="full">
            <label for="ipsum">Message</label>
            <textarea name="ipsum"></textarea>
          </p>
          <p>
            <button>Submit</button>
          </p>
        </form>
      </main>
    </section>
  </main>
<?php
}
// Final db close
dba_close($db);
// Calculate the milliseconds it took to render the page and last update
$exec_time = intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
$last_updated = date('Y-m-d H:i', filemtime(__FILE__)); ?>
  <footer>
    <small><?php echo $last_updated; ?> / <?php echo $exec_time; ?>ms</small>
  </footer>
</body>
</html>
