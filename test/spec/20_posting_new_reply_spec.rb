feature "Posting a new reply to a thread" do
  given(:subject) { lorem_ipsum(5) }
  given(:message) { lorem_ipsum(64) }
  given(:captcha) { "GOODCAPTCHA" }
  given(:cookie)  { :checked }

  background do
    visit "/corn/res/1000.html"
    within("#newpost") do
      fill_in "subject", with: subject
      fill_in "message", with: message
      fill_in "captcha_answer", with: captcha
      uncheck "opt_in_cookie" if cookie == :unchecked
      check "opt_in_cookie" if cookie == :checked
      click_button "Submit"
    end
  end

  context "when the form is fully filled-out" do
    xscenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/corn/res/1000.html")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the form is filled-out with a bad captcha" do
    given(:captcha) { "BADCAPTCHA" }
    xscenario "stays on the new post page and shows an error" do
      expect(page).to have_current_path("/post.php")
      expect(page).to have_content("You got the CAPTCHA wrong")
    end
  end

  context "when the form is empty" do
    given(:subject) { "" }
    given(:message) { "" }
    given(:captcha) { "" }
    xscenario "stays on the new post page and shows an error" do
      expect(page).to have_current_path("/post.php")
      expect(page).to have_content("You need a subject or message")
    end
  end

  context "when the subject is empty" do
    given(:subject) { "" }
    xscenario "redirects to the thread and shows the new message-only post" do
      expect(page).to have_current_path("/corn/res/1000.html")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    given(:message) { "" }
    xscenario "redirects to the thread and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/res/1000.html")
      expect(page).to have_content(subject)
    end
  end

  context "when the captcha cookie opt-in is checked" do
    given(:cookie) { :checked }
    xscenario "redirects to the thread and doesn't prompt for a CAPTCHA" do
      expect(page).to have_current_path("/corn/res/1000.html")
      expect(page).to_not have_content("CAPTCHA")
    end
  end

  context "when the captcha cookie opt-in is unchecked" do
    given(:cookie) { :unchecked }
    xscenario "redirects to the thread and prompts for a CAPTCHA" do
      expect(page).to have_current_path("/corn/res/1000.html")
      expect(page).to have_content("CAPTCHA")
    end
  end
end
