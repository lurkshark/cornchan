<?php // Establish basic preconditions
header('X-Powered-By: Corn v0.3');
extension_loaded('dba') or exit('The dba extension is not available!');
in_array('lmdb', dba_handlers())
  or in_array('gdbm', dba_handlers())
  or exit('Neither lmdb or gdbm implementations are available for dba!');
$DBA_HANDLER = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'gdbm';
// Use the override when provided (e.g. Travis CI) otherwise use HOME dir
$DBA_PATH = ($_ENV['CORN_DBA_PATH_OVERRIDE'] ?? $_ENV['HOME']) . 'cornchan.db';
$TEST_OVERRIDE = isset($_ENV['CORN_TEST_OVERRIDE']);

// Create the db if it doesn't exist
if (!file_exists($DBA_PATH)) {
  $db = dba_open($DBA_PATH, 'c', $DBA_HANDLER)
    or exit('Can\'t initialize a new db file!');

  $boards = ['corn', 'meta', 'news'];
  dba_replace('metadata_id', '9999', $db);
  dba_replace('metadata_name', 'cornchan', $db);
  dba_replace('metadata_boards', json_encode($boards), $db);
  dba_replace('metadata_secret', bin2hex(random_bytes(32)), $db);
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
preg_match('/^(?:\/(' . join('|', $BOARDS) . '))?(?:\/(\d+))?(?:\/(new|delete))?\/?$/',
    $PATH, $matches);
$BOARD = $matches[1];
$POST_ID = $matches[2];
$ACTION = $matches[3];

$CANONICAL = '/'; // Build expected path
empty($BOARD)   or $CANONICAL .= $BOARD;
empty($POST_ID)  or $CANONICAL .= '/' . $POST_ID;
empty($ACTION)  or $CANONICAL .= '/' . $ACTION;
if (!empty($BOARD) && empty($POST_ID) && empty($ACTION)) {
  $CANONICAL .= '/';
}

// Utility function for validating posts
function post_exists($board, $post_id, $handler) {
  // If this post_id just doesn't exist
  if (!empty($post_id) && !dba_exists($post_id . '_subject', $handler)) {
    return false;
  // Or if this post_id exists but it's for a different board
  } elseif (!empty($board) && !empty($post_id)
      && dba_fetch($post_id . '_board', $handler) != $board) {
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
    // Or well-formatted but for a post that doesn't exist
    || !post_exists($BOARD, $POST_ID, $db)) {
  http_response_code(404);
}

// If trying to view a specific reply then redirect to the thread
if (!empty($POST_ID) && $ACTION != 'delete' && post_exists($BOARD, $POST_ID, $db)
    && dba_fetch($POST_ID . '_thread', $db) != $POST_ID) {
  $thread_id = dba_fetch($POST_ID . '_thread', $db);
  $redirect_to = '/' . $BOARD . '/' . $thread_id . '#reply' . $POST_ID;
  header('Location: ' . $redirect_to, true, 302);
  dba_close($db);
  exit(0);
}

// Returns a HMAC token
function generate_token($tag, $handler) {
  $time = time(); // Signing time for the token
  $secret = dba_fetch('metadata_secret', $handler);
  $hmac = hash_hmac('sha256', implode('.', [$tag, $time]), $secret);
  return implode('.', [$time, $hmac]);
}

// Verifies a HMAC token from generate_token
function verify_token($tag, $token, $handler) {
  [$time, $hmac] = explode('.', $token);
  if (time() - intval($time) > 3600) {
    return false;
  }
  // Verify the hmac because timestamp is good
  $secret = dba_fetch('metadata_secret', $handler);
  return hash_hmac('sha256', implode('.', [$tag, $time]),
      $secret) == $hmac;
}

// Gets and increment ID
function fresh_id($handle) {
  $fresh_id = strval(intval(dba_fetch('metadata_id', $handle)) + 1);
  dba_replace('metadata_id', $fresh_id, $handle);
  return $fresh_id;
}

// Upserts the post for the given ID
function update_post($id, $board, $thread, $subject, $message, $handle) {
  $subject = filter_var($subject, FILTER_SANITIZE_SPECIAL_CHARS);
  $message = filter_var($message, FILTER_SANITIZE_SPECIAL_CHARS);
  dba_replace($id . '_subject', substr($subject, 0, 64), $handle);
  dba_replace($id . '_message', substr($message, 0, 4096), $handle);
  dba_replace($id . '_thread', $thread, $handle);
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

// Get the CAPTCHA status
$captcha_answer = strtoupper($_POST['captcha-answer']);
$captcha_cookie = explode('.', $_COOKIE['captcha']);
$captcha_cookie_token = implode('.', array_slice($captcha_cookie, 1));
$captcha_skip = !empty($_COOKIE['captcha'])
    && (verify_token($captcha_cookie[0], $captcha_cookie_token, $db)
      || ($TEST_OVERRIDE && $captcha_cookie[0] == 'GOODCAPTCHA'));

// If posting a new thread or reply
if (post_exists($BOARD, $POST_ID, $db) && !empty($ACTION)
    && $_SERVER['REQUEST_METHOD'] == 'POST'
    && verify_token('csrf', $_POST['csrf-token'], $db)
    && (verify_token($captcha_answer, $_POST['captcha-token'], $db)
      || ($TEST_OVERRIDE && $captcha_answer == 'GOODCAPTCHA')
      || $captcha_skip)) {
  // If opt-in to CAPTCHA cookie
  if ($_POST['opt-in-cookie'] && !$captcha_skip) {
    setcookie('captcha', implode('.', [$captcha_answer, $_POST['captcha-token']]), 0, '/');
  }
  // If new thread: POST /board/new
  if (!empty($BOARD) && empty($POST_ID)
      && !empty($_POST['lorem'])) {
    dba_close($db); // Close the db for reads and reopen for writes
    $db = dba_open($DBA_PATH, 'w', $DBA_HANDLER);
    // Get the ID for the new thread
    $new_thread_id = fresh_id($db);
    // Insert the new thread at the head of the board
    insert_at_head($BOARD, $new_thread_id, $db);
    // Create the post for the new thread
    update_post($new_thread_id, $BOARD, $new_thread_id, $_POST['lorem'], $_POST['ipsum'], $db);
    // Initialize thread replies pointers
    dba_replace($new_thread_id . '_replies_head_next', $new_thread_id . '_replies_tail', $db);
    dba_replace($new_thread_id . '_replies_tail_prev', $new_thread_id . '_replies_head', $db);
    // Redirect to the board and exit
    $redirect_to = '/' . $BOARD . '/';
    header('Location: ' . $redirect_to, true, 302);
    dba_close($db);
    exit(0);
  // If new reply: POST /board/1000/new
  } elseif (!empty($BOARD) && !empty($POST_ID)
      && (!empty($_POST['lorem']) || !empty($_POST['ipsum']))) {
    dba_close($db); // Close the db for reads and reopen for writes
    $db = dba_open($DBA_PATH, 'w', $DBA_HANDLER);
    // Remove the replied-to thread from its place
    $old_thread_next = dba_fetch($POST_ID . '_next', $db);
    $old_thread_prev = dba_fetch($POST_ID . '_prev', $db);
    dba_replace($old_thread_prev . '_next', $old_thread_next, $db);
    dba_replace($old_thread_next . '_prev', $old_thread_prev, $db);
    // Insert the replied-to thread at the head of the board
    insert_at_head($BOARD, $POST_ID, $db);
    // Get the ID for the new reply
    $new_reply_id = fresh_id($db);
    // Create the post for the new reply
    update_post($new_reply_id, $BOARD, $POST_ID, $_POST['lorem'], $_POST['ipsum'], $db);
    // Insert the reply at the tail of the thread replies
    insert_at_tail($POST_ID . '_replies', $new_reply_id, $db);
    // Redirect to the thread and exit
    $redirect_to = '/' . $BOARD . '/' . $POST_ID;
    header('Location: ' . $redirect_to, true, 302);
    dba_close($db);
    exit(0);
  }
} ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title><?php echo $NAME; ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/static/favicon.png">
  <meta name="theme-color" content="#666">
  <style>
<?php include('static/normalize.css'); ?>
<?php include('static/style.css'); ?>
  </style>
</head>
<body>
  <nav>
    <a href="/" style="font-weight: bold;"><?php echo $NAME; ?></a>
<?php
foreach ($BOARDS as $board) {
  $board_path = '/' . $board . '/'; ?>
    <a href="<?php echo $board_path; ?>"><?php echo $board_path; ?></a>
<?php // End foreach board
} ?>
  </nav>
<?php // Check for error status code
if (http_response_code() != 200) { ?>
  <h1>Error <?php echo http_response_code(); ?></h1>
<?php // If at root path
} elseif (empty($BOARD) && empty($POST_ID)) {
  // Something novel for the root path
} elseif (!empty($BOARD) && empty($POST_ID)) { ?>
  <header>
    <h1>/<?php echo $BOARD; ?>/</h1>
  </header>
  <main>
<?php // List threads for the board
  $thread_id = dba_fetch($BOARD . '_head_next', $db);
  while ($thread_id != $BOARD . '_tail' && empty($ACTION)) {
    $thread_subject = dba_fetch($thread_id . '_subject', $db);
    $thread_message = dba_fetch($thread_id . '_message', $db);
    $thread_time = dba_fetch($thread_id . '_time', $db); ?>
    <article id="thread<?php echo $thread_id; ?>">
      <header>
        <hgroup>
          <h2><?php echo $thread_subject; ?></h2>
          <h3>
            <a href="<?php echo $thread_id; ?>"><?php echo $thread_id; ?></a>
            <time><?php echo date('Y-m-d H:i', $thread_time); ?></time>
          </h3>
        </hgroup>
      </header>
      <div>
        <p><?php echo str_replace('&#13;&#10;', '<br>', $thread_message); ?></p>
      </div>
    </article>
<?php // End of foreach thread loop
    $thread_id = dba_fetch($thread_id . '_next', $db);
  }
} elseif (!empty($BOARD) && !empty($POST_ID)) { ?>
  <header>
    <h1>/<?php echo $BOARD; ?>/<?php echo $POST_ID; ?></h1>
  </header>
  <main>
<?php
  $thread_id = $POST_ID;
  $thread_subject = dba_fetch($thread_id . '_subject', $db);
  $thread_message = dba_fetch($thread_id . '_message', $db);
  $thread_time = dba_fetch($thread_id . '_time', $db); ?>
    <article id="thread<?php echo $thread_id; ?>">
      <header>
        <hgroup>
          <h2><?php echo $thread_subject; ?></h2>
          <h3>
            <a href="<?php echo $thread_id; ?>"><?php echo $thread_id; ?></a>
            <time><?php echo date('Y-m-d H:i', $thread_time); ?></time>
          </h3>
        </hgroup>
      </header>
      <div>
        <p><?php echo str_replace('&#13;&#10;', '<br>', $thread_message); ?></p>
<?php
  $reply_id = dba_fetch($POST_ID . '_replies_head_next', $db);
  while ($reply_id != $POST_ID . '_replies_tail' && empty($ACTION)) {
    $reply_subject = dba_fetch($reply_id . '_subject', $db);
    $reply_message = dba_fetch($reply_id . '_message', $db);
    $reply_time = dba_fetch($reply_id . '_time', $db); ?>
        <section id="reply<?php echo $reply_id; ?>">
          <header>
            <hgroup>
              <h2><?php echo $reply_subject; ?></h2>
              <h3>
                <a href="#r<?php echo $reply_id; ?>"><?php echo $reply_id; ?></a>
                <time><?php echo date('Y-m-d H:i', $reply_time); ?></time>
              </h3>
            </hgroup>
          </header>
          <div>
            <p><?php echo str_replace('&#13;&#10;', '<br>', $reply_message); ?></p>
          </div>
        </section>
<?php
    $reply_id = dba_fetch($reply_id . '_next', $db);
  } ?>
      </div>
    </article>
<?php // End main body
} // Start new thread/reply form
if (!empty($BOARD)) {
  if (!empty($BOARD) && empty($POST_ID)) { ?>
    <section id="newthread">
      <header><h2>New Thread</h2></header>
      <div>
        <form method="post" action="/<?php echo $BOARD; ?>/new">
<?php // If form wasn't filled-out right
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['lorem'])) { ?>
          <p>You need a subject</p><p></p>
<?php
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST'
        && (!verify_token($_POST['captcha-answer'], $_POST['captcha-token'], $db)
          || ($TEST_OVERRIDE && $_POST['captcha-answer'] != 'GOODCAPTCHA'))) { ?>
          <p>You got the CAPTCHA wrong</p><p></p>
<?php
    }
  } elseif (!empty($BOARD) && !empty($POST_ID)) { ?>
    <section id="newreply">
      <header><h2>New Reply</h2></header>
      <div>
        <form method="post" action="/<?php echo $BOARD . '/' . $POST_ID; ?>/new">
<?php // If form wasn't filled-out right
    if ($_SERVER['REQUEST_METHOD'] == 'POST'
        && empty($_POST['lorem']) && empty($_POST['ipsum'])) { ?>
          <p>You need a subject or message</p><p></p>
<?php
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST'
        && (!verify_token($_POST['captcha-answer'], $_POST['captcha-token'], $db)
          || ($TEST_OVERRIDE && $_POST['captcha-answer'] != 'GOODCAPTCHA'))) { ?>
          <p>You got the CAPTCHA wrong</p><p></p>
<?php
    }
  } ?>
          <p>
            <label for="lorem">Subject</label>
            <input type="text" name="lorem" id="lorem" autocomplete="off">
          </p>
          <p>
            <!-- <label for="dolor">Upload</label> -->
            <!-- <input type="file" name="dolor" id="dolor"> -->
          </p>
          <p class="full">
            <label for="ipsum">Message</label>
            <textarea name="ipsum" id="ipsum"></textarea>
          </p>
<?php // Generate CAPTCHA if no cookie
  if (!$captcha_skip) {
    $captcha = imagecreate(80, 20);
    imagecolorallocate($captcha, 255, 255, 255);

    $answer = array();
    $alphabet = range('A', 'Z');
    while (sizeof($answer) < 6) {
      $answer[] = $alphabet[random_int(0, sizeof($alphabet) - 1)];
    }

    $red = imagecolorallocate($captcha, 192, 64, 64);
    imagestring($captcha, 5, 5, 2, implode($answer), $red);

    ob_start();
    imagepng($captcha, NULL, 9);
    $bin = ob_get_clean();
    imagedestroy($captcha);
    $image_data = base64_encode($bin);
    $captcha_token = generate_token(implode($answer), $db); ?>
          <p class="full">
            <label for="captcha-answer">CAPTCHA</label>
            <input type="checkbox" name="opt-in-cookie" id="opt-in-cookie" checked>
            <label for="opt-in-cookie">Use cookie to remember</label>
            <input type="text" name="captcha-answer" id="captcha-answer" autocomplete="off"
              style="background: url(data:image/png;base64,<?php echo $image_data; ?>) right no-repeat;">
            <input type="hidden" name="captcha-token" value="<?php echo $captcha_token; ?>">
          </p>
<?php // End CAPTCHA if no cookie
  } ?>
          <p>
            <button>Submit</button>
          </p>
<?php // Generate CSRF token
  $csrf_token = generate_token('csrf', $db); ?>
          <input type="hidden" name="csrf-token" value="<?php echo $csrf_token; ?>">
        </form>
      </div>
    </section>
  </main><!-- This main is only on boards and threads -->
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
