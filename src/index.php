<?php // Gentlemen, behold...
$config = array(); // CORNCHAN
define('CORN_VERSION', '0.7.1');
header('X-Powered-By: Corn v' . CORN_VERSION);
$config['test_override'] = isset($_ENV['CORN_TEST_OVERRIDE']);
$config['config_location'] = $config['test_override'] ? '/tmp' : $_ENV['HOME'];
$config['installed'] = @include($config['config_location'] . '/config.php');
$config['remote_addr'] = $_SERVER['REMOTE_ADDR'];

$db = $config['installed'] ? dba_open(CORN_DBA_PATH, 'r', CORN_DBA_HANDLER) : NULL;
foreach (['name', 'language', 'anonymous', 'board_ids', 'secret', 'admin'] as $option) {
  $config[$option] = $db ? dba_fetch('_config.' . $option, $db) : NULL;
}

$config['board_ids'] = $config['board_ids'] ? json_decode($config['board_ids']) : [];
// Set the values that'll be used on the install page
$config['language'] = $config['language'] ?? 'en';
$config['name'] = $config['name'] ?? 'cornchan';

// Returns a HMAC token
function generate_token($tag) { global $config;
  $time = time();
  $message = implode('.', [$tag, $time]);
  $hmac = hash_hmac('sha256', $message, $config['secret']);
  return implode('.', [$time, $hmac]);
}

// Verifies a HMAC token from generate_token
function verify_token($tag, $token, $hours = 1) { global $config;
  [$time, $hmac] = explode('.', $token);
  if ($hours > 0 && time() - intval($time) > $hours * 3600) {
    return false;
  }
  // Verify the hmac because timestamp is good
  $expected_message = implode('.', [$tag, $time]);
  return hash_hmac('sha256', $expected_message, $config['secret']) == $hmac;
}


$config['csrf'] = generate_token('_csrf' . $config['remote_addr']);

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

function render_publish_form_fragment_html($board_or_thread, $trust, $prefill = array()) { global $config;
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
        <?php if (!$trust) echo render_captcha_form_fragment_html(); ?>
        <!-- <label for="password">Password</label>
        <input type="text" name="password" id="password"
            autocomplete="off" class="new-post-password"> -->
        <button class="new-post-submit">Submit</button>
        <input type="hidden" name="csrf_token" value="<?php echo $config['csrf']; ?>">
        <input type="hidden" name="thread_id" value="<?php echo $board_or_thread['thread_id']; ?>">
      </form>
    </section>
  <?php return ob_get_clean();
}

function render_inline_delete_form_fragment_html($thread_or_reply) { global $config;
  $form_action = '/' . $thread_or_reply['board_id'];
  if (!empty($thread_or_reply['reply_id'])) $form_action .= '/t/' . $thread_or_reply['thread_id'];
  $form_action .= '/delete';
  ob_start(); ?>
    <form method="post" action="<?php echo $form_action; ?>" class="delete-post-inline">
      <input type="hidden" name="csrf_token" value="<?php echo $config['csrf']; ?>">
      <input type="hidden" name="reply_id" value="<?php echo $thread_or_reply['reply_id']; ?>">
      <input type="hidden" name="thread_id" value="<?php echo $thread_or_reply['thread_id']; ?>">
      <button class="delete-post-submit">&#9003;</button>
    </form>
  <?php return ob_get_clean();
}

