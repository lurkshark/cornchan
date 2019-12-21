<?php // Gentlemen, behold...
$config = array(); // CORNCHAN
header('X-Powered-By: Corn v0.6');
extension_loaded('dba') or exit('The dba extension is not available!');
in_array('lmdb', dba_handlers())
  or in_array('gdbm', dba_handlers())
  or exit('Neither lmdb or gdbm implementations are available for dba!');
$config['dba_handler'] = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'gdbm';
// Use the override when provided (e.g. Travis CI) otherwise use the HOME dir
$config['dba_path'] = ($_ENV['CORN_DBA_PATH_OVERRIDE'] ?? $_ENV['HOME']) . 'cornchan.db';
$config['test_override'] = isset($_ENV['CORN_TEST_OVERRIDE']);
$config['anonymous'] = 'Cornonymous';
$config['language'] = 'en';

// Create the db if it doesn't exist
if (!file_exists($config['dba_path'])) {
  $db_c = dba_open($config['dba_path'], 'c', $config['dba_handler'])
    or exit('Can\'t initialize a new db file!');

  $board_ids = ['corn', 'meta', 'news'];
  dba_replace('_metadata.id', '999', $db_c);
  dba_replace('_config.name', 'cornchan', $db_c);
  dba_replace('_config.board_ids', json_encode($board_ids), $db_c);
  dba_replace('_config.secret', bin2hex(random_bytes(32)), $db_c);
  foreach ($board_ids as $board_id) {
    dba_replace($board_id . '.thread_count', '0', $db_c);
    dba_replace($board_id . '#thread_head.next_thread_id', 'thread_tail', $db_c);
    dba_replace($board_id . '#thread_tail.prev_thread_id', 'thread_head', $db_c);
  }
  // Close after db initialization
  dba_close($db_c);
}

// Open db for read and get config
$db = dba_open($config['dba_path'], 'r', $config['dba_handler'])
  or exit('Can\'t open the db file!');
$config['board_ids'] = json_decode(dba_fetch('_config.board_ids', $db));
$config['secret'] = dba_fetch('_config.secret', $db);
$config['name'] = dba_fetch('_config.name', $db);

// Returns a HMAC token
function generate_token($tag) { global $config;
  $time = time();
  $message = implode('.', [$tag, $time]);
  $hmac = hash_hmac('sha256', $message, $config['secret']);
  return implode('.', [$time, $hmac]);
}

// Verifies a HMAC token from generate_token
function verify_token($tag, $token) { global $config;
  [$time, $hmac] = explode('.', $token);
  if (time() - intval($time) > 3600) {
    return false;
  }
  // Verify the hmac because timestamp is good
  $expected_message = implode('.', [$tag, $time]);
  return hash_hmac('sha256', $expected_message, $config['secret']) == $hmac;
}

$config['csrf'] = generate_token('_csrf');

function render_text($raw_message, $styling = true) { global $config;
  if (!$styling) return filter_var($raw_message, FILTER_SANITIZE_SPECIAL_CHARS);

  $rules = array();
  $rules['/\n(\>[^\>\n]+)/'] = "\n<q>\\1</q>";
  $rules['/(\*\*|__)(.*?)\1/'] = '<strong>\2</strong>';
  $rules['/(\*|_)(.*?)\1/'] = '<em>\2</em>';
  $rules['/\>\>(\d+)/'] = '<a href="#\1">>>\1</a>'; // Blind trust that the reply exists
  $rules['/(https?:\/\/[\w\-\.\~\:\/\?\#\[\]\@\!\$\&\'\(\)\*\+\,\;\=\%]+)/'] = '<a href="\1">\1</a>';
  $rules['/\n([^\n]+)/'] = '<p>\1</p>';
  $rules['/\n/'] = '<br>';

  $out = "\n" . preg_replace('/\r/', '', $raw_message);
  foreach ($rules as $pattern => $replacement) {
    $out = preg_replace($pattern, $replacement, $out);
  }

  $out = filter_var($out, FILTER_SANITIZE_SPECIAL_CHARS);
  $out = preg_replace('/&#60;(\/)?(p|q|br|strong|em|a)&#62;/', '<\1\2>', $out);
  return preg_replace('/&#60;a href=&#34;(.*?)&#34;&#62;/', '<a href="\1">', $out);
}

