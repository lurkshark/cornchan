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
$config['base_path'] = ''; // Allows for non-top-level;

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

function render_new_post_form_fragment_html($board_or_thread) { global $config;
  ob_start(); ?>
    <section id="new-post">
      <form method="post" action="<?php echo $config['base_path'] ?>/post.php" class="new-post">
        <label for="subject">Subject</label>
        <input type="text" name="subject" id="subject" autocomplete="off" class="new-post-subject">
        <label for="message">Message</label>
        <textarea name="message" id="message" class="new-post-message"></textarea>
        <?php echo render_captcha_form_fragment_html(); ?>
        <label for="password">Password</label>
        <input type="text" name="password" id="password"
            autocomplete="off" class="new-post-password">
        <button class="new-post-submit">Submit</button>
        <input type="hidden" name="csrf_token" value="<?php echo $config['csrf']; ?>">
        <input type="hidden" name="board_id" value="<?php echo $board_or_thread['board_id']; ?>">
        <input type="hidden" name="thread_id" value="<?php echo $board_or_thread['thread_id']; ?>">
      </form>
    </section>
  <?php return ob_get_clean();
}

function render_reply_fragment_html($reply) {}

function render_thread_fragment_html($thread) {
  ob_start(); ?>
    <article id="<?php echo $thread['thread_id']; ?>" class="thread">
      <header class="post-details">
        <span class="post-subject"><?php echo $thread['subject']; ?></span>
        <span class="post-name"><?php echo $thread['name']; ?></span>
        <span class="post-tag"><?php echo $thread['tag']; ?></span>
        <span class="post-time">
          <time><?php echo date('Y-m-d H:i', $thread['time']); ?></time>
        </span>
        <span class="post-id">
          <a href="<?php echo $thread['href_anchor']; ?>">No.<?php echo $thread['thread_id']; ?></a>
        </span>
      </header>
      <div class="post-message">
        <?php echo str_replace('&#13;&#10;', '<br>', $thread['message']); ?>
      </div>
    </article>
  <?php return ob_get_clean();
}

function render_board_body_html($board) {
  ob_start(); ?>
    <header>
      <h1><?php echo $board['board_id']; ?></h1>
    </header>
    <?php foreach ($board['threads'] as $thread) { ?>
      <hr>
      <?php echo render_thread_fragment_html($thread); ?>
    <?php } ?>
    <hr>
    <?php echo render_new_post_form_fragment_html($board); ?>
  <?php return ob_get_clean();
}

function render_thread_body_html($thread) {
  ob_start(); ?>
    <header>
      <h1><?php echo $thread['thread_id']; ?> : <?php echo $thread['board_id']; ?></h1>
    </header>
    <hr>
    <?php echo render_thread_fragment_html($thread); ?>
    <hr>
    <?php echo render_new_post_form_fragment_html($thread); ?>
  <?php return ob_get_clean();
}

function render_html($title, $body) { global $config;
  ob_start(); ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <title><?php echo $title; ?></title>
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
      <nav class="top-bar">
        <span style="font-weight: bold;"><?php echo $config['name']; ?></span>
        <?php foreach ($config['board_ids'] as $board_id) {
          $board_path = $config['base_path'] . '/' . $board_id . '/'; ?>
          / <a href="<?php echo $board_path; ?>"><?php echo $board_id; ?></a>
        <?php } ?>
      </nav>
      <?php echo $body; ?>
      <hr>
      <?php // Calculate the milliseconds it took to render the page and last update
        $exec_time = intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
        $last_updated = date('Y-m-d H:i', filemtime(__FILE__)); ?>
      <footer>
        <small><?php echo $last_updated; ?> / <?php echo $exec_time; ?>ms</small>
      </footer>
    </body>
    </html>
  <?php return ob_get_clean();
}

function with_write_db($func) { global $config, $db;
  dba_close($db); // Close the db for reads and reopen for writes
  $db = $db_w = dba_open($config['dba_path'], 'w', $config['dba_handler']);
  $func($db_w); // Execute the callback with the writable db handle
  dba_close($db_w); // Close the db for writes and reopen for reads
  $db = dba_open($config['dba_path'], 'r', $config['dba_handler']);
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
  $old_head_next_key = $thread['board_id'] . '#' . $old_head_next_id;
  dba_replace($thread_head_key . '.next_thread_id', $thread['thread_id'], $db_w);
  dba_replace($thread['key'] . '.next_thread_id', $old_head_next_id, $db_w);
  dba_replace($thread['key'] . '.prev_thread_id', 'thread_head', $db_w);
  dba_replace($old_head_next_key . '.prev_thread_id', $thread['thread_id'], $db_w);
}

