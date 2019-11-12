<?php // Establish basic preconditions
header('X-Powered-By: Corn v0.3');
extension_loaded('dba') or exit('The dba extension is not available!');
in_array('lmdb', dba_handlers())
  or in_array('gdbm', dba_handlers())
  or exit('Neither lmdb or gdbm implementations are available for dba!');
$DBA_HANDLER = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'gdbm';
// Use the override when provided (e.g. Travis CI) otherwise use HOME dir
$DBA_PATH = ($_ENV['CORN_DBA_PATH_OVERRIDE'] ?? $_ENV['HOME']) . 'cornchan.db';

// Create the db if it doesn't exist
if (!file_exists($DBA_PATH)) {
  $db = dba_open($DBA_PATH, 'c', $DBA_HANDLER)
    or exit('Can\'t initialize a new db file!');

  $boards = ['corn', 'prog', 'news'];
  dba_replace('metadata_id', '9999', $db);
  dba_replace('metadata_name', 'cornchan', $db);
  dba_replace('metadata_boards', json_encode($boards), $db);
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
$BOARDS = json_decode(dba_fetch('metadata_boards', $db));
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

// Utility function for validating posts
function post_exists($board, $thread, $handler) {
  // If this thread just doesn't exist
  if (!empty($thread) && !dba_exists($thread . '_subject', $handler)) {
    return false;
  // Or if this thread exists but it's for a different board
  } elseif (!empty($board) && !empty($thread)
      && dba_fetch($thread . '_board', $handler) != $board) {
    return false;
  }
  // Otherwise it's OK
  return true;
}

// Redirect if the request is canonical except for slashes
if ($PATH == $CANONICAL . '/' || $PATH . '/' == $CANONICAL) {
  header('Location: ' . $CANONICAL, true, 301);
  dba_close($db);
  exit(0);
} elseif ($PATH != $CANONICAL // Else if the path is nonsensical
    // Or well-formatted but for a thread that doesn't exist
    || !post_exists($BOARD, $THREAD, $db)) {
  http_response_code(404);
}

// Utility functions for adding posts

// Gets and increment ID
function fresh_id($handle) {
  $fresh_id = strval(intval(dba_fetch('metadata_id', $handle)) + 1);
  dba_replace('metadata_id', $fresh_id, $handle);
  return $fresh_id;
}

// Upserts the post for the given ID
function update_post($id, $board, $subject, $message, $handle) {
  $subject = filter_var($subject, FILTER_SANITIZE_SPECIAL_CHARS);
  $message = filter_var($message, FILTER_SANITIZE_SPECIAL_CHARS);
  dba_replace($id . '_subject', substr($subject, 0, 64), $handle);
  dba_replace($id . '_message', substr($message, 0, 4096), $handle);
  dba_replace($id . '_board', $board, $handle);
  dba_replace($id . '_time', time(), $handle);
}

// Inserts a post at the head of the list
function insert_at_head($head_prefix, $id, $handle) {
  $old_head_next = dba_fetch($head_prefix . '_head_next', $handle);
  dba_replace($head_prefix . '_head_next', $id, $handle);
  dba_replace($id . '_next', $old_head_next, $handle);
  dba_replace($id . '_prev', $head_prefix . '_head', $handle);
  dba_replace($old_head_next . '_prev', $id, $handle);
}

// Inserts a post at the tail of the list
function insert_at_tail($tail_prefix, $id, $handle) {
  $old_tail_prev = dba_fetch($tail_prefix . '_tail_prev', $handle);
  dba_replace($tail_prefix . '_tail_prev', $id, $handle);
  dba_replace($id . '_next', $tail_prefix . '_tail', $handle);
  dba_replace($id . '_prev', $old_tail_prev, $handle);
  dba_replace($old_tail_prev . '_next', $id, $handle);
}

// If posting a new thread or reply
if (post_exists($BOARD, $THREAD, $db) && !empty($NEW)
    && $_SERVER['REQUEST_METHOD'] == 'POST') {
  // If new thread: POST /board/new
  if (!empty($BOARD) && empty($THREAD)
      && !empty($_POST['lorem'])) {
    dba_close($db); // Close the db for reads and reopen for writes
    $db = dba_open($DBA_PATH, 'w', $DBA_HANDLER);
    // Get the ID for the new thread
    $new_thread_id = fresh_id($db);
    // Insert the new thread at the head of the board
    insert_at_head($BOARD, $new_thread_id, $db);
    // Create the post for the new thread
    update_post($new_thread_id, $BOARD, $_POST['lorem'], $_POST['ipsum'], $db);
    // Initialize thread replies pointers
    dba_replace($new_thread_id . '_replies_head_next', $new_thread_id . '_replies_tail', $db);
    dba_replace($new_thread_id . '_replies_tail_prev', $new_thread_id . '_replies_head', $db);
    // Redirect to the board and exit
    $redirect_to = '/' . $BOARD . '/';
    header('Location: ' . $redirect_to, true, 302);
    dba_close($db);
    exit(0);
  // If new reply: POST /board/1000/new
  } elseif (!empty($BOARD) && !empty($THREAD)
      && (!empty($_POST['lorem']) || !empty($_POST['ipsum']))) {
    dba_close($db); // Close the db for reads and reopen for writes
    $db = dba_open($DBA_PATH, 'w', $DBA_HANDLER);
    // Remove the replied-to thread from its place
    $old_thread_next = dba_fetch($THREAD . '_next', $db);
    $old_thread_prev = dba_fetch($THREAD . '_prev', $db);
    dba_replace($old_thread_prev . '_next', $old_thread_next, $db);
    dba_replace($old_thread_next . '_prev', $old_thread_prev, $db);
    // Insert the replied-to thread at the head of the board
    insert_at_head($BOARD, $THREAD, $db);
    // Get the ID for the new reply
    $new_reply_id = fresh_id($db);
    // Create the post for the new reply
    update_post($new_reply_id, $BOARD, $_POST['lorem'], $_POST['ipsum'], $db);
    // Insert the reply at the tail of the thread replies
    insert_at_tail($THREAD . '_replies', $new_reply_id, $db);
    // Redirect to the thread and exit
    $redirect_to = '/' . $BOARD . '/' . $THREAD;
    header('Location: ' . $redirect_to, true, 302);
    dba_close($db);
    exit(0);
  }
} ?>
<!DOCTYPE html>
<html>
<head>
  <title><?php echo $NAME; ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/static/favicon.png">
  <meta name="theme-color" content="#666">
  <style type="text/css">
    <?php include('static/normalize.css'); ?>
    <?php include('static/style.css'); ?>
  </style>
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
  <header>
    <h1>/<?php echo $BOARD; ?>/</h1>
  </header>
  <main>
<?php // List threads for the board
  $thread_id = dba_fetch($BOARD . '_head_next', $db);
  while ($thread_id != $BOARD . '_tail' && empty($NEW)) {
    $thread_subject = dba_fetch($thread_id . '_subject', $db);
    $thread_message = dba_fetch($thread_id . '_message', $db);
    $thread_time = dba_fetch($thread_id . '_time', $db); ?>
    <article id="thread<?php echo $thread_id; ?>">
      <header>
        <hgroup>
          <h1><?php echo $thread_subject; ?></h1>
          <h2>
            <a href="<?php echo $thread_id; ?>"><?php echo $thread_id; ?></a>
            <time><?php echo date('Y-m-d H:i', $thread_time); ?></time>
          </h2>
        </hgroup>
      </header>
      <main>
        <p><?php echo str_replace('&#13;&#10;', '<br>', $thread_message); ?></p>
      </main>
    </article>
<?php // End of foreach thread loop
    $thread_id = dba_fetch($thread_id . '_next', $db);
  } ?>
    <section id="newthread">
      <header><h1>New Thread</h1></header>
      <main>
        <form method="post" action="/<?php echo $BOARD; ?>/new">
<?php // If form wasn't filled-out right
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['lorem'])) { ?>
          <p>You need a subject</p>
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
} elseif (!empty($BOARD) && !empty($THREAD)) { ?>
  <header>
    <h1>/<?php echo $BOARD; ?>/<?php echo $THREAD; ?></h1>
  </header>
  <main>
<?php
  $thread_id = $THREAD;
  $thread_subject = dba_fetch($thread_id . '_subject', $db);
  $thread_message = dba_fetch($thread_id . '_message', $db);
  $thread_time = dba_fetch($thread_id . '_time', $db); ?>
    <article id="thread<?php echo $thread_id; ?>">
      <header>
        <hgroup>
          <h1><?php echo $thread_subject; ?></h1>
          <h2>
            <a href="<?php echo $thread_id; ?>"><?php echo $thread_id; ?></a>
            <time><?php echo date('Y-m-d H:i', $thread_time); ?></time>
          </h2>
        </hgroup>
      </header>
      <main>
        <p><?php echo str_replace('&#13;&#10;', '<br>', $thread_message); ?></p>
<?php
  $reply_id = dba_fetch($THREAD . '_replies_head_next', $db);
  while ($reply_id != $THREAD . '_replies_tail' && empty($NEW)) {
    $reply_subject = dba_fetch($reply_id . '_subject', $db);
    $reply_message = dba_fetch($reply_id . '_message', $db);
    $reply_time = dba_fetch($reply_id . '_time', $db); ?>
        <section>
          <header>
            <hgroup>
              <h1><?php echo $reply_subject; ?></h1>
              <h2>
                <a href="#r<?php echo $reply_id; ?>"><?php echo $reply_id; ?></a>
                <time><?php echo date('Y-m-d H:i', $reply_time); ?></time>
              </h2>
            </hgroup>
          </header>
          <main>
            <p><?php echo str_replace('&#13;&#10;', '<br>', $reply_message); ?></p>
          </main>
        </section>
<?php
    $reply_id = dba_fetch($reply_id . '_next', $db);
  } ?>
      </main>
    </article>
    <section id="newreply">
      <header><h1>New Reply</h1></header>
      <main>
        <form method="post" action="/<?php echo $BOARD . '/' . $THREAD; ?>/new">
<?php // If form wasn't filled-out right
  if ($_SERVER['REQUEST_METHOD'] == 'POST'
      && empty($_POST['lorem']) && empty($_POST['ipsum'])) { ?>
          <p>You need a subject or message</p>
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