function render_captcha_form_fragment_html() { global $config;
  ob_start(); ?>
    <?php // Generate the CAPTCHA image
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
      $captcha_token = generate_token(implode($answer)); ?>
    <label for="captcha-answer">Verify</label>
    <input type="text" name="captcha_answer" id="captcha-answer" autocomplete="off"
      style="background: url(data:image/png;base64,<?php echo $image_data; ?>) right no-repeat;">
    <input type="hidden" name="captcha_token" value="<?php echo $captcha_token; ?>">
  <?php return ob_get_clean();
}

function render_new_post_form_fragment_html($board_or_thread, $prefill = array()) { global $config;
  $form_action = '/' . $board_or_thread['board_id'];
  if (!empty($board_or_thread['thread_id'])) $form_action .= '/t/' . $board_or_thread['thread_id'];
  $form_action .= '/publish';
  ob_start(); ?>
    <section id="new-post">
      <form method="post" action="<?php echo $form_action; ?>" class="new-post">
        <?php if (empty($board_or_thread['thread_id'])) { ?>
          <label for="subject">Subject</label>
          <input type="text" name="subject" id="subject" autocomplete="off" class="new-post-subject"
              value="<?php echo $prefill['subject']; ?>">
        <?php } ?>
        <label for="message">Message</label>
        <textarea name="message" id="message" class="new-post-message"><?php echo $prefill['message']; ?></textarea>
        <?php echo render_captcha_form_fragment_html(); ?>
        <!-- <label for="password">Password</label>
        <input type="text" name="password" id="password"
            autocomplete="off" class="new-post-password"> -->
        <button class="new-post-submit">Submit</button>
        <input type="hidden" name="csrf_token" value="<?php echo $config['csrf']; ?>">
        <input type="hidden" name="board_id" value="<?php echo $board_or_thread['board_id']; ?>">
        <input type="hidden" name="thread_id" value="<?php echo $board_or_thread['thread_id']; ?>">
      </form>
    </section>
  <?php return ob_get_clean();
}

function render_post_tag_fragment_html($post_tag) {
  $hashed = hash('sha256', $post_tag);
  ob_start(); ?>
    <span class="post-tag-segment" style="background-color: #<?php echo substr($hashed, 0, 6); ?>;">
    </span><span class="post-tag-segment" style="background-color: #<?php echo substr($hashed, 6, 6); ?>;">
    </span><span class="post-tag-segment" style="background-color: #<?php echo substr($hashed, 12, 6); ?>;">
    </span><span class="post-tag-segment" style="background-color: #<?php echo substr($hashed, 18, 6); ?>;">
    </span>
  <?php return ob_get_clean(); // margin:0;
}

function render_reply_fragment_html($reply) {
  ob_start(); ?>
    <div class="reply-wrapper">
      <div id="<?php echo $reply['reply_id']; ?>" class="reply">
        <div class="post-details">
          <a class="post-id" href="<?php echo $reply['href']; ?>"><?php echo $reply['reply_id']; ?></a>
          <span class="post-tag"><?php echo render_post_tag_fragment_html($reply['tag']); ?></span>
          <span class="post-name"><?php echo render_text($reply['name'], false); ?></span>
          <time class="post-time"><?php echo date('Y-m-d H:i', $reply['time']); ?></time>
        </div>
        <div class="post-message">
          <?php echo render_text($reply['message']); ?>
        </div>
      </div>
    </div>
  <?php return ob_get_clean();
}

function render_thread_fragment_html($thread) {
  ob_start(); ?>
    <article id="<?php echo $thread['thread_id']; ?>" class="thread">
      <header class="post-details">
        <h2 class="post-subject"><?php echo render_text($thread['subject'], false); ?></h2>
        <a class="post-id" href="<?php echo $thread['href']; ?>"><?php echo $thread['thread_id']; ?></a>
        <span class="post-tag"><?php echo render_post_tag_fragment_html($thread['tag']); ?></span>
        <span class="post-name"><?php echo render_text($thread['name'], false); ?></span>
        <time class="post-time"><?php echo date('Y-m-d H:i', $thread['time']); ?></time>
      </header>
      <div class="post-message">
        <?php echo render_text($thread['message']); ?>
      </div>
      <?php foreach ($thread['replies'] as $reply) { ?>
        <?php echo render_reply_fragment_html($reply); ?>
      <?php } ?>
    </article>
  <?php return ob_get_clean();
}

