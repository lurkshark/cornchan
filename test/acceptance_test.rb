require 'test/unit/capybara'

sleep 5 # Wait for Selenium to boot
class AcceptanceTest < Test::Unit::TestCase
  include Capybara::DSL

  Capybara.run_server = false
  Capybara.default_driver = :selenium
  Capybara.app_host = "http://#{ENV['TARGET_HOST']}"
  Capybara.register_driver :selenium do |app|
    Capybara::Selenium::Driver.new(app,
      browser: :remote,
      desired_capabilities: :firefox,
      url: "http://#{ENV['SELENIUM_HOST']}:#{ENV['SELENIUM_PORT']}/wd/hub")
  end

  def setup
  end

  def test_load_corn
    visit("/corn/")
    within("body") do
      assert_equal("OK", text)
    end
  end
end
