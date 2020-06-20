require 'capybara/rspec'

# Wait for Selenium to boot
$wait ||= sleep 5

# Capybara configuration
Capybara.run_server = false
Capybara.default_driver = :selenium
Capybara.app_host = "http://#{ENV['TARGET_HOST']}"
Capybara.register_driver :selenium do |app|
  Capybara::Selenium::Driver.new(app,
    browser: :remote,
    desired_capabilities: :firefox,
    url: "http://#{ENV['SELENIUM_HOST']}:#{ENV['SELENIUM_PORT']}/wd/hub")
end

def lorem_ipsum(words)
  Array.new(words) do
    [ "lorem", "ipsum", "dolor", "sit", "amet", "consectetur", "adipiscing", "elit", "sed", "do", "eiusmod",
      "tempor", "incididunt", "ut", "labore", "et", "dolore", "magna", "aliqua", "adipiscing", "diam", "donec",
      "adipiscing", "tristique", "risus", "nec", "feugiat", "in", "eu", "ultrices", "vitae", "auctor", "eu", "augue",
      "ut", "lectus", "arcu", "bibendum", "odio", "euismod", "lacinia", "at", "quis", "risus", "sed", "vulputate", "ut",
      "faucibus", "pulvinar", "elementum", "integer", "enim", "neque", "volutpat", "ac", "tincidunt", "aliquet",
      "bibendum", "enim", "facilisis", "gravida", "neque", "convallis", "a", "cras", "vulputate", "mi", "sit", "amet",
      "mauris", "commodo", "quis", "pharetra", "vel", "turpis", "nunc", "eget", "lorem", "dolor", "lacus", "sed",
      "viverra", "tellus", "in", "hac", "habitasse", "platea", "dictumst", "tempor", "orci", "dapibus", "ultrices",
      "in", "iaculis", "nunc", "sed", "enim", "facilisis", "gravida", "neque", "convallis", "a", "cras", "leo", "urna",
      "magnis", "at", "elementum", "eu", "facilisis", "sed", "odio", "morbi", "in", "hac", "habitasse", "platea",
      "dictumst", "quisque", "sagittis", "purus", "sit", "turpis", "nunc", "eget", "lorem", "dolor", "sed", "viverra",
      "amet", "consectetur", "adipiscing", "elit", "pellentesque", "habitant", "morbi", "sapien", "et", "ligula",
      "ullamcorper", "malesuada", "proin", "libero", "nunc", "consequat", "interdum", "elit", "eget", "gravida",
      "sociis", "natoque", "penatibus", "et"].sample
  end.join(" ").capitalize
end

def post_reply(which = :oldest)
  visit "/index.php/corn/"
  threads = find_all(".thread a.post-id")
  if which == :oldest
    threads.last.click
  else
    threads.first.click
  end

  subject = find(".thread .post-subject").text
  within("#new-post") do
    fill_in "message", with: lorem_ipsum(64)
    if page.has_field?("captcha_answer")
      fill_in "captcha_answer", with: "GOODCAPTCHA"
    end
    click_button "Submit"
  end
  subject
end

def post_thread
  visit "/index.php/corn/"
  subject = lorem_ipsum(5)
  within("#new-post") do
    fill_in "subject", with: subject
    fill_in "message", with: lorem_ipsum(64)
    if page.has_field?("captcha_answer")
      fill_in "captcha_answer", with: "GOODCAPTCHA"
    end
    click_button "Submit"
  end
  subject
end