function render_publish_body_html($board_or_thread, $prefill, $toast) {
  $headline = !empty($board_or_thread['thread_id']) ?
      $board_or_thread['board_id'] . ' / ' . $board_or_thread['thread_id'] :
      $board_or_thread['board_id'];
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $headline; ?></h1>
    </header>
    <hr>
    <div class="toast"><?php echo $toast; ?></div>
    <?php echo render_new_post_form_fragment_html($board_or_thread, $prefill); ?>
  <?php return ob_get_clean();
}

function render_thread_body_html($thread) {
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $thread['thread_id']; ?> / <?php echo $thread['board_id']; ?></h1>
    </header>
    <hr>
    <?php echo render_thread_fragment_html($thread); ?>
    <hr>
    <?php echo render_new_post_form_fragment_html($thread); ?>
  <?php return ob_get_clean();
}

function render_board_body_html($board) {
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $board['board_id']; ?></h1>
    </header>
    <?php foreach ($board['threads'] as $thread) { ?>
      <hr>
      <?php echo render_thread_fragment_html($thread); ?>
    <?php } ?>
    <hr>
    <?php echo render_new_post_form_fragment_html($board); ?>
  <?php return ob_get_clean();
}

function render_html($title, $body) { global $config;
  ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?php echo $config['language']; ?>">
    <head>
      <title><?php echo $title; ?></title>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="icon" href="/static/favicon.png">
      <meta name="theme-color" content="#666">
      <style>
        <?php include('static/normalize.css'); ?>
        <?php include('static/style.css'); ?>
        <?php include('static/wild.css'); ?>
      </style>
    </head>
    <body>
      <nav class="top-bar">
        <span class="logo" style="font-weight: bold;"><?php echo $config['name']; ?></span>
        <?php foreach ($config['board_ids'] as $board_id) {
          $board_path = '/' . $board_id . '/'; ?>
          / <a href="<?php echo $board_path; ?>"><?php echo $board_id; ?></a>
        <?php } ?>
      </nav>
      <?php echo $body; ?>
      <hr>
      <?php // Calculate the milliseconds it took to render the page and last update
        $exec_time = intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
        $last_updated = date('Y-m-d H:i', filemtime(__FILE__)); ?>
      <footer class="footer">
        <small><?php echo $last_updated; ?> / <?php echo $exec_time; ?>ms</small>
      </footer>
    </body>
    </html>
  <?php return ob_get_clean();
}

function with_write_db($func) { global $config, $db;
  dba_close($db); // Close the db for reads and reopen for writes
  $db = $db_w = dba_open($config['dba_path'], 'w', $config['dba_handler']);
  $out = $func($db_w); // Execute the callback with the writable db handle
  dba_close($db_w); // Close the db for writes and reopen for reads
  $db = dba_open($config['dba_path'], 'r', $config['dba_handler']);
  return $out;
}

// Gets and increment ID
function fresh_id($db_w) {
  $fresh_id = strval(intval(dba_fetch('_metadata.id', $db_w)) + 1);
  dba_replace('_metadata.id', $fresh_id, $db_w);
  return $fresh_id;
}

// Inserts a post at the head of the list
function bump_thread($db_w, $thread) {
  $thread_head_key = $thread['board_id'] . '#thread_head';
  $old_head_next_id = dba_fetch($thread_head_key . '.next_thread_id', $db_w);
  if ($old_head_next_id === $thread['thread_id']) return;

  $old_next_id = $thread['next_thread_id'];
  $old_prev_id = $thread['prev_thread_id'];
  if (!empty($old_next_id) && !empty($old_prev_id)) {
    $old_next_key = $thread['board_id'] . '#' . $old_next_id;
    $old_prev_key = $thread['board_id'] . '#' . $old_prev_id;
    // Stitch the next of the prev to the next and the prev of the next to the prev
    dba_replace($old_prev_key . '.next_thread_id', $old_next_id, $db_w);
    dba_replace($old_next_key . '.prev_thread_id', $old_prev_id, $db_w);
  }

  $old_head_next_key = $thread['board_id'] . '#' . $old_head_next_id;
  dba_replace($thread_head_key . '.next_thread_id', $thread['thread_id'], $db_w);
  dba_replace($old_head_next_key . '.prev_thread_id', $thread['thread_id'], $db_w);
  dba_replace($thread['key'] . '.next_thread_id', $old_head_next_id, $db_w);
  dba_replace($thread['key'] . '.prev_thread_id', 'thread_head', $db_w);
}

