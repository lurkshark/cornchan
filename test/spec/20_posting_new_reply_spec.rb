feature "Posting a new reply to a thread" do
  given(:subject) { lorem_ipsum(5) }
  given(:message) { lorem_ipsum(64) }
  given(:captcha) { "GOODCAPTCHA" }
  given(:cookie)  { :checked }

  background do
    visit "/corn/10000/new"
    within("#newreply") do
      fill_in "lorem", with: subject
      fill_in "ipsum", with: message
      fill_in "captcha-answer", with: captcha
      uncheck "opt-in-cookie" if cookie == :unchecked
      check "opt-in-cookie" if cookie == :checked
      click_button "Submit"
    end
  end

  context "when the form is fully filled-out" do
    scenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the form is filled-out with a bad captcha" do
    given(:captcha) { "BADCAPTCHA" }
    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/10000/new")
      expect(page).to have_content("You got the CAPTCHA wrong")
    end
  end

  context "when the form is empty" do
    given(:subject) { "" }
    given(:message) { "" }
    given(:captcha) { "" }
    scenario "stays on the new thread page and shows an error" do
      expect(page).to have_current_path("/corn/10000/new")
      expect(page).to have_content("You need a subject or message")
    end
  end

  context "when the subject is empty" do
    given(:subject) { "" }
    scenario "redirects to the thread and shows the new message-only post" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    given(:message) { "" }
    scenario "redirects to the thread and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content(subject)
    end
  end

  context "when the captcha cookie opt-in is checked" do
    given(:cookie) { :checked }
    scenario "redirects to the board and doesn't prompt for a CAPTCHA" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to_not have_content("CAPTCHA")
    end
  end

  context "when the captcha cookie opt-in is unchecked" do
    given(:cookie) { :unchecked }
    scenario "redirects to the board and prompts for a CAPTCHA" do
      expect(page).to have_current_path("/corn/10000")
      expect(page).to have_content("CAPTCHA")
    end
  end
end
