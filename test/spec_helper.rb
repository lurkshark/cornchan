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