function put_reply_data($db_w, $reply) { global $config;
  $board_id = $reply['board_id'];
  $thread_id = $reply['thread_id'];
  $thread_key = $board_id . '#' . $thread_id;
  if (!fetch_thread_data($board_id, $thread_id)) return false;
  if (empty(trim($reply['message']))) return false;

  $reply_id = fresh_id($db_w);
  $reply_key = $board_id . '#' . $thread_id . '#' . $reply_id;

  dba_replace($reply_key . '.board_id', $board_id, $db_w);
  dba_replace($reply_key . '.thread_id', $thread_id, $db_w);
  dba_replace($reply_key . '.reply_id', $reply_id, $db_w);
  dba_replace($reply_key . '.message', $reply['message'], $db_w);
  // dba_replace($reply_key . '.name', $reply['name'], $db_w);
  dba_replace($reply_key . '.ip', $reply['ip'], $db_w);
  dba_replace($reply_key . '.time', time(), $db_w);

  $thread_reply_count = intval(dba_fetch($thread_key . '.reply_count', $db_w));
  dba_replace($thread_key . '.reply_count', $thread_reply_count + 1, $db_w);
  bump_thread($db_w, fetch_thread_data($board_id, $thread_id));

  // Append the reply to the thread replies
  $reply_tail_key = $thread_key . '#reply_tail';
  $old_tail_prev_id = dba_fetch($reply_tail_key . '.prev_reply_id', $db_w);
  $old_tail_prev_key = $thread_key . '#' . $old_tail_prev_id;
  dba_replace($reply_tail_key . '.prev_reply_id', $reply_id, $db_w);
  dba_replace($reply_key . '.prev_reply_id', $old_tail_prev_id, $db_w);
  dba_replace($reply_key . '.next_reply_id', 'reply_tail', $db_w);
  dba_replace($old_tail_prev_key . '.next_reply_id', $reply_id, $db_w);

  // Return the persisted reply data
  return fetch_reply_data($board_id, $thread_id, $reply_id);
}

function put_thread_data($db_w, $thread) { global $config;
  $board_id = $thread['board_id'];
  if (!in_array($board_id, $config['board_ids'])) return false;

  $thread_id = fresh_id($db_w);
  $thread_key = $board_id . '#' . $thread_id;
  dba_replace($thread_key . '.board_id', $board_id, $db_w);
  dba_replace($thread_key . '.thread_id', $thread_id, $db_w);
  dba_replace($thread_key . '.subject', $thread['subject'], $db_w);
  dba_replace($thread_key . '.message', $thread['message'], $db_w);
  // dba_replace($thread_key . '.name', $thread['name'], $db_w);
  dba_replace($thread_key . '.ip', $thread['ip'], $db_w);
  dba_replace($thread_key . '.time', time(), $db_w);

  dba_replace($thread_key . '.reply_count', '0', $db_w);
  dba_replace($thread_key . '#reply_head.next_reply_id', 'reply_tail', $db_w);
  dba_replace($thread_key . '#reply_tail.prev_reply_id', 'reply_head', $db_w);

  $board_thread_count = intval(dba_fetch($board_id . '.thread_count', $db_w));
  dba_replace($board_id . '.thread_count', $board_thread_count + 1, $db_w);
  $persisted =  fetch_thread_data($board_id, $thread_id);
  // Bump and return persisted new thread
  bump_thread($db_w, $persisted);
  return $persisted;
}

function fetch_reply_data($board_id, $thread_id, $reply_id) { global $config, $db;
  $reply_key = $board_id . '#' . $thread_id . '#' . $reply_id; 
  $reply = ['board_id' => $board_id, 'thread_id' => $thread_id, 'reply_id' => $reply_id];
  $reply['time'] = intval(dba_fetch($reply_key . '.time', $db));
  if (empty($reply['time'])) return false;

  $reply['name'] = dba_fetch($reply_key . '.name', $db);
  if (empty($reply['name'])) $reply['name'] = $config['anonymous'];

  $reply['ip'] = dba_fetch($reply_key . '.ip', $db);
  $tag_source_data = $reply['ip'] . $reply['thread_id'];
  $reply['tag'] = hash_hmac('sha256', $tag_source_data, $config['secret']);

  $reply['subject'] = dba_fetch($reply_key . '.subject', $db);
  $reply['message'] = dba_fetch($reply_key . '.message', $db);
  $reply['next_reply_id'] = dba_fetch($reply_key . '.next_reply_id', $db);
  $reply['prev_reply_id'] = dba_fetch($reply_key . '.prev_reply_id', $db);
  $reply['href'] = '/' . $board_id . '/t/' . $thread_id . '#' . $reply_id;
  $reply['key'] = $reply_key;
  return $reply;
}