function put_thread_data($db_w, $thread) { global $config;
  $thread_id = fresh_id($db_w);
  $board_id = $thread['board_id'];
  $thread_key = $board_id . '#' . $thread_id;
  dba_replace($thread_key . '.board_id', $board_id, $db_w);
  dba_replace($thread_key . '.thread_id', $thread_id, $db_w);
  // $name = filter_var($thread['name'], FILTER_SANITIZE_SPECIAL_CHARS);
  $subject = filter_var($thread['subject'], FILTER_SANITIZE_SPECIAL_CHARS);
  $message = filter_var($thread['message'], FILTER_SANITIZE_SPECIAL_CHARS);
  dba_replace($thread_key . '.subject', $subject, $db_w);
  dba_replace($thread_key . '.message', $message, $db_w);
  dba_replace($thread_key . '.time', time(), $db_w);

  dba_replace($thread_key . '.reply_count', '0', $db_w);
  dba_replace($thread_key . '#reply_head.next_reply_id', 'reply_tail', $db_w);
  dba_replace($thread_key . '#reply_tail.prev_reply_id', 'reply_head', $db_w);

  $board_thread_count = intval(dba_fetch($board_id . '.thread_count', $db_w));
  dba_replace($board_id . '.thread_count', $board_thread_count + 1, $db_w);
  return fetch_thread_data($board_id, $thread_id);
}

function fetch_thread_data($board_id, $thread_id) { global $config, $db;
  $thread_key = $board_id . '#' . $thread_id; 
  $thread = ['board_id' => $board_id, 'thread_id' => $thread_id];
  $thread['time'] = intval(dba_fetch($thread_key . '.time', $db));
  if (empty($thread['time'])) return false;

  // $thread['name'] = dba_fetch($thread_key . '.name', $db);
  $thread['subject'] = dba_fetch($thread_key . '.subject', $db);
  $thread['message'] = dba_fetch($thread_key . '.message', $db);
  $thread['next_thread_id'] = dba_fetch($thread_key . '.next_thread_id', $db);
  $thread['prev_thread_id'] = dba_fetch($thread_key . '.prev_thread_id', $db);
  $thread['reply_count'] = intval(dba_fetch($thread_key . '.reply_count', $db));
  $thread['href'] = $config['base_path'] . '/' . $board_id . '/t/' . $thread_id;
  $thread['href_anchor'] = $thread['href'] . '#' . $thread_id;
  $thread['key'] = $thread_key;

  $thread['replies'] = array();
  return $thread;
}

function fetch_board_data($board_id) { global $config, $db;
  if (!in_array($board_id, $config['board_ids'])) return false;
  $board = ['board_id' => $board_id]; // Just for completeness
  $board['thread_count'] = intval(dba_fetch($board_id . '.thread_count', $db));
  $board['href'] = $config['base_path'] . '/' . $board_id . '/';

  $board['threads'] = array();
  $current_thread_id = dba_fetch($board_id . '#thread_head.next_thread_id', $db);
  while ($current_thread_id !== 'thread_tail') {
    $current_thread = fetch_thread_data($board_id, $current_thread_id);
    $current_thread_id = $current_thread['next_thread_id'];
    $board['threads'][] = $current_thread;
  }

  return $board;
}

function new_post($params, $cookies, $data) { global $config;
  $thread_data = array_filter($data, function($key) {
    return in_array($key, ['board_id', 'subject', 'message']);
  }, ARRAY_FILTER_USE_KEY);

  with_write_db(function($db_w) use ($thread_data) {
    $thread = put_thread_data($db_w, $thread_data);
    bump_thread($db_w, $thread);
  });
}

function get_root($params, $cookies, $data) { global $config;
  echo render_html($config['name'], '');
}

function get_board($params, $cookies, $data) { global $config;
  $board = fetch_board_data($params['board_id']);
  // This likely won't happen because the regex already checks
  if (!$board) return error_404($params, $cookies, $data);
  $title = $board['board_id'] . ' : ' . $config['name'];
  echo render_html($title, render_board_body_html($board));
}

function get_thread($params, $cookies, $data) { global $config;
  $thread = fetch_thread_data($params['board_id'], $params['thread_id']);
  if (!$thread) return error_404($params, $cookies, $data);
  $title = $thread['thread_id'] . ' : ' . $thread['board_id'] . ' : ' . $config['name'];
  echo render_html($title, render_thread_body_html($thread));
}

function error_404($params, $cookies, $data) {
  echo '<h1>Error 404</h1>';
  echo '<pre>'; var_dump(['error_404', $params, $cookies, $data]); echo '</pre>';
}

function debug($params, $cookies, $data) { global $db;
  $key = dba_firstkey($db);
  while ($key !== false) {
    $value = dba_fetch($key, $db);
    echo $key . ' => ' . $value . '<br>';
    $key = dba_nextkey($db);
  }
  phpinfo();
}

function entrypoint($method, $path, $cookies, $data) { global $config;
  $routes = array();
  $routes['GET#/'] = 'get_root';
  $routes['GET#/_debug'] = 'debug';
  $routes['GET#/%board_id%/'] = 'get_board';
  $routes['POST#/%board_id%/publish'] = 'post_board_new';
  $routes['GET#/%board_id%/t/%thread_id%'] = 'get_thread';
  $routes['POST#/%board_id%/%thread_id%/publish'] = 'post_thread_new';

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
      // Call the function for this route
      call_user_func($route_handler, $params, $cookies, $data);
      return;
    }
  }

  // Error if we get through all the routes without finding a match
  error_404([$path], $cookies, $data);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
entrypoint($method, $path, $_COOKIE, $_POST);
