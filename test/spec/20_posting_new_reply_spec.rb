feature "Posting a new reply to a thread" do
  given(:subject) { lorem_ipsum(5) }
  given(:message) { lorem_ipsum(64) }
  given(:captcha) { "GOODCAPTCHA" }

  background do
    visit "/corn/t/1000"
    within("#new-post") do
      fill_in "subject", with: subject
      fill_in "message", with: message
      fill_in "captcha_answer", with: captcha
      click_button "Submit"
    end
  end

  context "when the form is fully filled-out" do
    scenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/corn/t/1000")
      expect(page).to have_content(subject)
      expect(page).to have_content(message)
    end
  end

  context "when the subject is empty" do
    given(:subject) { "" }
    scenario "redirects to the thread and shows the new message-only post" do
      expect(page).to have_current_path("/corn/t/1000")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    given(:message) { "" }
    scenario "redirects to the thread and shows the new subject-only post" do
      expect(page).to have_current_path("/corn/t/1000")
      expect(page).to have_content(subject)
    end
  end
end