function fetch_thread_data($board_id, $thread_id) { global $config, $db;
  $thread_key = $board_id . '#' . $thread_id; 
  $thread = ['board_id' => $board_id, 'thread_id' => $thread_id];
  $thread['time'] = intval(dba_fetch($thread_key . '.time', $db));
  if (empty($thread['time'])) return false;

  $thread['name'] = dba_fetch($thread_key . '.name', $db);
  if (empty($thread['name'])) $thread['name'] = $config['anonymous'];

  $thread['ip'] = dba_fetch($thread_key . '.ip', $db);
  $tag_source_data = $thread['ip'] . $thread['thread_id'];
  $thread['tag'] = hash_hmac('sha256', $tag_source_data, $config['secret']);

  $thread['subject'] = dba_fetch($thread_key . '.subject', $db);
  $thread['message'] = dba_fetch($thread_key . '.message', $db);
  $thread['next_thread_id'] = dba_fetch($thread_key . '.next_thread_id', $db);
  $thread['prev_thread_id'] = dba_fetch($thread_key . '.prev_thread_id', $db);
  $thread['reply_count'] = intval(dba_fetch($thread_key . '.reply_count', $db));
  $thread['href'] = '/' . $board_id . '/t/' . $thread_id;
  $thread['key'] = $thread_key;

  $thread['replies'] = array();
  $reply_head_key = $thread_key . '#reply_head';
  $current_reply_id = dba_fetch($reply_head_key . '.next_reply_id', $db);
  while ($current_reply_id !== 'reply_tail') {
    $current_reply = fetch_reply_data($board_id, $thread_id, $current_reply_id);
    $current_reply_id = $current_reply['next_reply_id'];
    $thread['replies'][] = $current_reply;
  }

  return $thread;
}

function fetch_board_data($board_id) { global $config, $db;
  if (!in_array($board_id, $config['board_ids'])) return false;
  $board = ['board_id' => $board_id]; // Just for completeness
  $board['thread_count'] = intval(dba_fetch($board_id . '.thread_count', $db));
  $board['href'] = '/' . $board_id . '/';

  $board['threads'] = array();
  $current_thread_id = dba_fetch($board_id . '#thread_head.next_thread_id', $db);
  while ($current_thread_id !== 'thread_tail') {
    $current_thread = fetch_thread_data($board_id, $current_thread_id);
    $current_thread_id = $current_thread['next_thread_id'];
    $board['threads'][] = $current_thread;
  }

  return $board;
}

function verify_csrf($csrf_token) {
  return verify_token('_csrf', $csrf_token);
}

function verify_captcha($captcha_answer, $captcha_token) { global $config;
  if ($config['test_override'] && $captcha_answer === 'GOODCAPTCHA') return true;
  return verify_token($captcha_answer, $captcha_token);
}

function post_thread_publish($params, $cookies, $data) { global $config;
  $reply_data = array_filter(array_merge($params, $data), function($key) {
    return in_array($key, ['board_id', 'thread_id', 'subject', 'message', 'ip']);
  }, ARRAY_FILTER_USE_KEY);

  $thread = fetch_thread_data($params['board_id'], $params['thread_id']);
  $title = $thread['thread_id'] . ' / ' . $thread['board_id'] . ' / ' . $config['name'];

  if (!verify_csrf($data['csrf_token'])
      || !verify_captcha(strtoupper($data['captcha_answer']), $data['captcha_token'])) {
    echo render_html($title, render_publish_body_html($thread, $reply_data, 'Something went wrong; try again'));
    return;
  }

  $reply = with_write_db(function($db_w) use ($reply_data) {
    return put_reply_data($db_w, $reply_data);
  });

  if (!$reply) {
    echo render_html($title, render_publish_body_html($thread, $reply_data, 'Something went wrong; try again'));
    return;
  }

  header('Location: ' . $reply['href']);
}