function render_post_tag_fragment_html($post_tag) {
  $tags = array_map(function($hex_chunk) {
    return 'hsl(' . (hexdec($hex_chunk) % 24) * 15 . ', 100%, 50%)';
  }, str_split(hash('sha256', $post_tag), 8));
  ob_start(); ?>
    <span class="post-tag-segment" style="background-color: <?php echo $tags[0]; ?>;">
    </span><span class="post-tag-segment" style="background-color: <?php echo $tags[1]; ?>;">
    </span><span class="post-tag-segment" style="background-color: <?php echo $tags[2]; ?>;">
    </span>
  <?php return ob_get_clean();
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
          <?php echo render_inline_delete_form_fragment_html($reply); ?>
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
        <?php echo render_inline_delete_form_fragment_html($thread); ?>
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

function render_publish_body_html($board_or_thread, $trust, $prefill, $toast) {
  $headline = !empty($board_or_thread['thread_id']) ?
      $board_or_thread['board_id'] . ' / ' . $board_or_thread['thread_id'] :
      $board_or_thread['board_id'];
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $headline; ?></h1>
    </header>
    <hr>
    <div class="toast"><?php echo $toast; ?></div>
    <?php echo render_publish_form_fragment_html($board_or_thread, $trust, $prefill); ?>
  <?php return ob_get_clean();
}

function render_delete_body_html($board_or_thread, $trust, $prefill, $toast) { global $config;
  $headline = !empty($board_or_thread['thread_id']) ?
      $board_or_thread['board_id'] . ' / ' . $board_or_thread['thread_id'] :
      $board_or_thread['board_id'];
  $form_action = '/' . $board_or_thread['board_id'];
  if (!empty($prefill['reply_id'])) $form_action .= '/t/' . $board_or_thread['thread_id'];
  $form_action .= '/delete';
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $headline; ?></h1>
    </header>
    <hr>
    <div class="toast"><?php echo $toast; ?></div>
    <section id="delete-post">
      <form method="post" action="<?php echo $form_action; ?>" class="delete-post">
      <label for="password">Password</label>
        <input type="password" name="password" id="password" class="delete-post-password">
        <input type="hidden" name="csrf_token" value="<?php echo $config['csrf']; ?>">
        <input type="hidden" name="thread_id" value="<?php echo $prefill['thread_id']; ?>">
        <input type="hidden" name="reply_id" value="<?php echo $prefill['reply_id']; ?>">
        <?php if (!$trust) echo render_captcha_form_fragment_html(); ?>
        <button class="delete-post-submit">Delete</button>
      </form>
    </section>
  <?php return ob_get_clean();
}

function render_thread_body_html($thread, $trust) {
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $thread['thread_id']; ?> / <?php echo $thread['board_id']; ?></h1>
    </header>
    <hr>
    <?php echo render_thread_fragment_html($thread); ?>
    <hr>
    <?php echo render_publish_form_fragment_html($thread, $trust); ?>
  <?php return ob_get_clean();
}

function render_board_body_html($board, $trust) {
  ob_start(); ?>
    <header>
      <h1 class="title"><?php echo $board['board_id']; ?></h1>
    </header>
    <?php foreach ($board['threads'] as $thread) { ?>
      <hr>
      <?php echo render_thread_fragment_html($thread); ?>
    <?php } ?>
    <hr>
    <?php echo render_publish_form_fragment_html($board, $trust); ?>
  <?php return ob_get_clean();
}

function render_overboard_body_html($overboard) {
  ob_start(); ?>
    <header>
      <h1 class="title">recent</h1>
    </header>
    <?php foreach ($overboard['threads'] as $thread) { ?>
      <hr>
      <?php echo render_thread_fragment_html($thread); ?>
    <?php } ?>
  <?php return ob_get_clean();
}

function render_install($preconditions) { global $config;
  $descriptions = array();
  $descriptions['can_modify_files'] = [
      true => 'Files can be written and deleted on your server',
      false => 'Cannot write or delete files on your server'];
  $descriptions['gd_extension'] = [
      true => 'GD PHP extension is installed and supports the required filetypes',
      false => 'GD PHP extension or a required filetype isn\'t installed'];
  $descriptions['dba_extension'] =[
      true => 'DBA PHP extension is installed and supports an acceptable handler',
      false => 'DBA PHP extension isn\'t installed or doesn\'t have an acceptable handler'];
  ob_start(); ?>
    <header>
      <h1 class="title">installation</h1>
    </header>
    <hr>
    <section>
      <?php foreach (array_keys($descriptions) as $condition) { ?>
        <p>
          <?php if ($preconditions[$condition]) { ?>
            <span>&#128077;</span>
          <?php } else { ?>
            <span>&#9940;</span>
          <?php } ?>
          <?php echo $descriptions[$condition][$preconditions[$condition]]; ?>
        </p>
      <?php } ?>
      <hr>
      <?php if ($preconditions['can_create_db']) { ?>
        <form method="post">
          <label for="dba_path">DB File Path</label>
          <input type="text" name="dba_path"
              value="<?php echo $config['config_location'] . '/cornchan.db'; ?>"
              id="dba_path" autocomplete="off">
          <label for="admin_password">Admin Password</label>
          <input type="text" name="admin_password" value="<?php echo bin2hex(random_bytes(8)); ?>"
              id="admin_password" autocomplete="off">
          <label for="initial_boards">Initial Boards</label>
          <input type="text" name="initial_boards" value="corn, prog, news"
              id="initial_boards" autocomplete="off">
          <label for="name">Board Name</label>
          <input type="text" name="name" value="<?php echo $config['name']; ?>"
              id="name" autocomplete="off">
          <label for="language">Language</label>
          <input type="text" name="language" value="<?php echo $config['language']; ?>"
              id="language" autocomplete="off">
          <button>Install</button>
        </form>
      <?php } else { ?>
        <p>Please fix the issues above before continuing</p>
      <?php } ?>
    </section>
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
        <a href="/" class="logo" style="font-weight: bold;"><?php echo $config['name']; ?></a>
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
        <small>
          <?php echo CORN_VERSION; ?> /
          <?php echo $last_updated; ?> /
          <?php echo $exec_time; ?>ms
        </small>
      </footer>
    </body>
    </html>
  <?php return ob_get_clean();
}

function with_write_db($func) { global $config, $db;
  dba_close($db); // Close the db for reads and reopen for writes
  $db = $db_w = dba_open(CORN_DBA_PATH, 'w', CORN_DBA_HANDLER);
  $out = $func($db_w); // Execute the callback with the writable db handle
  dba_close($db_w); // Close the db for writes and reopen for reads
  $db = dba_open(CORN_DBA_PATH, 'r', CORN_DBA_HANDLER);
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
  dba_replace($reply_key . '.ip', $config['remote_addr'], $db_w);
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
  if (empty(trim($thread['subject'])) && empty(trim($thread['message']))) return false;

  $thread_id = fresh_id($db_w);
  $thread_key = $board_id . '#' . $thread_id;
  dba_replace($thread_key . '.board_id', $board_id, $db_w);
  dba_replace($thread_key . '.thread_id', $thread_id, $db_w);
  dba_replace($thread_key . '.subject', $thread['subject'], $db_w);
  dba_replace($thread_key . '.message', $thread['message'], $db_w);
  // dba_replace($thread_key . '.name', $thread['name'], $db_w);
  dba_replace($thread_key . '.ip', $config['remote_addr'], $db_w);
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

function delete_reply_data($db_w, $reply) {
  $reply = fetch_reply_data($reply['board_id'], $reply['thread_id'], $reply['reply_id']);
  if (!$reply) return;

  $attributes = ['board_id', 'thread_id', 'reply_id', 'time',
      'message', 'next_reply_id', 'prev_reply_id', 'ip'];
  foreach ($attributes as $attribute) {
    dba_delete($reply['key'] . '.' . $attribute, $db_w);
  }

  $thread_key = $reply['board_id'] . '#' . $reply['thread_id'];
  $prev_reply_key = $thread_key . '#' . $reply['prev_reply_id'];
  $next_reply_key = $thread_key . '#' . $reply['next_reply_id'];
  dba_replace($prev_reply_key . '.next_reply_id', $reply['next_reply_id'], $db_w);
  dba_replace($next_reply_key . '.prev_reply_id', $reply['prev_reply_id'], $db_w);
  $thread_reply_count = intval(dba_fetch($thread_key . '.reply_count', $db_w));
  dba_replace($thread_key . '.reply_count', $thread_reply_count - 1, $db_w);
}

function delete_thread_data($db_w, $thread) {
  $thread = fetch_thread_data($thread['board_id'], $thread['thread_id']);
  if (!$thread) return;

  foreach ($thread['replies'] as $reply) {
    delete_reply_data($db_w, $reply);
  }

  $attributes = ['board_id', 'thread_id', 'time', 'reply_count',
      'subject', 'message', 'next_thread_id', 'prev_thread_id', 'ip'];
  foreach ($attributes as $attribute) {
    dba_delete($thread['key'] . '.' . $attribute, $db_w);
  }

  dba_delete($thread['key'] . '#reply_head.next_reply_id', $db_w);
  dba_delete($thread['key'] . '#reply_tail.prev_reply_id', $db_w);

  $board_id = $thread['board_id'];
  $prev_thread_key = $board_id . '#' . $thread['prev_thread_id'];
  $next_thread_key = $board_id . '#' . $thread['next_thread_id'];
  dba_replace($prev_thread_key . '.next_thread_id', $thread['next_thread_id'], $db_w);
  dba_replace($next_thread_key . '.prev_thread_id', $thread['prev_thread_id'], $db_w);
  $board_thread_count = intval(dba_fetch($board_id . '.thread_count', $db_w));
  dba_replace($board_id . '.thread_count', $board_thread_count - 1, $db_w);
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

function fetch_overboard_data() { global $config, $db;
  $boards = array_map(function($board_id) {
    return fetch_board_data($board_id);
  }, $config['board_ids']);
  $threads = call_user_func_array('array_merge', array_map(function($board) {
    return $board['threads'];
  }, $boards));
  // Sorts in place
  usort($threads, function($thread_a, $thread_b) {
    $time_a = empty($thread_a['replies']) ?
        $thread_a['time'] : end($thread_a['replies'])['time'];
    $time_b = empty($thread_b['replies']) ?
        $thread_b['time'] : end($thread_b['replies'])['time'];
    return $time_b - $time_a;
  });
  $overboard = array();
  $overboard['threads'] = $threads;
  return $overboard;
}

function post_publish($params, $data, $trust) { global $config;
  $thread_or_reply_data = array_filter(array_merge($data, $params), function($key) {
    return in_array($key, ['board_id', 'thread_id', 'subject', 'message']);
  }, ARRAY_FILTER_USE_KEY);
  // This post is a reply if there is a thread_id
  $is_reply_post = !empty($thread_or_reply_data['thread_id']);

  $board_or_thread = $is_reply_post ?
      fetch_thread_data($params['board_id'], $params['thread_id']) :
      fetch_board_data($params['board_id']);
  $title = $is_reply_post ? $board_or_thread['thread_id'] . ' / ' : '';
  $title .= $board_or_thread['board_id'] . ' / ' . $config['name'];

  if (!$trust) {
    echo render_html($title, render_publish_body_html($board_or_thread, $trust,
        $thread_or_reply_data, 'Unauthorized; try again'));
    return;
  }

  $thread_or_reply = with_write_db(function($db_w) use ($thread_or_reply_data, $is_reply_post) {
    return $is_reply_post ?
        put_reply_data($db_w, $thread_or_reply_data) :
        put_thread_data($db_w, $thread_or_reply_data);
  });

  if (!$thread_or_reply) {
    echo render_html($title, render_publish_body_html($board_or_thread, $trust,
        $thread_or_reply_data, 'Something went wrong; try again'));
    return;
  }

  header('Location: ' . $thread_or_reply['href']);
}

function post_delete($params, $data, $trust) { global $config;
  $thread_or_reply_id = array_filter(array_merge($data, $params), function($key) {
    return in_array($key, ['board_id', 'thread_id', 'reply_id']);
  }, ARRAY_FILTER_USE_KEY);
  $is_reply_delete = !empty($thread_or_reply_id['reply_id']);

  $board_or_thread = $is_reply_delete ?
      fetch_thread_data($params['board_id'], $params['thread_id']) :
      fetch_board_data($params['board_id']);
  $title = $is_reply_delete ? $board_or_thread['thread_id'] . ' / ' : '';
  $title .= $board_or_thread['board_id'] . ' / ' . $config['name'];

  if ($trust !== '_admin') {
    echo render_html($title, render_delete_body_html($board_or_thread, $trust,
        $thread_or_reply_id, 'Unauthorized; try again'));
    return;
  }

  with_write_db(function($db_w) use ($thread_or_reply_id, $is_reply_delete) {
    return $is_reply_delete ?
        delete_reply_data($db_w, $thread_or_reply_id) :
        delete_thread_data($db_w, $thread_or_reply_id);
  });

  header('Location: ' . $board_or_thread['href']);
}

function get_thread($params, $data, $trust) { global $config;
  $thread = fetch_thread_data($params['board_id'], $params['thread_id']);
  if (!$thread) return error_404($params, $data, $trust);
  $title = $thread['thread_id'] . ' / ' . $thread['board_id'] . ' / ' . $config['name'];
  echo render_html($title, render_thread_body_html($thread, $trust));
}

function get_board($params, $data, $trust) { global $config;
  $board = fetch_board_data($params['board_id']);
  // This likely won't happen because the regex already checks
  if (!$board) return error_404($params, $data, $trust);
  $title = $board['board_id'] . ' / ' . $config['name'];
  echo render_html($title, render_board_body_html($board, $trust));
}

function get_root($params, $data, $trust) { global $config;
  $overboard = fetch_overboard_data();
  echo render_html($config['name'], render_overboard_body_html($overboard));
}

function error_404($params, $data, $trust) {
  // echo render_html('404 / ' . $config['name'], '');
  echo '<h1>Error 404</h1>';
  echo '<pre>'; var_dump(['error_404', $params, $data, $trust]); echo '</pre>';
}

function install($method, $data) { global $config;
  $preconditions = array();
  $test_file = $config['config_location'] . '/test';
  $preconditions['can_modify_files'] = @touch($test_file) && @unlink($test_file);
  $preconditions['gd_extension'] = extension_loaded('gd') && gd_info()['PNG Support'];
  $preconditions['dba_extension'] = extension_loaded('dba')
      && (in_array('lmdb', dba_handlers()) || in_array('gdbm', dba_handlers()));
  $preconditions['can_create_db'] = $preconditions['can_modify_files']
      && $preconditions['gd_extension'] && $preconditions['dba_extension'];

  if ($preconditions['can_create_db'] && $method === 'POST') {
    $dba_handler = in_array('lmdb', dba_handlers()) ? 'lmdb' : 'gdbm';
    // First create the static config file
    $config_file_data = '<?php
        define(\'CORN_DBA_PATH\', \'' . $data['dba_path'] . '\');
        define(\'CORN_DBA_HANDLER\', \'' . $dba_handler . '\');
        return true; ?>';
    file_put_contents($config['config_location'] . '/config.php', $config_file_data)
      or exit('Can\'t write config file!');

    // Now initialize the db file
    $db_c = dba_open($data['dba_path'], 'c', $dba_handler)
      or exit('Can\'t initialize a new db file!');

    // Explode and trim the initial boards
    $board_ids = array_map(function($board_id) {
      return trim($board_id);
    }, explode(',', $data['initial_boards']));

    // Put all the expected keys
    dba_replace('_metadata.id', '999', $db_c);
    dba_replace('_config.name', $data['name'], $db_c);
    dba_replace('_config.anonymous', 'Cornonymous', $db_c);
    dba_replace('_config.language', $data['language'], $db_c);
    dba_replace('_config.board_ids', json_encode($board_ids), $db_c);
    dba_replace('_config.admin', hash('sha256', $data['admin_password']), $db_c);
    dba_replace('_config.secret', bin2hex(random_bytes(32)), $db_c);
    foreach ($board_ids as $board_id) {
      dba_replace($board_id . '.thread_count', '0', $db_c);
      dba_replace($board_id . '#thread_head.next_thread_id', 'thread_tail', $db_c);
      dba_replace($board_id . '#thread_tail.prev_thread_id', 'thread_head', $db_c);
    }

    // Close after db initialization
    dba_close($db_c);

    // Redirect to root
    header('Location: /');
    return;
  }

  echo render_html('installation / cornchan', render_install($preconditions));
}

function debug($params, $data, $trust) { global $config, $db;
  if (!$config['test_override']) return error_404($params, $data, $trust);

  $key = dba_firstkey($db);
  while ($key !== false) {
    $value = dba_fetch($key, $db);
    echo $key . ' => ' . $value . '<br>';
    $key = dba_nextkey($db);
  }
}

function middleware_verify_csrf($method, $data) { global $config;
  if ($method === 'GET') return true;
  $csrf_token = $data['csrf_token'];
  return verify_token('_csrf' . $config['remote_addr'], $csrf_token);
}

function middleware_verify_captcha($data) { global $config;
  $captcha_token = $data['captcha_token'];
  $captcha_answer = strtoupper($data['captcha_answer']);
  if ($config['test_override'] && $captcha_answer === 'GOODCAPTCHA') return true;
  return verify_token($captcha_answer, $captcha_token);
}

function middleware_get_existing_role($cookies) {
  if (empty($cookies['role'])) return false;
  $role_cookie = explode('.', $cookies['role']);
  $role_cookie_token = implode('.', array_slice($role_cookie, 1));
  if (in_array($role_cookie[0], ['_ok', '_admin'])
      && (verify_token('_ok', $role_cookie_token, 3)
        || verify_token('_admin', $role_cookie_token, 0))) {
    return $role_cookie[0];
  }
  return false;
}

function middleware_fetch_role($data) { global $config;
  if (empty($data['password'])) return false;
  $hashed_password = hash('sha256', $data['password']);
  return $hashed_password === $config['admin'] ? '_admin' : false;
}

// Establishes a level of trust for the request. Returns false
// when totally untrusted, otherwise returns the user's role name
function middleware_establish_trust($method, $cookies, $data) { global $config, $db;
  if (!middleware_verify_csrf($method, $data)) return false;
  // If you don't have an existing role cookie and didn't post
  // any new data, then there's no way for you to have a role
  if (empty($cookies['role']) && empty($data)) return false;

  // Existing role from cookie
  $existing_role = middleware_get_existing_role($cookies);

  // Verify any newly submitted captcha
  $solved_captcha = middleware_verify_captcha($data);

  // If you don't have an existing role and didn't successfully
  // solve a captcha then there's nothing more you can do
  if (!$existing_role && !$solved_captcha) return false;

  // If you have an existing role or solved a captcha and you've
  // submitted a password, then we'll see if you get admin
  $fresh_role = middleware_fetch_role($data);

  // If you freshly posted admin
  if ($fresh_role) {
    // then update your role and return
    setcookie('role', $fresh_role . '.' . generate_token($fresh_role), 0, '/');
    return $fresh_role;
  // Otherwise if you had an existing role
  } elseif ($existing_role) {
    // then just return it
    return $existing_role;
  }

  // If you only solved a captcha
  // then assign basic trust
  setcookie('role', '_ok' . '.' . generate_token('_ok'), 0, '/');
  return '_ok';
}

function entrypoint($method, $path, $cookies, $data) { global $config;
  $routes = array();
  $routes['GET#/'] = 'get_root';
  $routes['GET#/_debug'] = 'debug';
  $routes['GET#/%board_id%/'] = 'get_board';
  $routes['POST#/%board_id%/publish'] = 'post_publish';
  $routes['POST#/%board_id%/delete'] = 'post_delete';
  $routes['GET#/%board_id%/t/%thread_id%'] = 'get_thread';
  $routes['POST#/%board_id%/t/%thread_id%/publish'] = 'post_publish';
  $routes['POST#/%board_id%/t/%thread_id%/delete'] = 'post_delete';

  $thread_regex = '(?P<thread_id>\d+)';
  $page_number_regex = '(?P<page_number>\d+)';
  $board_regex = '(?P<board_id>' . implode('|', $config['board_ids']) . ')';

  if (!$config['installed']) {
    install($method, $data);
    return;
  }

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
      $trust = middleware_establish_trust($method, $cookies, $data);
      // Call the function for this route with all the data
      call_user_func($route_handler, $params, $data, $trust);
      return;
    }
  }

  // Error if we get through all the routes without finding a match
  error_404($params, $data, $trust);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
entrypoint($method, $path, $_COOKIE, $_POST);
