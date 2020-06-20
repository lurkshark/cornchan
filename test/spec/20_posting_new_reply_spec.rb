feature "Posting a new reply to a thread" do
  given(:message) { lorem_ipsum(64) }
  given(:captcha) { "GOODCAPTCHA" }
  given(:thread_id) do
    page.current_path.match(/\/index.php\/corn\/t\/(\d+)/)[1]
  end

  background do
    visit "/index.php/corn/"
    first(".thread a.post-id").click
    within("#new-post") do
      fill_in "message", with: message
      fill_in "captcha_answer", with: captcha
      click_button "Submit"
    end
  end

  context "when the form is fully filled-out" do
    scenario "redirects to the thread and shows the new reply" do
      expect(page).to have_current_path("/index.php/corn/t/#{thread_id}")
      expect(page).to have_content(message)
    end
  end

  context "when the message is empty" do
    given(:message) { "" }
    scenario "fails to post the new reply and stays on the publish path" do
      expect(page).to have_current_path("/index.php/corn/t/#{thread_id}/publish")
    end
  end

  scenario "shows the reply on the board" do
    visit "/index.php/corn/"
    expect(page).to have_content(message)
  end
end