// This can probably be merged with the thread_publish above
function post_board_publish($params, $cookies, $data) { global $config;
  $thread_data = array_filter(array_merge($params, $data), function($key) {
    return in_array($key, ['board_id', 'subject', 'message', 'ip']);
  }, ARRAY_FILTER_USE_KEY);

  $board = fetch_board_data($params['board_id']);
  $title = $board['board_id'] . ' / ' . $config['name'];

  if (!verify_csrf($data['csrf_token'])
      || !verify_captcha(strtoupper($data['captcha_answer']), $data['captcha_token'])) {
    echo render_html($title, render_publish_body_html($board, $thread_data, 'Something went wrong; try again'));
    return;
  }

  $thread = with_write_db(function($db_w) use ($thread_data) {
    return put_thread_data($db_w, $thread_data);
  });

  if (!$thread) {
    echo render_html($title, render_publish_body_html($board, $thread_data, 'Something went wrong; try again'));
    return;
  }

  header('Location: ' . $thread['href']);
}

function get_thread($params, $cookies, $data) { global $config;
  $thread = fetch_thread_data($params['board_id'], $params['thread_id']);
  if (!$thread) return error_404($params, $cookies, $data);
  $title = $thread['thread_id'] . ' / ' . $thread['board_id'] . ' / ' . $config['name'];
  echo render_html($title, render_thread_body_html($thread));
}

function get_board($params, $cookies, $data) { global $config;
  $board = fetch_board_data($params['board_id']);
  // This likely won't happen because the regex already checks
  if (!$board) return error_404($params, $cookies, $data);
  $title = $board['board_id'] . ' / ' . $config['name'];
  echo render_html($title, render_board_body_html($board));
}

function get_root($params, $cookies, $data) { global $config;
  echo render_html($config['name'], '');
}

function error_404($params, $cookies, $data) {
  // echo render_html('404 / ' . $config['name'], '');
  echo '<h1>Error 404</h1>';
  echo '<pre>'; var_dump(['error_404', $params, $cookies, $data]); echo '</pre>';
}

function debug($params, $cookies, $data) { global $config, $db;
  if (!$config['test_override']) return error_404($params, $cookies, $data);

  $key = dba_firstkey($db);
  while ($key !== false) {
    $value = dba_fetch($key, $db);
    echo $key . ' => ' . $value . '<br>';
    $key = dba_nextkey($db);
  }
}

function entrypoint($method, $path, $cookies, $data) { global $config;
  $routes = array();
  $routes['GET#/'] = 'get_root';
  $routes['GET#/_debug'] = 'debug';
  $routes['GET#/%board_id%/'] = 'get_board';
  $routes['POST#/%board_id%/publish'] = 'post_board_publish';
  $routes['GET#/%board_id%/t/%thread_id%'] = 'get_thread';
  $routes['POST#/%board_id%/t/%thread_id%/publish'] = 'post_thread_publish';

  $thread_regex = '(?P<thread_id>\d+)';
  $page_number_regex = '(?P<page_number>\d+)';
  $board_regex = '(?P<board_id>' . implode('|', $config['board_ids']) . ')';

  foreach ($routes as $route_def => $route_handler) {
    list($route_method, $route_regex) = explode('#', $route_def);
    if ($method !== $route_method) continue;

    // Replace param placeholders with regexes
    $route_regex = preg_quote($route_regex, '/');
    $route_regex = str_replace('%board_id%', $board_regex, $route_regex);
    $route_regex = str_replace('%page_number%', $page_number_regex, $route_regex);
    $route_regex = str_replace('%thread_id%', $thread_regex, $route_regex);
    $route_regex = '/^' . $route_regex . '$/u';
    
    $matches = null;
    if (preg_match($route_regex, $path, $matches)) {
      $params = array_filter($matches, function($key) {
        return in_array($key, ['board_id', 'page_number', 'thread_id']);
      }, ARRAY_FILTER_USE_KEY);
      $data['ip'] = $data['REMOTE_ADDR'];
      // Call the function for this route with all the data
      call_user_func($route_handler, $params, $cookies, $data);
      return;
    }
  }

  // Error if we get through all the routes without finding a match
  error_404([$path], $cookies, $data);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
entrypoint($method, $path, $_COOKIE, array_merge($_SERVER, $_POST));
